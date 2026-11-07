<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BarcodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasCompanyPermission($this->route('company'), $this->route()->getActionMethod() === 'reserve' ? 'catalog.manage' : 'catalog.view');
    }

    public function rules(): array
    {
        return ['kind' => ['required', Rule::in(['internal_code128'])], ...($this->route()->getActionMethod() === 'reserve' ? ['sku_id' => ['required', 'ulid']] : ['code' => ['required', 'string', 'max:40']])];
    }
}
