<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ['q' => ['nullable', 'string', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,100'], 'active' => ['sometimes', 'boolean'], 'status' => ['nullable', 'string', 'max:30'], 'quality_status' => ['nullable', 'string', 'max:20'], 'expires_before' => ['nullable', 'date_format:Y-m-d']];
        $rules['receivable'] = ['sometimes', 'boolean'];
        foreach (['supplier_id', 'order_id', 'variant_id', 'unit_id', 'policy_id', 'warehouse_id', 'lot_id', 'location_id', 'reservation_id', 'balance_id', 'operation_id', 'resource_id'] as $id) {
            $rules[$id] = ['nullable', 'ulid'];
        }

        return $rules;
    }
}
