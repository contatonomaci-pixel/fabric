<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('TEST_DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Requer MySQL 8.4 e Redis reais; executar suíte MySQL/CI.');
        }
    }

    public function test_mysql84_innodb_utf8mb4_and_real_redis(): void
    {
        if (getenv('TEST_DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Requer MySQL 8.4 e Redis reais; executar suíte MySQL/CI.');
        }
        $this->assertStringStartsWith('8.4.', DB::selectOne('SELECT VERSION() AS version')->version);
        $tables = DB::select("SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('users','companies','company_users')");
        $this->assertCount(3, $tables);
        foreach ($tables as $table) {
            $this->assertSame('InnoDB', $table->ENGINE);
            $this->assertStringStartsWith('utf8mb4', $table->TABLE_COLLATION);
        }
        $this->assertNotEmpty(Redis::connection()->ping());
    }

    public function test_real_redis_queue_worker_processes_a_job(): void
    {
        $key = 'foundation-probe:'.Str::uuid();
        $queue = 'foundation-probe-'.Str::uuid();
        Queue::connection('redis')->push(CallQueuedClosure::create(static function () use ($key): void {
            Cache::store('redis')->put($key, 'processed', 60);
        }), '', $queue);

        $this->artisan('queue:work', ['connection' => 'redis', '--queue' => $queue, '--once' => true, '--tries' => 1, '--timeout' => 15])
            ->assertExitCode(0);
        $this->assertSame('processed', Cache::store('redis')->pull($key));
    }

    public function test_password_reset_revokes_a_real_database_session(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['password' => 'Ficticia-Segura123!']);
        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Ficticia-Segura123!'])->assertOk();
        $cookieName = config('session.cookie');
        $oldSession = $login->getCookie($cookieName)->getValue();
        Auth::forgetGuards();
        $this->withCookie($cookieName, $oldSession)->getJson('/api/v1/me')->assertOk();
        $this->assertDatabaseHas('sessions', ['id' => $oldSession, 'user_id' => $user->id]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => Password::createToken($user),
            'password' => 'Nova-Ficticia123!',
            'password_confirmation' => 'Nova-Ficticia123!',
        ])->assertOk();
        Auth::forgetGuards();
        $this->withCookie($cookieName, $oldSession)->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertDatabaseMissing('sessions', ['id' => $oldSession, 'user_id' => $user->id]);
    }
}
