<?php

namespace Tests;

use App\Dominio\Sistema\Acceso\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;

abstract class TestCase extends BaseTestCase
{
    /** Symfony guarda los hosts de confianza en una variable estática: que no pasen de un test a otro. */
    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);

        parent::tearDown();
    }

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
