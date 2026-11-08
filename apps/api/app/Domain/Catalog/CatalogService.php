<?php

namespace App\Domain\Catalog;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CatalogService
{
    private const MAX_FAMILY_LEVELS = 20;

    public function __construct(private CodeAllocator $codes) {}

    public function find(Company $company, string $resource, string $id, bool $lock = false): ScopedRecord
    {
        $class = CatalogDefinition::MODELS[$resource];
        $query = $class::where('company_id', $company->id)->where('public_id', $id);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    public function listing(User $user, Company $company, string $resource, array $filters)
    {
        Gate::forUser($user)->authorize('catalog.view', [$company, $resource]);
        $class = CatalogDefinition::MODELS[$resource];
        $query = $class::where('company_id', $company->id);
        if ($resource === 'product-variants') {
            $query->withExists(['skus' => fn ($q) => $q->where('company_id', $company->id)]);
        }
        if (isset($filters['active'])) {
            $query->where('active', $filters['active']);
        }
        if ($filters['q'] ?? null) {
            $query->where(function ($q) use ($filters, $resource, $company) {
                $q->where('name', 'like', '%'.$filters['q'].'%');
                if (in_array($resource, ['units', 'commercial-skus'], true)) {
                    $q->orWhere($resource === 'units' ? 'code' : 'sku', 'like', '%'.$filters['q'].'%');
                }
                if ($resource === 'product-variants') {
                    $q->orWhere('flavor', 'like', '%'.$filters['q'].'%');
                }
                if ($resource === 'commercial-skus') {
                    $q->orWhereIn('id', ProductBarcode::where('company_id', $company->id)->where('code', 'like', '%'.$filters['q'].'%')->select('sku_id'));
                }
            });
        }
        if ($resource === 'products') {
            if ($filters['family_id'] ?? null) {
                $query->whereIn('family_id', ProductFamily::where('company_id', $company->id)->where('public_id', $filters['family_id'])->select('id'));
            }
            if ($filters['type'] ?? null) {
                $query->where('type', $filters['type']);
            }
        }
        if (in_array($resource, ['product-variants', 'commercial-skus'], true)) {
            $products = Product::where('company_id', $company->id);
            if ($filters['family_id'] ?? null) {
                $products->whereIn('family_id', ProductFamily::where('company_id', $company->id)->where('public_id', $filters['family_id'])->select('id'));
            }
            if ($filters['type'] ?? null) {
                $products->where('type', $filters['type']);
            }
            if ($resource === 'product-variants') {
                $query->whereIn('product_id', $products->select('id'));
            } else {
                $variants = ProductVariant::where('company_id', $company->id)->whereIn('product_id', $products->select('id'));
                if ($filters['variant_id'] ?? null) {
                    $variants->where('public_id', $filters['variant_id']);
                }
                $query->whereIn('variant_id', $variants->select('id'));
            }
        }
        $page = $query->orderBy('name')->orderBy('id')->paginate($filters['per_page'] ?? 20);
        $this->prime($company, $resource, $page->getCollection());
        if ($resource === 'commercial-skus') {
            $barcodes = ProductBarcode::where('company_id', $company->id)->whereIn('sku_id', $page->getCollection()->modelKeys())->get()->keyBy('sku_id');
            foreach ($page as $sku) {
                $sku->setRelation('internal_barcode', $barcodes->get($sku->id));
            }
        }
        $page->setCollection($page->getCollection()->map(fn ($record) => $this->resource($resource, $record)));

        return $page;
    }

    private function prime(Company $company, string $resource, Collection $records, int $depth = 0): void
    {
        if ($depth > self::MAX_FAMILY_LEVELS + 2 || $records->isEmpty()) {
            return;
        }
        foreach (CatalogDefinition::REFERENCES[$resource] ?? [] as $field => $class) {
            $parents = $class::where('company_id', $company->id)->whereIn('id', $records->pluck($field)->filter()->unique())->get();
            $this->prime($company, array_search($class, CatalogDefinition::MODELS, true), $parents, $depth + 1);
            $map = $parents->keyBy('id');
            foreach ($records as $record) {
                $record->setRelation($field, $map->get($record->getAttribute($field)));
            }
        }
    }

    private function parent(ScopedRecord $record, string $field, string $class): ?ScopedRecord
    {
        if (! $record->getAttribute($field)) {
            return null;
        }

        return $record->relationLoaded($field) ? $record->getRelation($field) : $class::where('company_id', $record->company_id)->whereKey($record->getAttribute($field))->first();
    }

    public function available(string $resource, ScopedRecord $record, array $visited = []): bool
    {
        if (! $record->active || isset($visited[$resource][$record->id])) {
            return false;
        }
        $visited[$resource][$record->id] = true;
        if (count($visited['product-families'] ?? []) > self::MAX_FAMILY_LEVELS) {
            return false;
        }
        foreach (CatalogDefinition::REFERENCES[$resource] ?? [] as $field => $class) {
            $parent = $this->parent($record, $field, $class);
            if ($record->getAttribute($field) && (! $parent || ! $this->available(array_search($class, CatalogDefinition::MODELS, true), $parent, $visited))) {
                return false;
            }
        }

        return true;
    }

    public function resource(string $resource, ScopedRecord $record): array
    {
        $data = ['public_id' => $record->public_id, ...$record->only(CatalogDefinition::FIELDS[$resource]), 'effective_active' => $this->available($resource, $record)];
        foreach (CatalogDefinition::REFERENCES[$resource] ?? [] as $field => $class) {
            $parent = $this->parent($record, $field, $class);
            $data[$field] = $parent?->public_id;
            $data[$field.'_label'] = $parent?->name;
        }
        if ($resource === 'product-variants') {
            $data['attributes'] = (object) ($record->getAttribute('attributes') ?? []);
            $data['identity_locked'] = (bool) ($record->getAttribute('skus_exists') ?? CommercialSku::where('company_id', $record->company_id)->where('variant_id', $record->id)->exists());
        }
        if ($resource === 'commercial-skus') {
            $barcode = $record->relationLoaded('internal_barcode') ? $record->getRelation('internal_barcode') : ProductBarcode::where('company_id', $record->company_id)->where('sku_id', $record->id)->first();
            $data['barcode'] = $barcode?->only(['public_id', 'code', 'kind']);
        }
        $data['created_at'] = $record->created_at?->toISOString();

        return $data;
    }

    public function save(User $actor, Company $company, string $resource, array $input, ?string $publicId = null): ScopedRecord
    {
        Gate::forUser($actor)->authorize('catalog.manage', [$company, $resource]);
        try {
            return DB::transaction(function () use ($actor, $company, $resource, $input, $publicId) {
                $company = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('catalog.manage', [$company, $resource]);
                if ($resource === 'commercial-skus') {
                    $this->codes->lock();
                }
                $class = CatalogDefinition::MODELS[$resource];
                $record = $publicId ? $this->find($company, $resource, $publicId, true) : new $class;
                $before = $record->exists ? $this->resource($resource, $record) : null;
                $generateBarcode = $input['generate_internal_barcode'] ?? true;
                unset($input['generate_internal_barcode']);
                foreach (CatalogDefinition::REFERENCES[$resource] ?? [] as $field => $parentClass) {
                    if (array_key_exists($field, $input)) {
                        $parent = $input[$field] ? $parentClass::where('company_id', $company->id)->where('public_id', $input[$field])->lockForUpdate()->firstOrFail() : null;
                        if ($parent && (! $record->exists || $record->getAttribute($field) !== $parent->id) && ! $this->available(array_search($parentClass, CatalogDefinition::MODELS, true), $parent)) {
                            throw ValidationException::withMessages([$field => 'Escolha um cadastro ativo, com seus cadastros superiores também ativos.']);
                        }
                        $input[$field] = $parent?->id;
                    }
                }
                $record->fill($input);
                $record->forceFill(['company_id' => $company->id]);
                $this->validateDomain($company, $resource, $record);
                if ($resource === 'commercial-skus' && ! $record->exists) {
                    if (! $record->sku) {
                        do {
                            $record->sku = $this->codes->next();
                        } while (CodeReservation::where('company_id', $company->id)->where('namespace', 'sku')->where('code', $record->sku)->exists());
                    }
                    if (CodeReservation::where('company_id', $company->id)->where('namespace', 'sku')->where('code', $record->sku)->exists()) {
                        throw ValidationException::withMessages(['sku' => 'Este SKU já está reservado, inclusive se estiver desativado.']);
                    }
                }
                $creating = ! $record->exists;
                $record->save();
                if ($resource === 'commercial-skus' && $creating) {
                    $this->codes->reserve($company, $record, 'sku', $record->sku);
                    if ($generateBarcode) {
                        $this->barcode($actor, $company, $record, true);
                    }
                }
                $record->unsetRelations();
                self::audit($actor, $company, $resource, $record->public_id, $creating ? 'created' : 'updated', $before, $this->resource($resource, $record));

                return $record;
            }, 5);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([$resource === 'commercial-skus' ? 'sku' : 'code' => 'Identificador já reservado. Não reutilize códigos de cadastros desativados.']);
        }
    }

    private function validateDomain(Company $company, string $resource, ScopedRecord $record): void
    {
        if ($resource === 'units') {
            $expected = ['g' => ['mass', '1'], 'KG' => ['mass', '1000'], 'UN' => ['count', '1'], 'PCT' => ['package', null], 'CX' => ['package', null]];
            if (isset($expected[$record->code]) && ($record->dimension !== $expected[$record->code][0] || ($expected[$record->code][1] !== null && bccomp($record->ratio_to_base ?? '0', $expected[$record->code][1], 6) !== 0))) {
                throw ValidationException::withMessages(['ratio_to_base' => 'A unidade padrão deve manter sua dimensão e fator de base.']);
            }
            if (($record->dimension === 'package') !== ($record->ratio_to_base === null)) {
                throw ValidationException::withMessages(['ratio_to_base' => 'Embalagens não possuem conversão global. Massa/contagem exigem fator positivo.']);
            }
            if ($record->exists && $record->isDirty(['code', 'dimension', 'ratio_to_base'])) {
                throw ValidationException::withMessages(['code' => 'Código, dimensão e fator são imutáveis. Crie outra unidade.']);
            }
        }
        if ($resource === 'product-families') {
            $parentId = $record->parent_id;
            $visited = $record->exists ? [$record->id] : [];
            $levels = 1;
            while ($parentId) {
                if (in_array($parentId, $visited, true) || ++$levels > self::MAX_FAMILY_LEVELS) {
                    throw ValidationException::withMessages(['parent_id' => 'A hierarquia não pode conter ciclos nem exceder 20 níveis.']);
                }
                $visited[] = $parentId;
                $parentId = ProductFamily::where('company_id', $company->id)->whereKey($parentId)->value('parent_id');
            }
            if ($record->exists && $record->isDirty('parent_id')) {
                $frontier = [$record->id];
                $descendants = [$record->id];
                while ($frontier) {
                    $frontier = ProductFamily::where('company_id', $company->id)->whereIn('parent_id', $frontier)->pluck('id')->all();
                    if ($frontier && (++$levels > self::MAX_FAMILY_LEVELS || array_intersect($descendants, $frontier))) {
                        throw ValidationException::withMessages(['parent_id' => 'A hierarquia não pode conter ciclos nem exceder 20 níveis, incluindo as famílias descendentes.']);
                    }
                    $descendants = [...$descendants, ...$frontier];
                }
            }
        }
        if ($resource === 'product-variants') {
            foreach (array_keys($record->getAttribute('attributes') ?? []) as $key) {
                if (! is_string($key) || ! preg_match('/^[\pL][\pL\pN _-]{0,39}$/u', $key)) {
                    throw ValidationException::withMessages(['attributes' => 'Use nomes de atributos com até 40 caracteres, iniciados por letra.']);
                }
            }
            if ($record->exists && $record->isDirty(['product_id', 'flavor', 'nominal_unit_weight_g', 'attributes']) && CommercialSku::where('company_id', $company->id)->where('variant_id', $record->id)->exists()) {
                throw ValidationException::withMessages(['name' => 'Esta variante já possui SKUs. Para mudar sabor, peso ou características, crie outra variante.']);
            }
        }
        if ($resource === 'commercial-skus') {
            if ($record->exists && $record->isDirty(['sku', 'variant_id', 'unit_id', 'packaging_qty', 'nominal_net_weight_g', 'variable_weight'])) {
                throw ValidationException::withMessages(['sku' => 'A identidade e a apresentação são imutáveis. Crie outro SKU para a nova apresentação.']);
            }
            $unit = Unit::where('company_id', $company->id)->whereKey($record->unit_id)->firstOrFail();
            if ($unit->dimension === 'package' && ! $record->packaging_qty) {
                throw ValidationException::withMessages(['packaging_qty' => 'Informe o conteúdo desta embalagem em unidades.']);
            }
            if ($unit->dimension === 'mass' && ! $record->variable_weight) {
                throw ValidationException::withMessages(['variable_weight' => 'A venda por massa exige peso variável e pesagem real nas fases operacionais.']);
            }
            if ($record->variable_weight && $record->nominal_net_weight_g !== null) {
                throw ValidationException::withMessages(['nominal_net_weight_g' => 'Peso variável não pode ter peso líquido fixado por SKU.']);
            }
        }
    }

    public function barcode(User $actor, Company $company, CommercialSku $sku, bool $initializing = false): ProductBarcode
    {
        Gate::forUser($actor)->authorize('catalog.manage', [$company, 'commercial-skus']);

        return DB::transaction(function () use ($actor, $company, $sku, $initializing) {
            $company = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('catalog.manage', [$company, 'commercial-skus']);
            $this->codes->lock();
            $sku = CommercialSku::where('company_id', $company->id)->whereKey($sku->id)->lockForUpdate()->firstOrFail();
            $barcode = ProductBarcode::where('company_id', $company->id)->where('sku_id', $sku->id)->first();
            if ($barcode) {
                return $barcode; // Reserva idempotente por SKU; nunca emite outro código.
            }
            if (! $initializing && ! $this->available('commercial-skus', $sku)) {
                throw ValidationException::withMessages(['sku_id' => 'Não é possível emitir novo código para um SKU indisponível.']);
            }
            $barcode = new ProductBarcode;
            $barcode->forceFill(['company_id' => $company->id, 'sku_id' => $sku->id, 'code' => $this->codes->next(), 'kind' => 'internal_code128'])->save();
            $this->codes->reserve($company, $sku, 'internal_code128', $barcode->code);
            self::audit($actor, $company, 'barcodes', $barcode->public_id, 'reserved', null, $barcode->only(['public_id', 'code', 'kind']));

            return $barcode;
        }, 5);
    }

    public static function audit(User $actor, Company $company, string $resource, string $publicId, string $action, ?array $before, array $after): void
    {
        $event = new CatalogEvent;
        $event->forceFill(['company_id' => $company->id, 'actor_id' => $actor->id, 'resource' => $resource, 'resource_public_id' => $publicId, 'action' => $action, 'before' => $before, 'after' => $after])->save();
    }
}
