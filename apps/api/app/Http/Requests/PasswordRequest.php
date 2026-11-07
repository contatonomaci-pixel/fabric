<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordRequest extends FormRequest
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
        return array_merge(['email' => ['required', 'email', 'max:254']], $this->is('*/reset-password') ? [
            'token' => ['required', 'string', 'max:256'],
            'password' => ['required', 'confirmed', 'max:256', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ] : []);
    }
}
