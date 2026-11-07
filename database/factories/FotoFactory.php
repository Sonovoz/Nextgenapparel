<?php

namespace Database\Factories;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\TipoFoto;
use App\Models\Color;
use App\Models\Prenda;
use Illuminate\Database\Eloquent\Factories\Factory;

class FotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prenda_id' => Prenda::factory(),
            'color_id' => Color::factory(),
            'tipo' => TipoFoto::Maqueta,
            'cara' => Cara::Delantera,
            'ruta' => 'catalogo/'.fake()->uuid().'.webp',
        ];
    }
}
