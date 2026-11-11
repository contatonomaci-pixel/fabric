<?php

namespace App\Domain\Inventory;

use App\Domain\Catalog\CatalogService;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class InventoryDirectory
{
    public function save(User $actor, Company $company, string $resource, array $input, ?string $id = null): Record
    {
        $permission = $resource === 'suppliers' ? 'purchasing.manage' : 'inventory.configure';
        InventoryAccess::authorize($actor, $company, $permission);
        try {
            return DB::transaction(function () use ($actor, $company, $resource, $input, $id, $permission) {
                $company = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
                InventoryAccess::authorize($actor, $company, $permission);
                $class = InventoryRead::MODELS[$resource];
                $record = $id ? $class::where('company_id', $company->id)->where('public_id', $id)->lockForUpdate()->firstOrFail() : new $class;
                $before = $record->exists ? $record->only(InventoryRead::FIELDS[$resource]) : null;
                $reason = $input['reason'];
                unset($input['reason']);
                foreach (InventoryRead::REFS[$resource] ?? [] as $field => $parentClass) {
                    if (array_key_exists($field, $input)) {
                        $parent = $parentClass::where('company_id', $company->id)->where('public_id', $input[$field])->firstOrFail();
                        if ($record->exists && $parent->id !== $record->$field) {
                            Decimal::fail($field, 'Vínculo do cadastro é imutável; crie outro cadastro.');
                        }
                        if (! $record->exists && ! app(CatalogService::class)->available($resource === 'locations' ? 'warehouses' : 'product-variants', $parent)) {
                            Decimal::fail($field, 'Cadastro superior indisponível.');
                        }
                        $input[$field] = $parent->id;
                    }
                }
                $record->fill($input);
                if ($resource === 'locations' && $record->exists && $record->isDirty('code')) {
                    Decimal::fail('code', 'Código do local é preservado; cadastre outro local para outro identificador.');
                }
                if ($resource === 'policies' && $record->exists && $record->isDirty(['mode', 'requires_expiry']) && $record->identityLocked()) {
                    Decimal::fail('mode', 'A política já possui pedido emitido ou lote; dimensão e regra de validade não podem ser reescritas.');
                }
                $record->forceFill(['company_id' => $company->id])->save();
                $event = new InventoryEvent;
                $event->forceFill(['company_id' => $company->id, 'actor_id' => $actor->id, 'resource' => $resource, 'resource_public_id' => $record->public_id, 'before' => $before, 'after' => $record->only(InventoryRead::FIELDS[$resource]), 'reason' => $reason])->save();

                return $record->fresh();
            }, 5);
        } catch (UniqueConstraintViolationException $e) {
            Decimal::fail('name', 'Identificador ou política já cadastrado, inclusive desativado.');
        }
    }
}
