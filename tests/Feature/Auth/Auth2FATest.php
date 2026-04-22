<?php

namespace Tests\Feature\Auth;

use App\Models\LoginHistory;
use App\Models\User;
use App\Services\TurnstileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class Auth2FATest extends TestCase
{
    use RefreshDatabase;

    private function mockTurnstile(bool $passes = true): void
    {
        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')->andReturn($passes);
        $mockService->shouldReceive('getErrorMessage')->andReturn($passes ? null : 'Verification failed.');
    }

    public function test_login_without_2fa_returns_token(): void
    {
        $this->mockTurnstile();
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['user', 'token']);
        $response->assertJsonPath('user.id', $user->id);
    }

    public function test_login_without_2fa_creates_success_login_history(): void
    {
        $this->mockTurnstile();
        $user = User::factory()->create();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'cf_turnstile_response' => 'valid-token',
        ]);

        $this->assertDatabaseHas('login_history', [
            'user_id' => $user->id,
            'success' => true,
        ]);
    }

    public function test_login_with_2fa_returns_requires_2fa(): void
    {
        $this->mockTurnstile();
        $user = User::factory()->create([
            'two_factor_secret' => Crypt::encryptString('SECRET12345678'),
            'two_factor_enabled_at' => now(),
            'two_factor_recovery_codes' => ['code1', 'code2'],
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'requires_2fa' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
        $response->assertJsonMissing(['token']);
    }

    public function test_login_with_2fa_creates_pending_login_history(): void
    {
        $this->mockTurnstile();
        $user = User::factory()->create([
            'two_factor_secret' => Crypt::encryptString('SECRET12345678'),
            'two_factor_enabled_at' => now(),
            'two_factor_recovery_codes' => ['code1', 'code2'],
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'cf_turnstile_response' => 'valid-token',
        ]);

        $this->assertDatabaseHas('login_history', [
            'user_id' => $user->id,
            'success' => false,
            'failure_reason' => 'pending_2fa',
        ]);
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $this->mockTurnstile();
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_login_with_nonexistent_email_fails(): void
    {
        $this->mockTurnstile();

        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_email(): void
    {
        $response = $this->postJson('/api/login', [
            'password' => 'password',
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_password(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_login_requires_valid_email_format(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'invalid-email',
            'password' => 'password',
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_turnstile_token(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cf_turnstile_response']);
    }
}