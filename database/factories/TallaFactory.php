<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TallaFactory extends Factory
{
    public function definition(): array
    {
        return ['nombre' => fake()->unique()->randomElement(['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL']), 'orden' => 0];
    }
}
