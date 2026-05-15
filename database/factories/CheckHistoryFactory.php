<?php

namespace Database\Factories;

use App\Models\CheckHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckHistory>
 */
class CheckHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'monitor_id' => \App\Models\Monitor::factory(),
            'status_code' => 200,
            'response_time_ms' => $this->faker->numberBetween(100, 1000),
            'is_up' => true,
            'checked_at' => now(),
        ];
    }
}
