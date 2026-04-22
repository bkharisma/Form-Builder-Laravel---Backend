<?php

namespace Database\Factories;

use Modules\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    public function definition(): array
    {
        return [
            'form_config_id' => null,
            'dynamic_data' => [
                'field_name' => fake()->name(),
                'field_email' => fake()->email(),
                'field_subject' => fake()->randomElement(['General Inquiry', 'Feedback', 'Support', 'Other']),
            ],
            'submitted_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
