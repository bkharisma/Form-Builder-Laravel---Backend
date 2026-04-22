<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class Login2FAControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $userWith2FA;

    private string $secret;

    private array $backupCodes;

    protected function setUp(): void
    {
        parent::setUp();

        $google2fa = new Google2FA();
        $this->secret = $google2fa->generateSecretKey();
        $this->backupCodes = ['backup1ab', 'backup2cd', 'backup3ef'];

        $this->userWith2FA = User::factory()->create([
            'two_factor_secret' => Crypt::encryptString($this->secret),
            'two_factor_enabled_at' => now(),
            'two_factor_recovery_codes' => $this->backupCodes,
        ]);
    }

    public function test_verify_with_valid_totp_code(): void
    {
        $google2fa = new Google2FA();
        $validCode = $google2fa->getCurrentOtp($this->secret);

        $response = $this->postJson('/api/login/2fa', [
            'user_id' => $this->userWith2FA->id,
            'code' => $validCode,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['user', 'token']);
        $response->assertJsonPath('user.id', $this->userWith2FA->id);
    }

    public function test_verify_creates_login_history_success_record(): void
    {
        $google2fa = new Google2FA();
        $validCode = $google2fa->getCurrentOtp($this->secret);

        $this->postJson('/api/login/2fa', [
            'user_id' => $this->userWith2FA->id,
            'code' => $validCode,
        ]);

        $this->assertDatabaseHas('login_history', [
            'user_id' => $this->userWith2FA->id,
            'success' => true,
        ]);
    }

    public function test_verify_with_backup_code_consume_and_login(): void
    {
        $response = $this->postJson('/api/login/2fa', [
            'user_id' => $this->userWith2FA->id,
            'code' => 'backup1ab',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['user', 'token']);

        $this->userWith2FA->refresh();
        $remainingCodes = $this->userWith2FA->two_factor_recovery_codes;
        $this->assertNotContains('backup1ab', $remainingCodes);
        $this->assertEquals(2, count($remainingCodes));
    }

    public function test_verify_rejects_invalid_code(): void
    {
        $response = $this->postJson('/api/login/2fa', [
            'user_id' => $this->userWith2FA->id,
            'code' => '000000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_verify_rejects_non_2fa_user(): void
    {
        $userWithout2FA = User::factory()->create();

        $response = $this->postJson('/api/login/2fa', [
            'user_id' => $userWithout2FA->id,
            'code' => '123456',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_verify_rejects_nonexistent_user(): void
    {
        $response = $this->postJson('/api/login/2fa', [
            'user_id' => 99999,
            'code' => '123456',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_verify_requires_user_id(): void
    {
        $response = $this->postJson('/api/login/2fa', [
            'code' => '123456',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_verify_requires_code(): void
    {
        $response = $this->postJson('/api/login/2fa', [
            'user_id' => $this->userWith2FA->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_verify_rate_limits_after_10_failed_attempts(): void
    {
        $rateLimitKey = "2fa_attempts_{$this->userWith2FA->id}";
        Cache::put($rateLimitKey, 10, now()->addMinutes(5));

        $response = $this->postJson('/api/login/2fa', [
            'user_id' => $this->userWith2FA->id,
            'code' => '000000',
        ]);

        $response->assertStatus(429);
        $response->assertJson(['message' => 'Too many failed 2FA attempts. Please try again later.']);
    }

    public function test_verify_clears_rate_limit_on_success(): void
    {
        $rateLimitKey = "2fa_attempts_{$this->userWith2FA->id}";
        Cache::put($rateLimitKey, 5, now()->addMinutes(5));

        $google2fa = new Google2FA();
        $validCode = $google2fa->getCurrentOtp($this->secret);

        $this->postJson('/api/login/2fa', [
            'user_id' => $this->userWith2FA->id,
            'code' => $validCode,
        ]);

        $this->assertNull(Cache::get($rateLimitKey));
    }

    public function test_verify_increments_rate_limit_on_failure(): void
    {
        $rateLimitKey = "2fa_attempts_{$this->userWith2FA->id}";

        $this->postJson('/api/login/2fa', [
            'user_id' => $this->userWith2FA->id,
            'code' => '000000',
        ]);

        $this->assertEquals(1, Cache::get($rateLimitKey));

        $this->postJson('/api/login/2fa', [
            'user_id' => $this->userWith2FA->id,
            'code' => '000000',
        ]);

        $this->assertEquals(2, Cache::get($rateLimitKey));
    }

    public function test_verify_creates_login_history_failure_record(): void
    {
        $this->postJson('/api/login/2fa', [
            'user_id' => $this->userWith2FA->id,
            'code' => '000000',
        ]);

        $this->assertDatabaseHas('login_history', [
            'user_id' => $this->userWith2FA->id,
            'success' => false,
            'failure_reason' => 'invalid_2fa',
        ]);
    }

    public function test_verify_cannot_use_same_backup_code_twice(): void
    {
        $this->postJson('/api/login/2fa', [
            'user_id' => $this->userWith2FA->id,
            'code' => 'backup1ab',
        ]);

        $response = $this->postJson('/api/login/2fa', [
            'user_id' => $this->userWith2FA->id,
            'code' => 'backup1ab',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }
}