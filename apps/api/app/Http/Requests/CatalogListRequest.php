<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy aplicada antes da consulta pelo controller/service.
    }

    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,100'], 'q' => ['nullable', 'string', 'max:100'], 'active' => ['sometimes', 'boolean'], 'family_id' => ['nullable', 'ulid'], 'variant_id' => ['nullable', 'ulid'], 'type' => ['nullable', Rule::in(['ingredient', 'intermediate', 'finished', 'packaging'])], 'resource_id' => ['nullable', 'ulid']];
    }
}
