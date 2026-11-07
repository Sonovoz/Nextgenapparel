<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FamiliaFactory extends Factory
{
    public function definition(): array
    {
        $nombre = fake()->unique()->randomElement(['Camisetas', 'Polos', 'Sudaderas', 'Tirantes', 'Chaquetas']);

        return [
            'nombre' => ['es' => $nombre, 'en' => $nombre.' (en)'],
            'slug' => ['es' => str($nombre)->slug(), 'en' => str($nombre)->slug().'-en'],
            'plazo_base_dias' => 2,
        ];
    }
}
