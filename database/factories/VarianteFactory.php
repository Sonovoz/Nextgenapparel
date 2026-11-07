<?php

namespace Database\Factories;

use App\Models\Color;
use App\Models\Prenda;
use App\Models\Talla;
use Illuminate\Database\Eloquent\Factories\Factory;

class VarianteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prenda_id' => Prenda::factory(),
            'color_id' => Color::factory(),
            'talla_id' => Talla::factory(),
            'sku' => 'SKU-'.fake()->unique()->numerify('######'),
            'precio_base' => 400,
            'coste' => 250,
            'peso_gramos' => 180,
            'stock' => 10,
        ];
    }
}
