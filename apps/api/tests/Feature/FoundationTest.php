<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    private function company(User $user, string $role = 'owner'): Company
    {
        $this->seed(RoleSeeder::class);
        $company = Company::create(['trade_name' => 'Empresa FICTÍCIA', 'legal_name' => 'Teste FICTÍCIO']);
        $user->companies()->attach($company, ['role_id' => DB::table('roles')->where('code', $role)->value('id')]);

        return $company;
    }

    public function test_anonymous_requests_are_json_401_with_correlation_id(): void
    {
        $response = $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
        $response->assertHeader('X-Request-ID', $response->json('request_id'));
        $this->getJson('/api/v1/companies')->assertUnauthorized();
    }

    public function test_login_me_and_logout_use_session_and_hide_internal_ids(): void
    {
        $user = User::factory()->create(['password' => 'Ficticia-Segura123!']);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Ficticia-Segura123!'])->assertOk();
        $this->assertAuthenticatedAs($user, 'web');
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.user.public_id', $user->public_id)->assertJsonMissingPath('data.user.password')->assertJsonMissingPath('data.user.id');
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertGuest('web');
        Auth::forgetGuards();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_invalid_or_inactive_users_cannot_login(): void
    {
        $user = User::factory()->create(['active' => false]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'password'])->assertUnprocessable();
        $this->assertGuest('web');
    }

    public function test_disabled_existing_session_is_revoked(): void
    {
        $user = User::factory()->create(['active' => false]);
        $this->actingAs($user)->getJson('/api/v1/me')->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_company_queries_are_scoped_and_permissions_enforced(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $own = $this->company($alice);
        $other = $this->company($bob);
        $this->actingAs($alice)->getJson('/api/v1/companies?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.public_id', $own->public_id)->assertJsonPath('meta.total', 1)->assertJsonMissingPath('data.0.id');
        $this->getJson('/api/v1/companies/'.$own->public_id)->assertOk();
        $this->getJson('/api/v1/companies/'.$other->public_id)->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $operator = User::factory()->create();
        $restricted = $this->company($operator, 'operator');
        $this->actingAs($operator)->getJson('/api/v1/companies/'.$restricted->public_id)->assertForbidden();
        $this->getJson('/api/v1/companies')->assertJsonCount(0, 'data');
    }

    public function test_multiple_roles_do_not_duplicate_companies_and_inactive_company_is_hidden(): void
    {
        $user = User::factory()->create();
        $company = $this->company($user);
        $user->companies()->attach($company, ['role_id' => DB::table('roles')->where('code', 'auditor')->value('id')]);
        $this->actingAs($user)->getJson('/api/v1/companies')->assertJsonPath('meta.total', 1);
        $company->update(['active' => false]);
        $this->getJson('/api/v1/companies')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/companies/'.$company->public_id)->assertForbidden();
    }

    public function test_pagination_and_unknown_routes_have_consistent_errors(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/v1/companies?per_page=1000')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/no-such-resource')->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'limited@example.test', 'password' => 'bad'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'limited@example.test', 'password' => 'bad'])->assertStatus(429)->assertHeader('Retry-After')->assertJsonPath('code', 'TOO_MANY_REQUESTS');
    }

    public function test_csrf_is_enforced_even_without_origin_header(): void
    {
        $this->app['env'] = 'local'; // Habilita o ramo real de CSRF, desativado normalmente pelo framework em testes.
        $this->postJson('/api/v1/auth/login', ['email' => 'test@example.test', 'password' => 'anything'])->assertStatus(419)->assertJsonPath('code', 'CSRF_MISMATCH');
        $this->postJson('/api/v1/auth/reset-password', [])->assertStatus(419);
    }

    public function test_demo_seed_is_fictitious_idempotent_and_password_hashed(): void
    {
        config(['demo.password' => 'Ficticia-Segura123!']);
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('company_users', 1);
        $user = User::first();
        $this->assertTrue($user->is_demo);
        $this->assertTrue(Hash::check('Ficticia-Segura123!', $user->password));
        $this->assertTrue(Company::first()->is_demo);
    }

    public function test_demo_seed_rejects_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(\RuntimeException::class);
        $this->app->make(DemoSeeder::class)->run();
    }

    public function test_duplicate_membership_is_rejected_by_database(): void
    {
        $user = User::factory()->create();
        $company = $this->company($user);
        $this->expectException(QueryException::class);
        $user->companies()->attach($company, ['role_id' => DB::table('roles')->where('code', 'owner')->value('id')]);
    }

    public function test_forgot_password_does_not_reveal_account_and_reset_token_is_single_use(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class);
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.test'])->assertOk();
        $this->assertStringContainsString('Se houver', $unknown->json('data.message'));
        $token = Password::createToken($user);
        DB::table('sessions')->insert(['id' => 'fictitious-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'Nova-SenhaSegura123!', 'password_confirmation' => 'Nova-SenhaSegura123!'];
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check($payload['password'], $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'fictitious-session']);
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable()->assertJsonPath('code', 'INVALID_RESET_TOKEN');
    }

    public function test_reset_token_expiration_and_password_policy(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'weak', 'password_confirmation' => 'weak'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->travel(61)->minutes();
        $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'Nova-SenhaSegura123!', 'password_confirmation' => 'Nova-SenhaSegura123!'])->assertUnprocessable()->assertJsonPath('code', 'INVALID_RESET_TOKEN');
    }

    public function test_readiness_checks_dependencies_and_does_not_leak_details(): void
    {
        Redis::shouldReceive('connection')->once()->andReturnSelf();
        Redis::shouldReceive('ping')->once()->andThrow(new \RuntimeException('secret-details'));
        $this->getJson('/api/v1/health')->assertStatus(503)->assertJsonPath('data.checks.database', true)->assertJsonPath('data.checks.redis', false)->assertDontSee('secret-details');
    }

    public function test_membership_requires_existing_company(): void
    {
        $user = User::factory()->create();
        $this->seed(RoleSeeder::class);
        $this->expectException(QueryException::class);
        $user->companies()->attach(999999, ['role_id' => DB::table('roles')->where('code', 'owner')->value('id')]);
    }

    public function test_malformed_email_is_validation_error_instead_of_server_error(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => ['invalid'], 'password' => 'fictitious'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/auth/forgot-password', ['email' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_demo_seed_requires_local_password(): void
    {
        config(['demo.password' => null]);
        $this->expectException(\RuntimeException::class);
        $this->app->make(DemoSeeder::class)->run();
    }

    public function test_password_email_is_localized_and_points_to_first_party_frontend(): void
    {
        $user = User::factory()->create();
        $message = (new ResetPassword('fictitious-token'))->toMail($user);
        $this->assertSame('Recuperação de senha', $message->subject);
        $this->assertStringStartsWith('http://localhost:5173/redefinir-senha?', $message->actionUrl);
        $this->assertStringContainsString('token=fictitious-token', $message->actionUrl);
    }

    public function test_password_reset_also_ends_the_requesting_authenticated_session(): void
    {
        $user = User::factory()->create();
        $sessionKey = Auth::guard('web')->getName();
        $token = Password::createToken($user);

        $response = $this->actingAs($user, 'web')->withSession([$sessionKey => $user->id])
            ->postJson('/api/v1/auth/reset-password', [
                'email' => $user->email,
                'token' => $token,
                'password' => 'Nova-SenhaSegura123!',
                'password_confirmation' => 'Nova-SenhaSegura123!',
            ])->assertOk();

        $this->assertGuest('web');
        $response->assertSessionMissing($sessionKey);
        Auth::forgetGuards();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }
}
