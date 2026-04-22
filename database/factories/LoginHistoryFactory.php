<?php

namespace Database\Factories;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginHistory>
 */
class LoginHistoryFactory extends Factory
{
    protected $model = LoginHistory::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'login_at' => now(),
            'success' => true,
            'failure_reason' => null,
        ];
    }

    public function failed(string $reason = 'invalid_2fa'): static
    {
        return $this->state(fn(array $attributes) => [
            'success' => false,
            'failure_reason' => $reason,
        ]);
    }
}