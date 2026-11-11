<?php

namespace App\Domain\Inventory;

use App\Domain\Catalog\ProductVariant;
use App\Domain\Catalog\Unit;
use App\Domain\Catalog\Warehouse;
use App\Domain\Purchasing\PurchaseOrder;
use App\Domain\Purchasing\PurchaseOrderItem;
use App\Domain\Purchasing\PurchaseReceipt;
use App\Domain\Purchasing\Supplier;
use App\Models\Company;
use App\Models\User;

class InventoryRead
{
    public const MODELS = ['suppliers' => Supplier::class, 'orders' => PurchaseOrder::class, 'order-items' => PurchaseOrderItem::class, 'receipts' => PurchaseReceipt::class, 'policies' => InventoryPolicy::class, 'locations' => Location::class, 'lots' => InventoryLot::class, 'positions' => InventoryBalance::class, 'reservations' => InventoryReservation::class, 'allocations' => InventoryAllocation::class, 'movements' => InventoryMovement::class, 'weighings' => InventoryWeighing::class, 'counts' => InventoryCount::class, 'operations' => InventoryOperation::class, 'events' => InventoryEvent::class];

    public const FIELDS = [
        'suppliers' => ['name', 'tax_id', 'email', 'phone', 'active'], 'orders' => ['reference', 'expected_on', 'status'],
        'order-items' => ['quantity', 'received_quantity', 'unit_cost', 'total_cost'], 'receipts' => ['received_on', 'quantity', 'unit_cost', 'total_cost'],
        'policies' => ['mode', 'requires_expiry', 'active'], 'locations' => ['name', 'code', 'active'],
        'lots' => ['code', 'supplier_lot_code', 'manufactured_on', 'expires_on', 'no_expiry_reason', 'quality_status'],
        'positions' => ['units', 'grams', 'reserved_units', 'reserved_grams'], 'reservations' => ['status'], 'allocations' => ['units', 'grams'],
        'movements' => ['units_delta', 'grams_delta'], 'weighings' => ['gross_g', 'tare_g', 'net_g'], 'counts' => ['expected_units', 'expected_grams', 'actual_units', 'actual_grams'],
        'operations' => ['kind', 'reason'], 'events' => ['resource', 'resource_public_id', 'before', 'after', 'reason'],
    ];

    public const REFS = [
        'orders' => ['supplier_id' => Supplier::class, 'operation_id' => InventoryOperation::class],
        'order-items' => ['order_id' => PurchaseOrder::class, 'variant_id' => ProductVariant::class, 'unit_id' => Unit::class, 'policy_id' => InventoryPolicy::class],
        'receipts' => ['operation_id' => InventoryOperation::class, 'order_item_id' => PurchaseOrderItem::class, 'balance_id' => InventoryBalance::class],
        'policies' => ['variant_id' => ProductVariant::class], 'locations' => ['warehouse_id' => Warehouse::class],
        'lots' => ['variant_id' => ProductVariant::class, 'policy_id' => InventoryPolicy::class, 'supplier_id' => Supplier::class],
        'positions' => ['lot_id' => InventoryLot::class, 'location_id' => Location::class],
        'reservations' => ['operation_id' => InventoryOperation::class, 'variant_id' => ProductVariant::class, 'warehouse_id' => Warehouse::class],
        'allocations' => ['reservation_id' => InventoryReservation::class, 'balance_id' => InventoryBalance::class],
        'movements' => ['operation_id' => InventoryOperation::class, 'balance_id' => InventoryBalance::class],
        'weighings' => ['operation_id' => InventoryOperation::class, 'balance_id' => InventoryBalance::class],
        'counts' => ['operation_id' => InventoryOperation::class, 'balance_id' => InventoryBalance::class],
        'operations' => ['reversal_of_id' => InventoryOperation::class], 'events' => ['operation_id' => InventoryOperation::class],
    ];

    private array $cache = [];

    public static function permission(string $resource): string
    {
        return in_array($resource, ['suppliers', 'orders', 'order-items', 'receipts'], true) ? 'purchasing.view' : ($resource === 'events' ? 'inventory.audit' : 'inventory.view');
    }

    public function authorizeRecord(User $actor, Company $company, string $resource, $record): void
    {
        if ($resource === 'events') {
            $allowed = ['locations', 'policies', 'lots', 'reservations'];
            if (in_array($record->resource, ['suppliers', 'purchase-orders'], true)) {
                InventoryAccess::authorize($actor, $company, 'purchasing.view');
            } elseif (! in_array($record->resource, $allowed, true)) {
                abort(403);
            }
        }
        if ($resource === 'operations' && in_array($record->kind, ['order', 'cancel-order', 'receive'], true)) {
            InventoryAccess::authorize($actor, $company, 'purchasing.view');
        }
    }

    private function reference(string $class, mixed $id, Company $company): mixed
    {
        if (! $id) {
            return null;
        }

        return $this->cache[$company->id][$class][$id] ??= $class::where('company_id', $company->id)->whereKey($id)->firstOrFail();
    }

