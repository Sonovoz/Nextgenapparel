<?php

namespace Database\Factories;

use App\Dominio\Catalogo\Cara;
use Illuminate\Database\Eloquent\Factories\Factory;

class ZonaImpresionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clave' => fake()->unique()->slug(2),
            'nombre' => ['es' => 'Frontal', 'en' => 'Front'],
            'cara' => Cara::Delantera,
            'ancho_max_mm' => 280,
            'alto_max_mm' => 380,
        ];
    }
}
