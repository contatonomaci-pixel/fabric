<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\AuthenticateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request, AuthenticateUser $action)
    {
        $action->handle($request->validated());

        return ApiResponse::data(['message' => 'Acesso autorizado.']);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return ApiResponse::data(['user' => ['public_id' => $user->public_id, 'name' => $user->name, 'email' => $user->email, 'is_demo' => $user->is_demo], 'environment' => app()->environment('local') ? 'development' : 'application']);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::data(['message' => 'Sessão encerrada.']);
    }
}
