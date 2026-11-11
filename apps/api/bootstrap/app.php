<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\RequestContext;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RequestContext::class);
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn () => rtrim(config('app.frontend_url'), '/').'/entrar');
        $middleware->alias(['active' => EnsureActiveUser::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $r) => $r->is('api/*') || $r->expectsJson());
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            if ($e instanceof AuthenticationException) {
                $status = 401;
            }
            if ($e instanceof AuthorizationException) {
                $status = 403;
            }
            if ($e instanceof ModelNotFoundException) {
                $status = 404;
            }
            if ($e instanceof ValidationException) {
                return ApiResponse::error('Confira os campos informados.', 'VALIDATION_ERROR', 422, $e->errors());
            }
            [$message,$code] = match ($status) {
                401 => ['Entre na sua conta para continuar.', 'UNAUTHENTICATED'],
                403 => ['Você não tem permissão para acessar este recurso.', 'FORBIDDEN'],
                404 => ['Recurso não encontrado.', 'NOT_FOUND'],
                405 => ['Método não permitido.', 'METHOD_NOT_ALLOWED'],
                419 => ['Sua sessão expirou. Atualize a página e tente novamente.', 'CSRF_MISMATCH'],
                429 => ['Muitas tentativas. Aguarde e tente novamente.', 'TOO_MANY_REQUESTS'],
                default => ['Não foi possível concluir a operação.', 'SERVER_ERROR'],
            };
            $response = ApiResponse::error($message, $code, $status);
            if ($e instanceof HttpExceptionInterface) {
                $response->headers->add($e->getHeaders());
            }

            return $response;
        });
    })->create();
