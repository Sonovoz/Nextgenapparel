<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TallaFactory extends Factory
{
    public function definition(): array
    {
        return ['nombre' => fake()->unique()->bothify('T-###'), 'orden' => 0];
    }
}
