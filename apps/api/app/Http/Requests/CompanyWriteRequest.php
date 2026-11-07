<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CompanyWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('post') ? Gate::allows('create', Company::class) : Gate::allows('update', $this->route('company'));
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return ['trade_name' => [$required, 'string', 'max:150'], 'legal_name' => [$required, 'string', 'max:200'], 'timezone' => [$required, 'timezone'], 'active' => ['sometimes', 'boolean'], 'is_demo' => ['prohibited'], 'company_id' => ['prohibited'], 'public_id' => ['prohibited'], 'id' => ['prohibited']];
    }
}
