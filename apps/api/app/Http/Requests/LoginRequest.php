<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:254'], 'password' => ['required', 'string', 'max:256']];
    }

    public function messages(): array
    {
        return ['email.required' => 'Informe seu e-mail.', 'email.email' => 'Informe um e-mail válido.', 'password.required' => 'Informe sua senha.'];
    }
}
