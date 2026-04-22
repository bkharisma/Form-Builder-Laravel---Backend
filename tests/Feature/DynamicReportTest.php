<?php

namespace Tests\Feature;

use Modules\FormBuilder\Models\FormConfig;
use Modules\FormBuilder\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function createActiveFormConfig(): FormConfig
    {
        return FormConfig::create([
            'title' => 'Active Form',
            'status' => 'published',
            'fields' => [
                [
                    'id' => 'field_subject',
                    'type' => 'select',
                    'label' => 'Subject',
                    'required' => true,
                    'enabled' => true,
                    'options' => [
                        ['label' => 'General Inquiry', 'value' => 'general_inquiry'],
                        ['label' => 'Feedback', 'value' => 'feedback'],
                        ['label' => 'Support', 'value' => 'support'],
                    ],
                ],
                [
                    'id' => 'field_organization',
                    'type' => 'text',
                    'label' => 'Organization',
                    'required' => true,
                    'enabled' => true,
                ],
                [
                    'id' => 'field_vip',
                    'type' => 'checkbox',
                    'label' => 'VIP',
                    'required' => false,
                    'enabled' => true,
                ],
                [
                    'id' => 'field_date',
                    'type' => 'date',
                    'label' => 'Date',
                    'required' => true,
                    'enabled' => true,
                ],
                [
                    'id' => 'field_notes',
                    'type' => 'textarea',
                    'label' => 'Notes',
                    'required' => false,
                    'enabled' => true,
                ],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    public function test_returns_chart_compatible_fields_list(): void
    {
        $config = $this->createActiveFormConfig();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/dynamic-fields?form_slug=' . $config->slug);

        $response->assertStatus(200);

        $fields = $response->json('fields');
        $this->assertCount(4, $fields);

        $fieldIds = collect($fields)->pluck('id')->all();
        $this->assertContains('field_subject', $fieldIds);
        $this->assertContains('field_organization', $fieldIds);
        $this->assertContains('field_vip', $fieldIds);
        $this->assertContains('field_date', $fieldIds);
        $this->assertNotContains('field_notes', $fieldIds);

        $subjectField = collect($fields)->first(fn($f) => $f['id'] === 'field_subject');
        $this->assertEquals('select', $subjectField['type']);
        $this->assertEquals('Subject', $subjectField['label']);
        $this->assertArrayHasKey('options', $subjectField);

        $textField = collect($fields)->first(fn($f) => $f['id'] === 'field_organization');
        $this->assertArrayNotHasKey('options', $textField);
    }

    public function test_returns_404_when_no_form_config_exists(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/dynamic-fields?form_slug=nonexistent');

        $response->assertStatus(404);
    }

    public function test_aggregates_select_field_values_with_counts(): void
    {
        $config = $this->createActiveFormConfig();

        Submission::factory()->count(3)->create([
            'form_config_id' => $config->id,
            'dynamic_data' => ['field_subject' => 'general_inquiry', 'field_organization' => 'ACME'],
            'submitted_at' => now()->subDays(5),
        ]);
        Submission::factory()->count(2)->create([
            'form_config_id' => $config->id,
            'dynamic_data' => ['field_subject' => 'feedback', 'field_organization' => 'ACME'],
            'submitted_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/dynamic?form_slug=' . $config->slug . '&field_id=field_subject');

        $response->assertStatus(200);
        $response->assertJsonPath('field.id', 'field_subject');
        $response->assertJsonPath('field.type', 'select');
        $response->assertJsonPath('field.label', 'Subject');

        $data = $response->json('data');
        $this->assertCount(2, $data);

        $inquiryEntry = collect($data)->first(fn($item) => $item['name'] === 'general_inquiry');
        $this->assertEquals(3, $inquiryEntry['count']);

        $feedbackEntry = collect($data)->first(fn($item) => $item['name'] === 'feedback');
        $this->assertEquals(2, $feedbackEntry['count']);
    }

    public function test_aggregates_checkbox_field_as_yes_no(): void
    {
        $config = $this->createActiveFormConfig();

        Submission::factory()->count(4)->create([
            'form_config_id' => $config->id,
            'dynamic_data' => ['field_vip' => true, 'field_subject' => 'general_inquiry'],
            'submitted_at' => now()->subDays(5),
        ]);
        Submission::factory()->count(2)->create([
            'form_config_id' => $config->id,
            'dynamic_data' => ['field_vip' => false, 'field_subject' => 'feedback'],
            'submitted_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/dynamic?form_slug=' . $config->slug . '&field_id=field_vip');

        $response->assertStatus(200);
        $response->assertJsonPath('field.id', 'field_vip');
        $response->assertJsonPath('field.type', 'checkbox');

        $data = $response->json('data');
        $this->assertCount(2, $data);

        $yesEntry = collect($data)->first(fn($item) => $item['name'] === 'Yes');
        $noEntry = collect($data)->first(fn($item) => $item['name'] === 'No');

        $this->assertEquals(4, $yesEntry['count']);
        $this->assertEquals(2, $noEntry['count']);
    }

    public function test_aggregates_date_field_as_time_series(): void
    {
        $config = $this->createActiveFormConfig();

        Submission::factory()->count(3)->create([
            'form_config_id' => $config->id,
            'dynamic_data' => ['field_date' => '2026-03-01', 'field_subject' => 'general_inquiry'],
            'submitted_at' => now()->subDays(5),
        ]);
        Submission::factory()->count(2)->create([
            'form_config_id' => $config->id,
            'dynamic_data' => ['field_date' => '2026-03-05', 'field_subject' => 'general_inquiry'],
            'submitted_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/dynamic?form_slug=' . $config->slug . '&field_id=field_date');

        $response->assertStatus(200);
        $response->assertJsonPath('field.id', 'field_date');
        $response->assertJsonPath('field.type', 'date');

        $data = $response->json('data');
        $this->assertCount(2, $data);

        $firstEntry = $data[0];
        $this->assertArrayHasKey('date', $firstEntry);
        $this->assertArrayHasKey('count', $firstEntry);
        $this->assertEquals('2026-03-01', $firstEntry['date']);
        $this->assertEquals(3, $firstEntry['count']);

        $secondEntry = $data[1];
        $this->assertEquals('2026-03-05', $secondEntry['date']);
        $this->assertEquals(2, $secondEntry['count']);
    }

    public function test_aggregates_text_field_with_top_n_limit(): void
    {
        $config = $this->createActiveFormConfig();

        for ($i = 0; $i < 55; $i++) {
            Submission::factory()->create([
                'form_config_id' => $config->id,
                'dynamic_data' => ['field_organization' => "Organization $i", 'field_subject' => 'general_inquiry'],
                'submitted_at' => now()->subDays(5),
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/dynamic?form_slug=' . $config->slug . '&field_id=field_organization');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(50, $data);
    }

    public function test_respects_date_range_filtering(): void
    {
        $config = $this->createActiveFormConfig();

        Submission::factory()->create([
            'form_config_id' => $config->id,
            'dynamic_data' => ['field_subject' => 'general_inquiry'],
            'submitted_at' => '2026-01-15 10:00:00',
        ]);
        Submission::factory()->create([
            'form_config_id' => $config->id,
            'dynamic_data' => ['field_subject' => 'feedback'],
            'submitted_at' => '2026-02-15 10:00:00',
        ]);
        Submission::factory()->create([
            'form_config_id' => $config->id,
            'dynamic_data' => ['field_subject' => 'support'],
            'submitted_at' => '2026-03-15 10:00:00',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/dynamic?form_slug=' . $config->slug . '&field_id=field_subject&date_from=2026-02-01&date_to=2026-02-28');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('feedback', $data[0]['name']);
    }

    public function test_returns_422_for_non_chart_compatible_field_type(): void
    {
        $config = $this->createActiveFormConfig();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/dynamic?form_slug=' . $config->slug . '&field_id=field_notes');

        $response->assertStatus(422);
    }

    public function test_returns_422_for_unknown_field_id(): void
    {
        $config = $this->createActiveFormConfig();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/dynamic?form_slug=' . $config->slug . '&field_id=nonexistent_field');

        $response->assertStatus(422);
    }

    public function test_returns_empty_data_when_no_submissions_match(): void
    {
        $config = $this->createActiveFormConfig();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/dynamic?form_slug=' . $config->slug . '&field_id=field_subject&date_from=2099-01-01&date_to=2099-12-31');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(0, $data);
    }

    public function test_aggregate_returns_404_when_no_form_config(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/dynamic?form_slug=nonexistent&field_id=field_subject');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_access_dynamic_fields(): void
    {
        $response = $this->getJson('/api/admin/reports/dynamic-fields?form_slug=test');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_aggregate(): void
    {
        $response = $this->getJson('/api/admin/reports/dynamic?form_slug=test&field_id=field_subject');
        $response->assertStatus(401);
    }

    public function test_non_admin_user_cannot_access_dynamic_fields(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $config = $this->createActiveFormConfig();

        $response = $this->actingAs($user)
            ->getJson('/api/admin/reports/dynamic-fields?form_slug=' . $config->slug);

        $response->assertStatus(403);
    }

    public function test_non_admin_user_cannot_access_aggregate(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $config = $this->createActiveFormConfig();

        $response = $this->actingAs($user)
            ->getJson('/api/admin/reports/dynamic?form_slug=' . $config->slug . '&field_id=field_subject');

        $response->assertStatus(403);
    }

    public function test_aggregate_validates_field_id_is_required(): void
    {
        $config = $this->createActiveFormConfig();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/dynamic?form_slug=' . $config->slug);

        $response->assertStatus(422);
    }

    public function test_aggregate_validates_date_from_and_date_to(): void
    {
        $config = $this->createActiveFormConfig();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/dynamic?form_slug=' . $config->slug . '&field_id=field_subject&date_from=2026-03-01&date_to=2026-01-01');

        $response->assertStatus(422);
    }
}