    private function label($row): string
    {
        if ($row instanceof InventoryOperation) {
            return ['order' => 'Compra', 'cancel-order' => 'Cancelamento de compra', 'receive' => 'Recebimento', 'reserve' => 'Reserva', 'consume' => 'Consumo de reserva', 'cancel-reservation' => 'Cancelamento de reserva', 'issue' => 'Saída', 'transfer' => 'Transferência', 'count' => 'Contagem', 'weigh' => 'Pesagem', 'quality' => 'Estado do lote', 'reverse' => 'Estorno'][$row->kind] ?? $row->kind;
        }

        return $row->name ?? $row->code ?? $row->reference ?? $row->kind ?? $row->public_id;
    }

    public function resource(User $actor, Company $company, string $resource, $record): array
    {
        $raw = $record->attributesToArray();
        $data = ['public_id' => $record->public_id, ...array_intersect_key($raw, array_flip(self::FIELDS[$resource])), 'created_at' => $record->created_at?->toISOString()];
        foreach (self::REFS[$resource] ?? [] as $field => $class) {
            $parent = $this->reference($class, $record->$field, $company);
            $data[$field] = $parent?->public_id;
            $data[$field.'_label'] = $parent ? $this->label($parent) : null;
        }
        if (! InventoryAccess::allowed($actor, $company, 'purchasing.costs')) {
            unset($data['unit_cost'], $data['total_cost']);
        }
        $data['name'] ??= $this->label($record);
        if ($resource === 'policies') {
            $data['name'] = $data['variant_id_label'].' · '.['mass' => 'Massa real (g)', 'count' => 'Contagem (UN)', 'dual' => 'UN e massa real'][$record->mode];
            $data['identity_locked'] = $record->identityLocked();
        }
        if ($resource === 'order-items') {
            $data['remaining_quantity'] = bcsub($record->quantity, $record->received_quantity, 6);
            $data['name'] = $data['variant_id_label'].' · restante '.$data['remaining_quantity'].' '.$data['unit_id_label'];
            $policy = $this->reference(InventoryPolicy::class, $record->policy_id, $company);
            $data['mode'] = $policy->mode;
        }
        if ($resource === 'lots') {
            $data['expired'] = $record->expires_on && $record->expires_on->toDateString() < now($company->timezone)->toDateString();
        }
        if (in_array($resource, ['positions', 'allocations', 'movements', 'weighings', 'counts', 'receipts'], true)) {
            $balance = $resource === 'positions' ? $record : $this->reference(InventoryBalance::class, $record->balance_id, $company);
            $lot = $this->reference(InventoryLot::class, $balance->lot_id, $company);
            $location = $this->reference(Location::class, $balance->location_id, $company);
            $variant = $this->reference(ProductVariant::class, $lot->variant_id, $company);
            $policy = $this->reference(InventoryPolicy::class, $lot->policy_id, $company);
            $data += ['lot_id' => $lot->public_id, 'lot_code' => $lot->code, 'variant_id' => $variant->public_id, 'variant_name' => $variant->name, 'location_id' => $location->public_id, 'location_name' => $location->name, 'mode' => $policy->mode, 'quality_status' => $lot->quality_status, 'expires_on' => $lot->expires_on?->toDateString()];
            $data['expired'] = $lot->expires_on && $lot->expires_on->toDateString() < now($company->timezone)->toDateString();
            $data['name'] = $variant->name.' · '.$lot->code.' · '.$location->name;
            if ($resource === 'positions') {
                $data['available_units'] = bcsub($record->units, $record->reserved_units, 6);
                $data['available_grams'] = bcsub($record->grams, $record->reserved_grams, 6);
                $data['kilograms'] = bcdiv($record->grams, '1000', 9);
                $data['available_kilograms'] = bcdiv($data['available_grams'], '1000', 9);
                $data['name'] .= ' · sem reserva '.$data['available_units'].' UN / '.$data['available_grams'].' g';
            }
        }
        if ($resource === 'orders') {
            $data['name'] = 'Compra '.$record->public_id.' · '.$data['supplier_id_label'];
        }
        if ($resource === 'operations') {
            $label = $this->label($record);
            $data['name'] = $label.' · '.$record->public_id;
            foreach (['orders' => PurchaseOrder::class, 'receipts' => PurchaseReceipt::class, 'reservations' => InventoryReservation::class] as $key => $class) {
                $data[$key] = $class::where('company_id', $company->id)->where('operation_id', $record->id)->pluck('public_id')->all();
            }
            $data['reversed_by'] = InventoryOperation::where('company_id', $company->id)->where('reversal_of_id', $record->id)->value('public_id');
        }
        if (isset($record->actor_id)) {
            $data['actor'] = User::whereKey($record->actor_id)->first(['public_id', 'name'])?->only(['public_id', 'name']);
        } elseif (isset($record->operation_id)) {
            $operation = $this->reference(InventoryOperation::class, $record->operation_id, $company);
            $data['actor'] = User::whereKey($operation->actor_id)->first(['public_id', 'name'])?->only(['public_id', 'name']);
        }

        return $data;
    }

