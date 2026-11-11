<?php

namespace App\Http\Requests;

use App\Domain\Inventory\InventoryAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryWriteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('lot_code'))) {
            $this->merge(['lot_code' => strtoupper(trim($this->input('lot_code')))]);
        }
    }

    public function authorize(): bool
    {
        $kind = $this->route('kind');
        $resource = $this->route('resource');
        $permission = $kind ? InventoryAccess::COMMANDS[$kind] : ($resource === 'suppliers' ? 'purchasing.manage' : 'inventory.configure');

        return $this->user()->hasCompanyPermission($this->route('company'), $permission);
    }

    public function rules(): array
    {
        $kind = $this->route('kind');
        $resource = $this->route('resource');
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $rules = ['reason' => ['required', 'string', 'min:3', 'max:500'], 'company_id' => ['prohibited'], 'id' => ['prohibited'], 'public_id' => ['prohibited']];
        if (! $kind) {
            return $rules + match ($resource) {
                'suppliers' => ['name' => [$required, 'string', 'max:150'], 'tax_id' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:200'], 'phone' => ['nullable', 'string', 'max:40'], 'active' => ['sometimes', 'boolean']],
                'locations' => ['name' => [$required, 'string', 'max:150'], 'code' => [$required, 'string', 'regex:/^[A-Z0-9][A-Z0-9._-]{0,39}$/'], 'warehouse_id' => [$required, 'ulid'], 'active' => ['sometimes', 'boolean']],
                'policies' => ['variant_id' => [$required, 'ulid'], 'mode' => [$required, Rule::in(['mass', 'count', 'dual'])], 'requires_expiry' => [$required, 'boolean'], 'active' => ['sometimes', 'boolean']],
            };
        }
        $rules['idempotency_key'] = ['required', 'string', 'regex:/^[A-Za-z0-9:_-]{8,100}$/'];
        $ids = match ($kind) {
            'order' => ['supplier_id'], 'cancel-order' => ['order_id'], 'receive' => ['order_item_id', 'location_id'], 'reserve' => ['variant_id', 'warehouse_id'], 'consume','cancel-reservation' => ['reservation_id'], 'quality' => ['lot_id'], 'reverse' => ['operation_id'], default => ['balance_id']
        };
        foreach ($ids as $id) {
            $rules[$id] = ['required', 'ulid'];
        }
        foreach (['balance_id', 'to_location_id', 'lot_id'] as $id) {
            $rules[$id] ??= ['nullable', 'ulid'];
        }
        if ($kind === 'transfer') {
            $rules['to_location_id'] = ['required', 'ulid'];
        }
        $amountFields = match ($kind) {
            'receive' => ['quantity', 'units', 'gross_g', 'tare_g'],
            'reserve' => ['units', 'grams'],
            'issue', 'transfer', 'count' => ['units', 'gross_g', 'tare_g'],
            'weigh' => ['gross_g', 'tare_g'],
            default => [],
        };
        foreach (['quantity', 'units', 'grams', 'gross_g', 'tare_g'] as $field) {
            $rules[$field] = in_array($field, $amountFields, true) ? ['nullable', 'string', 'max:24'] : ['prohibited'];
        }
        if ($kind === 'order') {
            $rules += ['reference' => ['nullable', 'string', 'max:100'], 'expected_on' => ['nullable', 'date_format:Y-m-d'], 'items' => ['required', 'array', 'min:1', 'max:50'], 'items.*.variant_id' => ['required', 'ulid'], 'items.*.unit_id' => ['required', 'ulid'], 'items.*.quantity' => ['required', 'string', 'max:24'], 'items.*.unit_cost' => ['required', 'string', 'max:24']];
        }
        if ($kind === 'receive') {
            $rules += ['supplier_lot_code' => ['required', 'string', 'max:100'], 'lot_code' => ['nullable', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,39}$/'], 'received_on' => ['required', 'date_format:Y-m-d'], 'manufactured_on' => ['nullable', 'date_format:Y-m-d'], 'expires_on' => ['nullable', 'date_format:Y-m-d'], 'no_expiry_reason' => ['nullable', 'string', 'min:3', 'max:500']];
        }
        if (in_array($kind, ['receive', 'quality'], true)) {
            $rules['quality_status'] = [$kind === 'quality' ? 'required' : 'sometimes', Rule::in(['quarantine', 'released', 'blocked', 'rejected'])];
        }
        if ($kind === 'consume') {
            $rules += ['measurements' => ['sometimes', 'array', 'max:100'], 'measurements.*.allocation_id' => ['required', 'ulid', 'distinct'], 'measurements.*.gross_g' => ['required', 'string', 'max:24'], 'measurements.*.tare_g' => ['required', 'string', 'max:24']];
        }

        return $rules;
    }
}
