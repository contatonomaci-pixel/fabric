<?php

namespace App\Http\Requests;

use App\Domain\Catalog\CatalogDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasCompanyPermission($this->route('company'), CatalogDefinition::permission($this->route('resource'), true));
    }

    protected function prepareForValidation(): void
    {
        foreach (['sku', 'code'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $field === 'code' && $value === 'g' ? 'g' : strtoupper($value)]);
            }
        }
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $quantity = ['bail', 'nullable', 'string', 'regex:/^\d{1,12}(\.\d{1,6})?$/', function ($field, $value, $fail) {
            if (bccomp($value, '0', 6) <= 0) {
                $fail('Informe uma quantidade positiva.');
            }
        }];
        $rules = ['name' => [$required, 'string', 'max:150'], 'active' => ['sometimes', 'boolean'], 'company_id' => ['prohibited'], 'public_id' => ['prohibited'], 'id' => ['prohibited']];
        $specific = match ($this->route('resource')) {
            'factories' => ['timezone' => [$required, 'timezone']],
            'warehouses' => ['kind' => [$required, Rule::in(['general', 'cold_room', 'raw_material', 'finished_goods'])]],
            'units' => ['name' => [$required, 'string', 'max:80'], 'code' => [$required, 'string', 'regex:/^(g|[A-Z][A-Z0-9_]{0,11})$/'], 'dimension' => [$required, Rule::in(['mass', 'count', 'package'])], 'ratio_to_base' => $quantity],
            'product-families' => [],
            'products' => ['type' => [$required, Rule::in(['ingredient', 'intermediate', 'finished', 'packaging'])]],
            'product-variants' => ['flavor' => ['nullable', 'string', 'max:100'], 'nominal_unit_weight_g' => $quantity, 'attributes' => ['nullable', 'array', 'max:20'], 'attributes.*' => ['nullable', 'string', 'max:200']],
            'commercial-skus' => ['sku' => ['nullable', 'string', 'regex:/^[A-Z0-9][A-Z0-9._-]{0,39}$/'], 'packaging_qty' => $quantity, 'nominal_net_weight_g' => $quantity, 'variable_weight' => ['sometimes', 'boolean'], 'price' => ['nullable', 'string', 'regex:/^\d{1,14}(\.\d{1,4})?$/'], 'generate_internal_barcode' => $this->isMethod('post') ? ['sometimes', 'boolean'] : ['prohibited']],
        };
        foreach (CatalogDefinition::REFERENCES[$this->route('resource')] ?? [] as $field => $model) {
            $nullable = $field === 'parent_id';
            $specific[$field] = [$nullable ? 'nullable' : $required, 'ulid', Rule::exists((new $model)->getTable(), 'public_id')->where('company_id', $this->route('company')->id)];
        }

        return array_replace($rules, $specific);
    }
}
