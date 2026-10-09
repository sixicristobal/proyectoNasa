<?php

namespace Database\Factories;

use App\Models\Alert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'risk_type' => fake()->randomElement(['sequia', 'helada', 'lluvia_extrema']),
            'message' => fake()->sentence(12),
            'data_date' => fake()->date(),
            'source' => 'laravel-ollama',
        ];
    }
}
