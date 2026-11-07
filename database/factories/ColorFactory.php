<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ColorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => ['es' => fake()->unique()->colorName(), 'en' => fake()->colorName()],
            'codigo_hex' => fake()->hexColor(),
        ];
    }
}
