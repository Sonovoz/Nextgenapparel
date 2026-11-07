<?php

namespace Tests;

use App\Dominio\Sistema\Acceso\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Usuario del backend con el rol indicado (por defecto, superadministrador).
     *
     * @param  array<string, mixed>  $atributos
     */
    protected function usuarioConRol(Rol $rol = Rol::Superadministrador, array $atributos = []): User
    {
        return tap(User::factory()->create($atributos), fn (User $usuario) => $usuario->assignRole($rol->value));
    }
}
