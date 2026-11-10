<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->words(3, true), 'description' => fake()->sentence(), 'duration_minutes' => 45, 'price_cents' => 2800];
    }
}
