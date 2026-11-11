<?php

namespace App\Domain\Inventory;

use App\Domain\Catalog\CatalogService;
use App\Domain\Catalog\ProductVariant;
use App\Domain\Catalog\ScopedRecord;
use App\Domain\Catalog\Unit;
use App\Domain\Catalog\Warehouse;
use App\Domain\Purchasing\PurchaseOrder;
use App\Domain\Purchasing\PurchaseOrderItem;
use App\Domain\Purchasing\PurchaseReceipt;
use App\Domain\Purchasing\Supplier;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryService
{
    private Company $company;

    private User $actor;

    public function execute(User $actor, Company $company, string $kind, array $input): InventoryOperation
    {
        $permission = InventoryAccess::COMMANDS[$kind];
        InventoryAccess::authorize($actor, $company, $permission);

        return DB::transaction(function () use ($actor, $company, $kind, $input, $permission) {
            $this->company = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            $this->actor = $actor;
            InventoryAccess::authorize($actor, $this->company, $permission);
            $key = strtolower($input['idempotency_key']);
            unset($input['idempotency_key']);
            $hash = hash('sha256', json_encode([$kind, $this->canonical($input)], JSON_THROW_ON_ERROR));
            $previous = InventoryOperation::where('company_id', $company->id)->where('idempotency_key', $key)->first();
            if ($previous) {
                if ($previous->kind !== $kind || ! hash_equals($previous->request_hash, $hash)) {
                    Decimal::fail('idempotency_key', 'Esta chave já foi usada com outra solicitação.');
                }

                return $previous;
            }
            $original = $kind === 'reverse' ? $this->one(InventoryOperation::class, $input['operation_id'] ?? null, 'operation_id') : null;
            if ($original && ($original->reversal_of_id || InventoryOperation::where('company_id', $company->id)->where('reversal_of_id', $original->id)->exists())) {
                Decimal::fail('operation_id', 'A operação já foi estornada ou é um estorno.');
            }
            $op = new InventoryOperation;
            $op->forceFill(['company_id' => $company->id, 'actor_id' => $actor->id, 'kind' => $kind, 'reason' => $input['reason'], 'idempotency_key' => $key, 'request_hash' => $hash, 'reversal_of_id' => $original?->id])->save();
            match ($kind) {
                'order' => $this->order($op, $input),
                'cancel-order' => $this->cancelOrder($op, $input),
                'receive' => $this->receive($op, $input),
                'reserve' => $this->reserve($op, $input),
                'consume', 'cancel-reservation' => $this->finishReservation($op, $input),
                'issue', 'transfer', 'count', 'weigh' => $this->physical($op, $input),
                'quality' => $this->quality($op, $input),
                'reverse' => $this->reverse($op, $original),
            };

            return $op;
        }, 5);
    }

    private function canonical(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(fn ($v) => $this->canonical($v), $value);
        }

        return $value;
    }

    private function one(string $class, mixed $id, string $field): ScopedRecord
    {
        if (! is_string($id) || $id === '') {
            Decimal::fail($field, 'Selecione um cadastro.');
        }

        return $class::where('company_id', $this->company->id)->where('public_id', $id)->lockForUpdate()->firstOrFail();
    }

    private function owned(string $class, int $id): mixed
    {
        return $class::where('company_id', $this->company->id)->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function create(string $class, array $data): mixed
    {
        $record = new $class;
        $record->forceFill(['company_id' => $this->company->id, ...$data])->save();

        return $record;
    }

    private function today(): string
    {
        return now($this->company->timezone)->toDateString();
    }

    private function policy(int $variantId): InventoryPolicy
    {
        $policy = InventoryPolicy::where('company_id', $this->company->id)->where('variant_id', $variantId)->where('active', true)->first();
        if (! $policy) {
            Decimal::fail('variant_id', 'Configure e ative a política de estoque desta variante antes de operar.');
        }
        $variant = $this->owned(ProductVariant::class, $variantId);
        if (! app(CatalogService::class)->available('product-variants', $variant)) {
            Decimal::fail('variant_id', 'A variante ou seus cadastros superiores estão indisponíveis.');
        }

        return $policy;
    }

    private function activeLocation(Location $location): void
    {
        $warehouse = $this->owned(Warehouse::class, $location->warehouse_id);
        if (! $location->active || ! app(CatalogService::class)->available('warehouses', $warehouse)) {
            Decimal::fail('location_id', 'Local, depósito ou fábrica indisponível.');
        }
    }

    private function eligible(InventoryBalance $balance): InventoryPolicy
    {
        $lot = $this->owned(InventoryLot::class, $balance->lot_id);
        $policy = $this->policy($lot->variant_id);
        $this->activeLocation($this->owned(Location::class, $balance->location_id));
        if ($lot->quality_status !== 'released' || ($lot->expires_on && $lot->expires_on->toDateString() < $this->today()) || (! $lot->expires_on && ($policy->requires_expiry || ! $lot->no_expiry_reason))) {
            Decimal::fail('lot_id', 'Lote vencido, bloqueado, em quarentena ou sem declaração de validade aplicável.');
        }

        return $policy;
    }

    private function position(InventoryLot $lot, Location $location): InventoryBalance
    {
        return InventoryBalance::where('company_id', $this->company->id)->where('lot_id', $lot->id)->where('location_id', $location->id)->lockForUpdate()->first()
            ?? $this->create(InventoryBalance::class, ['lot_id' => $lot->id, 'location_id' => $location->id, 'units' => '0', 'grams' => '0', 'reserved_units' => '0', 'reserved_grams' => '0']);
    }

    private function measurement(InventoryOperation $op, InventoryBalance $balance, array $input, InventoryPolicy $policy, bool $zero = false): array
    {
        $tracksUnits = $policy->mode !== 'mass' && $op->kind !== 'weigh';
        $units = $tracksUnits ? Decimal::quantity($input['units'] ?? null, 'units', ! $zero) : '0.000000';
        if (! $tracksUnits && isset($input['units']) && bccomp(Decimal::quantity($input['units'], 'units', false), '0', 6) !== 0) {
            Decimal::fail('units', 'A política acompanha somente massa.');
        }
        $grams = '0.000000';
        if ($policy->mode !== 'count') {
            $gross = Decimal::quantity($input['gross_g'] ?? null, 'gross_g', false);
            $tare = Decimal::quantity($input['tare_g'] ?? null, 'tare_g', false);
            $grams = bcsub($gross, $tare, 6);
            if (bccomp($grams, '0', 6) < ($zero ? 0 : 1)) {
                Decimal::fail('tare_g', 'Tara não pode exceder o bruto; o líquido deve ser positivo nesta operação.');
            }
            $this->create(InventoryWeighing::class, ['operation_id' => $op->id, 'balance_id' => $balance->id, 'gross_g' => $gross, 'tare_g' => $tare, 'net_g' => $grams]);
        } elseif (isset($input['gross_g']) || isset($input['tare_g'])) {
            Decimal::fail('gross_g', 'Esta política acompanha somente contagem. Não misture massa nominal com saldo.');
        }

        return [$units, $grams];
    }

    private function move(InventoryOperation $op, InventoryBalance $balance, string $units, string $grams): void
    {
        $next = [];
        foreach (['units' => $units, 'grams' => $grams] as $field => $delta) {
            $next[$field] = Decimal::bounded(bcadd($balance->$field, $delta, 6), $field);
            if (bccomp($next[$field], $balance->{'reserved_'.$field}, 6) < 0) {
                Decimal::fail($field, 'Saldo disponível insuficiente; há quantidade reservada.');
            }
        }
        $balance->forceFill($next)->save();
        if (bccomp($units, '0', 6) || bccomp($grams, '0', 6)) {
            $this->create(InventoryMovement::class, ['operation_id' => $op->id, 'balance_id' => $balance->id, 'units_delta' => $units, 'grams_delta' => $grams]);
        }
    }

    private function hold(InventoryBalance $balance, string $units, string $grams): void
    {
        foreach (['units' => $units, 'grams' => $grams] as $field => $delta) {
            $amount = Decimal::bounded(bcadd($balance->{'reserved_'.$field}, $delta, 6), $field);
            if (bccomp($amount, $balance->$field, 6) > 0) {
                Decimal::fail($field, 'Saldo disponível insuficiente para reservar.');
            }
            $balance->{'reserved_'.$field} = $amount;
        }
        $balance->save();
    }

    private function audit(InventoryOperation $op, Record $record, string $resource, ?array $before, array $after): void
    {
        $this->create(InventoryEvent::class, ['operation_id' => $op->id, 'actor_id' => $this->actor->id, 'resource' => $resource, 'resource_public_id' => $record->public_id, 'before' => $before, 'after' => $after, 'reason' => $op->reason]);
    }

    private function order(InventoryOperation $op, array $input): void
    {
        $supplier = $this->one(Supplier::class, $input['supplier_id'] ?? null, 'supplier_id');
        if (! $supplier->active) {
            Decimal::fail('supplier_id', 'Fornecedor desativado.');
        }
        $order = $this->create(PurchaseOrder::class, ['operation_id' => $op->id, 'supplier_id' => $supplier->id, 'reference' => $input['reference'] ?? null, 'expected_on' => $input['expected_on'] ?? null, 'status' => 'open']);
        if (empty($input['items'])) {
            Decimal::fail('items', 'Inclua pelo menos um item.');
        }
        foreach ($input['items'] as $item) {
            $variant = $this->one(ProductVariant::class, $item['variant_id'] ?? null, 'variant_id');
            $policy = $this->policy($variant->id);
            $unit = $this->one(Unit::class, $item['unit_id'] ?? null, 'unit_id');
            if (! $unit->active || $unit->dimension === 'package' || ($policy->mode !== 'dual' && $unit->dimension !== $policy->mode)) {
                Decimal::fail('unit_id', 'Use unidade ativa de massa/contagem compatível com a política; embalagem não possui conversão global.');
            }
            $quantity = Decimal::quantity($item['quantity'] ?? null, 'quantity');
            Decimal::base($quantity, $unit->ratio_to_base);
            $cost = Decimal::cost($item['unit_cost'] ?? null);
            $this->create(PurchaseOrderItem::class, ['order_id' => $order->id, 'variant_id' => $variant->id, 'policy_id' => $policy->id, 'unit_id' => $unit->id, 'quantity' => $quantity, 'received_quantity' => '0', 'unit_cost' => $cost, 'total_cost' => Decimal::total($quantity, $cost)]);
        }
    }

    private function cancelOrder(InventoryOperation $op, array $input): void
    {
        $order = $this->one(PurchaseOrder::class, $input['order_id'] ?? null, 'order_id');
        if ($order->status === 'cancelled' || PurchaseOrderItem::where('company_id', $this->company->id)->where('order_id', $order->id)->where('received_quantity', '>', 0)->exists()) {
            Decimal::fail('order_id', 'Pedido cancelado ou com recebimento; estorne os recebimentos antes de cancelar.');
        }
        $order->status = 'cancelled';
        $order->save();
        $this->audit($op, $order, 'purchase-orders', ['status' => 'open'], ['status' => 'cancelled']);
    }

    private function receive(InventoryOperation $op, array $input): void
    {
        $item = $this->one(PurchaseOrderItem::class, $input['order_item_id'] ?? null, 'order_item_id');
        $order = $this->owned(PurchaseOrder::class, $item->order_id);
        if ($order->status === 'cancelled') {
            Decimal::fail('order_id', 'Pedido cancelado.');
        }
        $policy = $this->policy($item->variant_id);
        $quantity = Decimal::quantity($input['quantity'] ?? null, 'quantity');
        $nextReceived = bcadd($item->received_quantity, $quantity, 6);
        if (bccomp($nextReceived, $item->quantity, 6) > 0) {
            Decimal::fail('quantity', 'Recebimento excede a quantidade restante do pedido.');
        }
        $location = $this->one(Location::class, $input['location_id'] ?? null, 'location_id');
        $this->activeLocation($location);
        $received = $input['received_on'] ?? null;
        if (! $received || $received > $this->today()) {
            Decimal::fail('received_on', 'Informe a data real de recebimento, sem data futura.');
        }
        $status = $input['quality_status'] ?? 'quarantine';
        if ($status !== 'quarantine') {
            InventoryAccess::authorize($this->actor, $this->company, 'inventory.release');
        }
        $expiry = $input['expires_on'] ?? null;
        $manufactured = $input['manufactured_on'] ?? null;
        if (! $expiry && ($policy->requires_expiry || empty($input['no_expiry_reason']))) {
            Decimal::fail('expires_on', 'Informe a validade declarada ou configure ausência de validade com justificativa explícita.');
        }
        if ($manufactured && ($manufactured > $received || ($expiry && $manufactured > $expiry))) {
            Decimal::fail('manufactured_on', 'Fabricação não pode ser posterior ao recebimento ou à validade.');
        }
        if ($status === 'released' && $expiry && $expiry < $this->today()) {
            Decimal::fail('expires_on', 'Lote vencido não pode ser liberado.');
        }
        $lotData = ['variant_id' => $item->variant_id, 'policy_id' => $policy->id, 'supplier_id' => $order->supplier_id, 'supplier_lot_code' => $input['supplier_lot_code'], 'manufactured_on' => $manufactured, 'expires_on' => $expiry, 'no_expiry_reason' => $expiry ? null : $input['no_expiry_reason'], 'quality_status' => $status];
        if (! empty($input['lot_id'])) {
            $lot = $this->one(InventoryLot::class, $input['lot_id'], 'lot_id');
            foreach ($lotData as $field => $value) {
                if (($lot->attributesToArray()[$field] ?? null) !== $value) {
                    Decimal::fail('lot_id', 'Dados declarados diferem do lote existente; cadastre outro lote.');
                }
            }
        } else {
            if (! empty($input['lot_code']) && InventoryLot::where('company_id', $this->company->id)->where('code', $input['lot_code'])->exists()) {
                Decimal::fail('lot_code', 'Código interno já utilizado. Associe ao lote existente com os mesmos dados ou escolha outro código.');
            }
            $lot = $this->create(InventoryLot::class, [...$lotData, 'code' => $input['lot_code'] ?? 'LOT-'.strtoupper((string) Str::ulid())]);
        }
        $balance = $this->position($lot, $location);
        [$units, $grams] = $this->measurement($op, $balance, $input, $policy);
        $unit = $this->owned(Unit::class, $item->unit_id);
        $base = Decimal::base($quantity, $unit->ratio_to_base);
        if (bccomp($base, $unit->dimension === 'mass' ? $grams : $units, 6) !== 0) {
            Decimal::fail('quantity', 'Quantidade recebida e medição real não correspondem à unidade do pedido.');
        }
        $this->move($op, $balance, $units, $grams);
        $this->create(PurchaseReceipt::class, ['operation_id' => $op->id, 'order_item_id' => $item->id, 'balance_id' => $balance->id, 'received_on' => $received, 'quantity' => $quantity, 'unit_cost' => $item->unit_cost, 'total_cost' => Decimal::total($quantity, $item->unit_cost)]);
        $item->received_quantity = $nextReceived;
        $item->save();
        $this->orderStatus($order);
        $this->audit($op, $lot, 'lots', null, ['quality_status' => $status]);
    }

    private function orderStatus(PurchaseOrder $order): void
    {
        $items = PurchaseOrderItem::where('company_id', $this->company->id)->where('order_id', $order->id)->get();
        $all = $items->every(fn ($i) => bccomp($i->received_quantity, $i->quantity, 6) === 0);
        $any = $items->contains(fn ($i) => bccomp($i->received_quantity, '0', 6) > 0);
        $order->status = $all ? 'received' : ($any ? 'partially_received' : 'open');
        $order->save();
    }

    private function reserve(InventoryOperation $op, array $input): void
    {
        $variant = $this->one(ProductVariant::class, $input['variant_id'] ?? null, 'variant_id');
        $policy = $this->policy($variant->id);
        $warehouse = $this->one(Warehouse::class, $input['warehouse_id'] ?? null, 'warehouse_id');
        if (! app(CatalogService::class)->available('warehouses', $warehouse)) {
            Decimal::fail('warehouse_id', 'Depósito indisponível.');
        }
        $needed = ['units' => $policy->mode !== 'mass' ? Decimal::quantity($input['units'] ?? null, 'units') : '0.000000', 'grams' => $policy->mode !== 'count' ? Decimal::quantity($input['grams'] ?? null, 'grams') : '0.000000'];
        if (($policy->mode === 'mass' && ! empty($input['units']) && bccomp(Decimal::quantity($input['units'], 'units', false), '0', 6)) || ($policy->mode === 'count' && ! empty($input['grams']) && bccomp(Decimal::quantity($input['grams'], 'grams', false), '0', 6))) {
            Decimal::fail('variant_id', 'Quantidades incompatíveis com a política.');
        }
        if ($policy->mode === 'dual' && empty($input['balance_id'])) {
            Decimal::fail('balance_id', 'Na política dupla selecione uma posição e declare as duas quantidades reais, sem rateio teórico de massa.');
        }
        $query = InventoryBalance::where('inventory_balances.company_id', $this->company->id)->whereIn('location_id', Location::where('company_id', $this->company->id)->where('warehouse_id', $warehouse->id)->where('active', true)->select('id'))
            ->join('inventory_lots as lots', function ($join) {
                $join->on('lots.id', '=', 'inventory_balances.lot_id')->on('lots.company_id', '=', 'inventory_balances.company_id');
            })
            ->where('lots.variant_id', $variant->id)->where('lots.quality_status', 'released')
            ->where(fn ($q) => $q->where('lots.expires_on', '>=', $this->today())->orWhere(fn ($q) => $q->whereNull('lots.expires_on')->whereNotNull('lots.no_expiry_reason')))
            ->select('inventory_balances.*')->orderByRaw('lots.expires_on IS NULL')->orderBy('lots.expires_on')->orderBy('lots.id')->orderBy('inventory_balances.id');
        if (! empty($input['balance_id'])) {
            $query->where('inventory_balances.public_id', $input['balance_id']);
        }
        $balances = $query->lockForUpdate()->get();
        $allocationCount = 0;
        $reservation = $this->create(InventoryReservation::class, ['operation_id' => $op->id, 'variant_id' => $variant->id, 'warehouse_id' => $warehouse->id, 'status' => 'active']);
        foreach ($balances as $balance) {
            $this->eligible($balance);
            $take = [];
            foreach ($needed as $field => $amount) {
                $available = bcsub($balance->$field, $balance->{'reserved_'.$field}, 6);
                $take[$field] = bccomp($amount, $available, 6) <= 0 ? $amount : $available;
            }
            if ($policy->mode === 'dual' && (bccomp($take['units'], $needed['units'], 6) || bccomp($take['grams'], $needed['grams'], 6))) {
                continue;
            }
            if (! bccomp($take['units'], '0', 6) && ! bccomp($take['grams'], '0', 6)) {
                continue;
            }
            $this->hold($balance, $take['units'], $take['grams']);
            if (++$allocationCount > 100) {
                Decimal::fail('quantity', 'Reserva excede 100 posições; divida a solicitação para permitir conferência de cada alocação.');
            }
            $this->create(InventoryAllocation::class, ['reservation_id' => $reservation->id, 'balance_id' => $balance->id, ...$take]);
            foreach ($needed as $field => $amount) {
                $needed[$field] = bcsub($amount, $take[$field], 6);
            }
            if (! bccomp($needed['units'], '0', 6) && ! bccomp($needed['grams'], '0', 6)) {
                break;
            }
        }
        if (bccomp($needed['units'], '0', 6) || bccomp($needed['grams'], '0', 6)) {
            Decimal::fail('quantity', 'Saldo liberado disponível insuficiente no depósito; lotes vencidos/quarentena/bloqueados não são elegíveis.');
        }
    }

    private function finishReservation(InventoryOperation $op, array $input): void
    {
        $reservation = $this->one(InventoryReservation::class, $input['reservation_id'] ?? null, 'reservation_id');
        if ($reservation->status !== 'active') {
            Decimal::fail('reservation_id', 'Reserva já consumida ou cancelada.');
        }
        $allocations = InventoryAllocation::where('company_id', $this->company->id)->where('reservation_id', $reservation->id)->get();
        $measurements = collect($input['measurements'] ?? []);
        foreach ($allocations as $allocation) {
            $balance = $this->owned(InventoryBalance::class, $allocation->balance_id);
            if ($op->kind === 'consume') {
                $policy = $this->eligible($balance);
                $matches = $measurements->where('allocation_id', $allocation->public_id);
                if ($policy->mode === 'count' && $matches->isNotEmpty()) {
                    Decimal::fail('measurements', 'Alocação de contagem não aceita pesagem. Informe somente a reserva para consumir UN.');
                }
                if ($policy->mode !== 'count') {
                    if ($matches->count() !== 1) {
                        Decimal::fail('measurements', 'Confirme a pesagem de cada alocação, uma única vez.');
                    }
                    [$units, $grams] = $this->measurement($op, $balance, [...$matches->first(), 'units' => $allocation->units], $policy);
                    if (bccomp($grams, $allocation->grams, 6)) {
                        Decimal::fail('measurements', 'Massa medida difere da reserva; cancele e refaça a reserva com a medição correta.');
                    }
                }
            }
            $this->hold($balance, bcsub('0', $allocation->units, 6), bcsub('0', $allocation->grams, 6));
            if ($op->kind === 'consume') {
                $this->move($op, $balance, bcsub('0', $allocation->units, 6), bcsub('0', $allocation->grams, 6));
            }
        }
        if ($op->kind === 'consume' && $measurements->contains(fn ($m) => ! $allocations->contains('public_id', $m['allocation_id']))) {
            Decimal::fail('measurements', 'Medição não pertence à reserva.');
        }
        $reservation->status = $op->kind === 'consume' ? 'consumed' : 'cancelled';
        $reservation->save();
        $this->audit($op, $reservation, 'reservations', ['status' => 'active'], ['status' => $reservation->status]);
    }

    private function physical(InventoryOperation $op, array $input): void
    {
        $balance = $this->one(InventoryBalance::class, $input['balance_id'] ?? null, 'balance_id');
        $lot = $this->owned(InventoryLot::class, $balance->lot_id);
        $policy = $op->kind === 'count' || $op->kind === 'weigh' ? $this->owned(InventoryPolicy::class, $lot->policy_id) : $this->eligible($balance);
        if ($op->kind === 'weigh' && $policy->mode === 'count') {
            Decimal::fail('balance_id', 'A política desta posição acompanha somente contagem.');
        }
        [$units, $grams] = $this->measurement($op, $balance, $input, $policy, $op->kind === 'count');
        if ($op->kind === 'weigh') {
            return;
        }
        if ($op->kind === 'count') {
            $this->create(InventoryCount::class, ['operation_id' => $op->id, 'balance_id' => $balance->id, 'expected_units' => $balance->units, 'expected_grams' => $balance->grams, 'actual_units' => $units, 'actual_grams' => $grams]);
            $this->move($op, $balance, bcsub($units, $balance->units, 6), bcsub($grams, $balance->grams, 6));

            return;
        }
        $this->move($op, $balance, bcsub('0', $units, 6), bcsub('0', $grams, 6));
        if ($op->kind === 'transfer') {
            $location = $this->one(Location::class, $input['to_location_id'] ?? null, 'to_location_id');
            $this->activeLocation($location);
            if ($location->id === $balance->location_id) {
                Decimal::fail('to_location_id', 'Escolha outro local de destino.');
            }
            $destination = $this->position($lot, $location);
            $this->move($op, $destination, $units, $grams);
        }
    }

    private function quality(InventoryOperation $op, array $input): void
    {
        $lot = $this->one(InventoryLot::class, $input['lot_id'] ?? null, 'lot_id');
        $status = $input['quality_status'];
        if ($status === 'released') {
            $policy = $this->policy($lot->variant_id);
            if (($lot->expires_on && $lot->expires_on->toDateString() < $this->today()) || (! $lot->expires_on && ($policy->requires_expiry || ! $lot->no_expiry_reason))) {
                Decimal::fail('quality_status', 'Validade do lote não permite liberação.');
            }
        }
        $before = ['quality_status' => $lot->quality_status];
        $lot->quality_status = $status;
        $lot->save();
        $this->audit($op, $lot, 'lots', $before, ['quality_status' => $status]);
    }

    private function reverse(InventoryOperation $op, InventoryOperation $original): void
    {
        if (! in_array($original->kind, ['receive', 'issue', 'consume', 'transfer', 'count'], true)) {
            Decimal::fail('operation_id', 'Esta operação não admite estorno físico.');
        }
        $moves = InventoryMovement::where('company_id', $this->company->id)->where('operation_id', $original->id)->orderByDesc('id')->get();
        foreach ($moves as $move) {
            $balance = $this->owned(InventoryBalance::class, $move->balance_id);
            $this->move($op, $balance, bcsub('0', $move->units_delta, 6), bcsub('0', $move->grams_delta, 6));
        }
        if ($original->kind === 'receive') {
            $receipt = PurchaseReceipt::where('company_id', $this->company->id)->where('operation_id', $original->id)->firstOrFail();
            $item = $this->owned(PurchaseOrderItem::class, $receipt->order_item_id);
            $item->received_quantity = bcsub($item->received_quantity, $receipt->quantity, 6);
            $item->save();
            $this->orderStatus($this->owned(PurchaseOrder::class, $item->order_id));
        }
    }
}
