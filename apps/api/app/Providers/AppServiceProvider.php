<?php

namespace App\Providers;

use App\Policies\CatalogPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('catalog.view', [CatalogPolicy::class, 'view']);
        Gate::define('catalog.manage', [CatalogPolicy::class, 'manage']);
        RateLimiter::for('login', fn (Request $r) => [Limit::perMinute(20)->by($r->ip()), Limit::perMinute(5)->by(hash('sha256', mb_strtolower(is_string($r->input('email')) ? $r->input('email') : '').'|'.$r->ip()))]);
        RateLimiter::for('password', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('workspace', fn (Request $r) => Limit::perMinute(max(1, config($r->isMethod('GET') ? 'workspace.read_per_minute' : 'workspace.write_per_minute')))
            ->by(($r->isMethod('GET') ? 'read|' : 'write|').$r->user()->id));
        ResetPassword::createUrlUsing(fn ($user, $token) => rtrim(config('app.frontend_url'), '/').'/redefinir-senha?'.http_build_query(['token' => $token, 'email' => $user->email]));
    }
}
