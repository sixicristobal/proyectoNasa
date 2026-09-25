<?php

namespace Database\Factories;

use App\Models\NasaAgroData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NasaAgroData>
 */
class NasaAgroDataFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lat' => fake()->latitude(-27.5, -22.0),
            'lon' => fake()->longitude(-62.7, -54.3),
            'date' => fake()->date(),
            'temp' => fake()->randomFloat(2, -2, 42),
            'precip' => fake()->randomFloat(2, 0, 150),
            'humidity' => fake()->randomFloat(2, 10, 100),
            'ndvi_value' => fake()->randomFloat(4, -0.2, 1),
        ];
    }
}
