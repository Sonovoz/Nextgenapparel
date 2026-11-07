<?php

namespace Database\Factories;

use App\Models\Familia;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrendaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'familia_id' => Familia::factory(),
            'nombre' => ['es' => 'Camiseta Essential', 'en' => 'Essential T-shirt'],
            'slug' => ['es' => 'camiseta-essential-'.fake()->unique()->numberBetween(1, 99999), 'en' => 'essential-t-shirt-'.fake()->unique()->numberBetween(1, 99999)],
            'gramaje' => 180,
        ];
    }
}
