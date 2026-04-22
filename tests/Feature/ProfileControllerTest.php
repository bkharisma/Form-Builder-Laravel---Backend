<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_show_returns_profile_with_2fa_status(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/profile');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id', 'name', 'email', 'role', 'two_factor_enabled', 'created_at', 'updated_at'
        ]);
        $response->assertJson([
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'two_factor_enabled' => false,
        ]);
    }

    public function test_show_returns_2fa_enabled_when_active(): void
    {
        $this->user->two_factor_enabled_at = now();
        $this->user->save();

        $response = $this->actingAs($this->user)->getJson('/api/profile');

        $response->assertStatus(200);
        $response->assertJson(['two_factor_enabled' => true]);
    }

    public function test_show_requires_authentication(): void
    {
        $response = $this->getJson('/api/profile');
        $response->assertStatus(401);
    }

    public function test_update_changes_name(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/profile', [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['name' => 'New Name']);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'New Name',
        ]);
    }

    public function test_update_changes_email(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/profile', [
            'email' => 'newemail@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['email' => 'newemail@example.com']);
    }

    public function test_update_enforces_email_uniqueness(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($this->user)->putJson('/api/profile', [
            'email' => 'taken@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_update_allows_keeping_same_email(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/profile', [
            'email' => $this->user->email,
        ]);

        $response->assertStatus(200);
    }

    public function test_update_validates_name_max_length(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/profile', [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_update_validates_email_format(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/profile', [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_update_password_with_valid_current_password(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Password updated successfully']);
    }

    public function test_update_password_rejects_wrong_current_password(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/profile/password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    }

    public function test_update_password_requires_password_confirmation(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_update_password_requires_min_length(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_enable_two_factor_generates_secret_and_codes(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/profile/two-factor/enable');

        $response->assertStatus(200);
        $response->assertJsonStructure(['secret', 'qr_code_url', 'backup_codes']);
        $this->assertEquals(8, count($response->json('backup_codes')));
        $this->assertEquals(16, strlen($response->json('secret')));
    }

    public function test_enable_two_factor_returns_409_if_already_enabled(): void
    {
        $this->user->two_factor_enabled_at = now();
        $this->user->save();

        $response = $this->actingAs($this->user)->postJson('/api/profile/two-factor/enable');

        $response->assertStatus(409);
        $response->assertJson(['message' => 'Two-factor authentication is already enabled']);
    }

    public function test_verify_two_factor_activates_with_valid_code(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        $backupCodes = ['code1abcd', 'code2efgh'];

        Cache::put("two_factor_pending_{$this->user->id}", [
            'secret' => $secret,
            'backup_codes' => $backupCodes,
        ], now()->addMinutes(10));

        $validCode = $google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($this->user)->postJson('/api/profile/two-factor/verify', [
            'code' => $validCode,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Two-factor authentication enabled successfully']);

        $this->user->refresh();
        $this->assertNotNull($this->user->two_factor_enabled_at);
        $this->assertNotNull($this->user->two_factor_secret);
    }

    public function test_verify_two_factor_rejects_invalid_code(): void
    {
        Cache::put("two_factor_pending_{$this->user->id}", [
            'secret' => 'ABCDEFGHIJKLMNOP',
            'backup_codes' => ['code1abcd'],
        ], now()->addMinutes(10));

        $response = $this->actingAs($this->user)->postJson('/api/profile/two-factor/verify', [
            'code' => '000000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_verify_two_factor_rejects_if_no_pending_data(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/profile/two-factor/verify', [
            'code' => '123456',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_disable_two_factor_requires_password(): void
    {
        $this->user->two_factor_secret = Crypt::encryptString('SECRET1234567890');
        $this->user->two_factor_enabled_at = now();
        $this->user->two_factor_recovery_codes = ['code1abcd'];
        $this->user->save();

        $response = $this->actingAs($this->user)->postJson('/api/profile/two-factor/disable', [
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_disable_two_factor_clears_fields(): void
    {
        $this->user->two_factor_secret = Crypt::encryptString('SECRET1234567890');
        $this->user->two_factor_enabled_at = now();
        $this->user->two_factor_recovery_codes = ['code1abcd'];
        $this->user->save();

        $response = $this->actingAs($this->user)->postJson('/api/profile/two-factor/disable', [
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Two-factor authentication disabled successfully']);

        $this->user->refresh();
        $this->assertNull($this->user->two_factor_secret);
        $this->assertNull($this->user->two_factor_enabled_at);
        $this->assertNull($this->user->two_factor_recovery_codes);
    }

    public function test_regenerate_codes_returns_new_codes(): void
    {
        $this->user->two_factor_secret = Crypt::encryptString('SECRET1234567890');
        $this->user->two_factor_enabled_at = now();
        $this->user->two_factor_recovery_codes = ['oldcode1', 'oldcode2'];
        $this->user->save();

        $response = $this->actingAs($this->user)->postJson('/api/profile/two-factor/regenerate-codes');

        $response->assertStatus(200);
        $response->assertJsonStructure(['backup_codes']);
        $this->assertEquals(8, count($response->json('backup_codes')));
    }

    public function test_regenerate_codes_replaces_old_codes(): void
    {
        $this->user->two_factor_secret = Crypt::encryptString('SECRET1234567890');
        $this->user->two_factor_enabled_at = now();
        $this->user->two_factor_recovery_codes = ['oldcode1', 'oldcode2'];
        $this->user->save();

        $response = $this->actingAs($this->user)->postJson('/api/profile/two-factor/regenerate-codes');

        $newCodes = $response->json('backup_codes');
        $this->user->refresh();
        $storedCodes = $this->user->two_factor_recovery_codes;

        $this->assertEquals($newCodes, $storedCodes);
        $this->assertNotContains('oldcode1', $storedCodes);
    }

    public function test_regenerate_codes_fails_if_2fa_not_enabled(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/profile/two-factor/regenerate-codes');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Two-factor authentication is not enabled']);
    }

    public function test_login_history_returns_paginated_results(): void
    {
        LoginHistory::factory()->count(15)->for($this->user)->create();

        $response = $this->actingAs($this->user)->getJson('/api/profile/login-history?per_page=10');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'ip_address', 'user_agent', 'login_at', 'success', 'failure_reason']
            ],
            'current_page', 'last_page', 'per_page', 'total'
        ]);
        $response->assertJsonPath('per_page', 10);
        $response->assertJsonPath('total', 15);
    }

    public function test_login_history_only_shows_own_records(): void
    {
        $otherUser = User::factory()->create();

        LoginHistory::factory()->for($this->user)->create(['ip_address' => '192.168.1.1']);
        LoginHistory::factory()->for($otherUser)->create(['ip_address' => '192.168.1.2']);

        $response = $this->actingAs($this->user)->getJson('/api/profile/login-history');

        $response->assertStatus(200);
        $response->assertJsonPath('total', 1);
        $response->assertJsonPath('data.0.ip_address', '192.168.1.1');
    }

    public function test_login_history_limits_per_page(): void
    {
        LoginHistory::factory()->count(100)->for($this->user)->create();

        $response = $this->actingAs($this->user)->getJson('/api/profile/login-history?per_page=100');

        $response->assertStatus(200);
        $response->assertJsonPath('per_page', 50);
    }
}