<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Storage::fake('public');
    }

    public function test_unauthenticated_user_cannot_access_settings(): void
    {
        $response = $this->getJson('/api/admin/settings');
        $response->assertStatus(401);
    }

    public function test_authenticated_admin_can_get_settings(): void
    {
        $settings = AppSetting::create([
            'app_name' => 'Form Builder',
            'app_description' => 'Test description',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/admin/settings');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'app_name',
            'app_description',
            'logo_path',
            'office_name',
            'office_address',
            'office_phone',
            'office_email',
        ]);
    }

    public function test_settings_returns_default_when_no_settings_exist(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/settings');

        $response->assertStatus(200);
        $response->assertJson(['app_name' => 'Form Builder']);
    }

    public function test_authenticated_admin_can_update_settings(): void
    {
        $response = $this->actingAs($this->admin)->putJson('/api/admin/settings', [
            'app_name' => 'Custom Form Builder',
            'app_description' => 'A custom description',
            'office_name' => 'Main Office',
            'office_address' => '123 Main St',
            'office_phone' => '555-1234',
            'office_email' => 'office@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'app_name' => 'Custom Form Builder',
            'app_description' => 'A custom description',
            'office_name' => 'Main Office',
            'office_address' => '123 Main St',
            'office_phone' => '555-1234',
            'office_email' => 'office@example.com',
        ]);
    }

    public function test_update_settings_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)->putJson('/api/admin/settings', [
            'app_name' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['app_name']);
    }

    public function test_update_settings_validates_email_format(): void
    {
        $response = $this->actingAs($this->admin)->putJson('/api/admin/settings', [
            'app_name' => 'Test',
            'office_email' => 'invalid-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['office_email']);
    }

    public function test_authenticated_admin_can_upload_logo(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 100, 100);

        $response = $this->actingAs($this->admin)->postJson('/api/admin/settings/logo', [
            'logo' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'logo_path', 'logo_url']);
    }

    public function test_logo_upload_validates_file_type(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1000);

        $response = $this->actingAs($this->admin)->postJson('/api/admin/settings/logo', [
            'logo' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['logo']);
    }

    public function test_logo_upload_validates_file_size(): void
    {
        $file = UploadedFile::fake()->image('logo.png')->size(3000);

        $response = $this->actingAs($this->admin)->postJson('/api/admin/settings/logo', [
            'logo' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['logo']);
    }

    public function test_logo_upload_accepts_jpg(): void
    {
        $file = UploadedFile::fake()->image('logo.jpg', 100, 100);

        $response = $this->actingAs($this->admin)->postJson('/api/admin/settings/logo', [
            'logo' => $file,
        ]);

        $response->assertStatus(200);
    }

    public function test_logo_upload_accepts_svg(): void
    {
        $file = UploadedFile::fake()->create('logo.svg', 100, 'image/svg+xml');

        $response = $this->actingAs($this->admin)->postJson('/api/admin/settings/logo', [
            'logo' => $file,
        ]);

        $response->assertStatus(200);
    }

    public function test_logo_upload_replaces_old_logo(): void
    {
        $file1 = UploadedFile::fake()->image('logo1.png', 100, 100);
        $file2 = UploadedFile::fake()->image('logo2.png', 100, 100);

        $response1 = $this->actingAs($this->admin)->postJson('/api/admin/settings/logo', [
            'logo' => $file1,
        ]);
        $response1->assertStatus(200);
        $path1 = $response1->json('logo_path');

        $response2 = $this->actingAs($this->admin)->postJson('/api/admin/settings/logo', [
            'logo' => $file2,
        ]);
        $response2->assertStatus(200);
        $path2 = $response2->json('logo_path');

        Storage::disk('public')->assertMissing($path1);
        Storage::disk('public')->assertExists($path2);
    }
}