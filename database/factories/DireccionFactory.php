<?php

namespace Database\Factories;

use App\Dominio\Clientes\TipoDireccion;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

class DireccionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'tipo' => TipoDireccion::Envio,
            'nombre' => fake()->name(),
            'direccion' => 'Calle Mayor 1',
            'codigo_postal' => '28013',
            'localidad' => 'Madrid',
            'provincia' => 'Madrid',
            'pais' => 'ES',
        ];
    }
}
