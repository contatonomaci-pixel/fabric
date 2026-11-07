<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordRequest;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function forgot(PasswordRequest $request)
    {
        Password::sendResetLink([...$request->safe()->only('email'), 'active' => true]);

        return ApiResponse::data(['message' => 'Se houver uma conta ativa com esse e-mail, enviaremos as instruções.']);
    }

    public function reset(PasswordRequest $request)
    {
        $status = Password::reset([...$request->validated(), 'active' => true], function ($user, $password) {
            DB::transaction(function () use ($user, $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            });
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error('Link inválido ou expirado. Solicite outro.', 'INVALID_RESET_TOKEN', 422);
        }

        // A sessão em memória também precisa ser encerrada: StartSession a salva ao fim da resposta.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::data(['message' => 'Senha redefinida. Entre novamente.']);
    }
}
