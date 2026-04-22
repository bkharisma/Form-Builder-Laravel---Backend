<?php

namespace Tests\Feature;

use Modules\FormBuilder\Models\FormConfig;
use App\Models\User;
use App\Services\TurnstileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_submission_requires_turnstile_token(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $config = FormConfig::create([
            'title' => 'Test Form',
            'status' => 'published',
            'fields' => [
                ['id' => 'field_name', 'type' => 'text', 'label' => 'Full Name', 'required' => true, 'enabled' => true, 'order' => 0],
            ],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_name' => 'John Doe',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cf_turnstile_response']);
    }

    public function test_submission_fails_with_invalid_turnstile_token(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $config = FormConfig::create([
            'title' => 'Test Form',
            'status' => 'published',
            'fields' => [
                ['id' => 'field_name', 'type' => 'text', 'label' => 'Full Name', 'required' => true, 'enabled' => true, 'order' => 0],
            ],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->with('invalid-token', \Mockery::type('string'))
            ->andReturn(false);
        $mockService->shouldReceive('getErrorMessage')
            ->once()
            ->andReturn('Verification failed. Please try again.');

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_name' => 'John Doe',
            ],
            'cf_turnstile_response' => 'invalid-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cf_turnstile_response']);
    }

    public function test_submission_succeeds_with_valid_turnstile_token(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $config = FormConfig::create([
            'title' => 'Test Form',
            'status' => 'published',
            'fields' => [
                ['id' => 'field_name', 'type' => 'text', 'label' => 'Full Name', 'required' => true, 'enabled' => true, 'order' => 0],
                ['id' => 'field_email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'enabled' => true, 'order' => 1],
            ],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->with('valid-token', \Mockery::type('string'))
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_name' => 'John Doe',
                'field_email' => 'john@example.com',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'dynamic_data' => [
                    'field_name' => 'John Doe',
                    'field_email' => 'john@example.com',
                ],
            ]);

        $this->assertDatabaseHas('submissions', [
            'dynamic_data->field_name' => 'John Doe',
        ]);
    }

    public function test_submission_validates_required_dynamic_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $config = FormConfig::create([
            'title' => 'Test Form',
            'status' => 'published',
            'fields' => [
                ['id' => 'field_name', 'type' => 'text', 'label' => 'Full Name', 'required' => true, 'enabled' => true, 'order' => 0],
                ['id' => 'field_email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'enabled' => true, 'order' => 1],
            ],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dynamic_data.field_name', 'dynamic_data.field_email']);
    }

    public function test_submission_requires_dynamic_data_array(): void
    {
        $response = $this->postJson('/api/submissions/some-slug', [
            'cf_turnstile_response' => 'some-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dynamic_data']);
    }
}