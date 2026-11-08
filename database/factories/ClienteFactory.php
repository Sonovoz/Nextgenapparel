<?php

namespace Database\Factories;

use App\Dominio\Clientes\TipoCliente;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClienteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'correo' => fake()->unique()->safeEmail(),
            'tipo' => TipoCliente::Particular,
            'nombre' => fake()->name(),
            'idioma' => 'es',
        ];
    }
}
