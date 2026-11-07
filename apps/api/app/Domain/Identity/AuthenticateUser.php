<?php

namespace App\Domain\Identity;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticateUser
{
    public function handle(array $credentials): void
    {
        if (! Auth::guard('web')->attempt([...$credentials, 'active' => true], false)) {
            throw ValidationException::withMessages(['email' => ['E-mail ou senha inválidos.']]);
        }
        request()->session()->regenerate();
    }
}