    public function listing(User $actor, Company $company, string $resource, array $filters)
    {
        InventoryAccess::authorize($actor, $company, self::permission($resource));
        $class = self::MODELS[$resource];
        $query = $class::where('company_id', $company->id);
        if ($resource === 'events') {
            $allowed = ['locations', 'policies', 'lots', 'reservations'];
            if (InventoryAccess::allowed($actor, $company, 'purchasing.view')) {
                $allowed = [...$allowed, 'suppliers', 'purchase-orders'];
            }
            $query->whereIn('resource', $allowed);
        }
        if ($resource === 'operations' && ! InventoryAccess::allowed($actor, $company, 'purchasing.view')) {
            $query->whereNotIn('kind', ['order', 'cancel-order', 'receive']);
        }
        if ($resource === 'positions') {
            if (! empty($filters['warehouse_id'])) {
                $query->whereIn('location_id', Location::where('company_id', $company->id)->whereIn('warehouse_id', Warehouse::where('company_id', $company->id)->where('public_id', $filters['warehouse_id'])->select('id'))->select('id'));
            }
            if (! empty($filters['variant_id']) || ! empty($filters['quality_status'])) {
                $lots = InventoryLot::where('company_id', $company->id);
                if (! empty($filters['variant_id'])) {
                    $lots->whereIn('variant_id', ProductVariant::where('company_id', $company->id)->where('public_id', $filters['variant_id'])->select('id'));
                }
                if (! empty($filters['quality_status'])) {
                    $lots->where('quality_status', $filters['quality_status']);
                }
                $query->whereIn('lot_id', $lots->select('id'));
            }
        }
        if ($resource === 'order-items' && ! empty($filters['receivable'])) {
            $query->whereColumn('received_quantity', '<', 'quantity')->whereIn('order_id', PurchaseOrder::where('company_id', $company->id)->where('status', '!=', 'cancelled')->select('id'));
        }
        if (isset($filters['active']) && in_array($resource, ['suppliers', 'locations', 'policies'], true)) {
            $query->where('active', $filters['active']);
        }
        if (! empty($filters['q'])) {
            $field = match ($resource) {
                'suppliers','locations' => 'name', 'lots' => 'code', 'operations' => 'reason', default => 'public_id'
            };
            $term = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($field, $term, $resource, $company) {
                $q->where($field, 'like', $term);
                if ($field !== 'public_id') {
                    $q->orWhere('public_id', 'like', $term);
                }
                if (isset(self::REFS[$resource]['variant_id'])) {
                    $q->orWhereIn('variant_id', ProductVariant::where('company_id', $company->id)->where('name', 'like', $term)->select('id'));
                }
                if ($resource === 'positions') {
                    $q->orWhereIn('lot_id', InventoryLot::where('company_id', $company->id)->where(fn ($lots) => $lots->where('code', 'like', $term)->orWhereIn('variant_id', ProductVariant::where('company_id', $company->id)->where('name', 'like', $term)->select('id')))->select('id'));
                }
                if ($resource === 'orders') {
                    $q->orWhere('reference', 'like', $term)->orWhereIn('supplier_id', Supplier::where('company_id', $company->id)->where('name', 'like', $term)->select('id'));
                }
            });
        }
        foreach (self::REFS[$resource] ?? [] as $field => $parentClass) {
            if (! empty($filters[$field])) {
                $query->whereIn($field, $parentClass::where('company_id', $company->id)->where('public_id', $filters[$field])->select('id'));
            }
        }
        if (! empty($filters['status']) && in_array($resource, ['orders', 'reservations'], true)) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['quality_status']) && $resource === 'lots') {
            $query->where('quality_status', $filters['quality_status']);
        }
        if (! empty($filters['expires_before']) && $resource === 'lots') {
            $query->whereNotNull('expires_on')->where('expires_on', '<=', $filters['expires_before'])->orderBy('expires_on');
        }
        if (! empty($filters['resource_id']) && $resource === 'events') {
            $query->where('resource_public_id', $filters['resource_id']);
        }
        $page = $query->orderByDesc('id')->paginate($filters['per_page'] ?? 20);
        // Carrega referências diretas em lote; referências transitivas usam cache desta consulta.
        foreach (self::REFS[$resource] ?? [] as $field => $parentClass) {
            foreach ($parentClass::where('company_id', $company->id)->whereIn('id', $page->getCollection()->pluck($field)->filter()->unique())->get() as $parent) {
                $this->cache[$company->id][$parentClass][$parent->id] = $parent;
            }
        }
        $page->setCollection($page->getCollection()->map(fn ($row) => $this->resource($actor, $company, $resource, $row)));

        return $page;
    }
}
