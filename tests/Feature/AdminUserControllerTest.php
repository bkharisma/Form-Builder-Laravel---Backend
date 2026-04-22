<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_unauthenticated_user_cannot_access_admin_users(): void
    {
        $response = $this->getJson('/api/admin-users');
        $response->assertStatus(401);
    }

    public function test_authenticated_admin_can_list_users(): void
    {
        User::factory()->count(3)->create(['role' => 'admin']);

        $response = $this->actingAs($this->admin)->getJson('/api/admin-users');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'role', 'created_at', 'updated_at']
            ],
            'current_page',
            'per_page',
            'total',
        ]);
    }

    public function test_admin_can_search_users_by_name(): void
    {
        User::factory()->create(['name' => 'John Doe', 'role' => 'admin']);
        User::factory()->create(['name' => 'Jane Smith', 'role' => 'admin']);

        $response = $this->actingAs($this->admin)->getJson('/api/admin-users?search=John');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'John Doe');
    }

    public function test_admin_can_search_users_by_email(): void
    {
        User::factory()->create(['email' => 'john@example.com', 'role' => 'admin']);
        User::factory()->create(['email' => 'jane@example.com', 'role' => 'admin']);

        $response = $this->actingAs($this->admin)->getJson('/api/admin-users?search=john@');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.email', 'john@example.com');
    }

    public function test_admin_can_create_new_user(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/admin-users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'role' => 'admin',
        ]);
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_create_user_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/admin-users', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_create_user_validates_email_unique(): void
    {
        User::factory()->create(['email' => 'existing@example.com', 'role' => 'admin']);

        $response = $this->actingAs($this->admin)->postJson('/api/admin-users', [
            'name' => 'New User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_create_user_validates_password_min_length(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/admin-users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_create_user_validates_password_confirmation(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/admin-users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_admin_can_show_user(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($this->admin)->getJson("/api/admin-users/{$user->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function test_admin_can_update_user(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($this->admin)->putJson("/api/admin-users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'role' => 'admin',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_admin_can_update_user_password(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($this->admin)->putJson("/api/admin-users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'admin',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);
    }

    public function test_update_user_email_must_be_unique(): void
    {
        $user1 = User::factory()->create(['role' => 'admin']);
        $user2 = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($this->admin)->putJson("/api/admin-users/{$user1->id}", [
            'name' => 'Updated',
            'email' => $user2->email,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_update_user_can_keep_same_email(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($this->admin)->putJson("/api/admin-users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => $user->email,
            'role' => 'admin',
        ]);

        $response->assertStatus(200);
    }

    public function test_admin_can_delete_user(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($this->admin)->deleteJson("/api/admin-users/{$user->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Admin user deleted successfully.']);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $response = $this->actingAs($this->admin)->deleteJson("/api/admin-users/{$this->admin->id}");

        $response->assertStatus(403);
        $response->assertJson(['message' => 'You cannot delete your own account.']);
    }

    public function test_cannot_delete_last_admin_user(): void
    {
        $response = $this->actingAs($this->admin)->deleteJson("/api/admin-users/{$this->admin->id}");

        $response->assertStatus(403);
        $response->assertJson(['message' => 'You cannot delete your own account.']);
    }

    public function test_cannot_delete_last_admin_when_multiple(): void
    {
        $userToDelete = User::factory()->create(['role' => 'admin']);

        $deleteResponse = $this->actingAs($this->admin)->deleteJson("/api/admin-users/{$userToDelete->id}");
        $deleteResponse->assertStatus(200);

        $lastAdminResponse = $this->actingAs($this->admin)->deleteJson("/api/admin-users/{$this->admin->id}");
        $lastAdminResponse->assertStatus(403);
        $lastAdminResponse->assertJson(['message' => 'You cannot delete your own account.']);
    }
}