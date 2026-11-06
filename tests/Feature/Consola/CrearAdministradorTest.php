<?php

namespace Tests\Feature\Consola;

use App\Dominio\Sistema\Acceso\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrearAdministradorTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_el_administrador_con_contrasena_cifrada(): void
    {
        $this->artisan('nga:crear-administrador', [
            '--nombre' => 'Ana Pérez',
            '--email' => 'ana@ejemplo.es',
            '--password' => 'una-clave-larga-123',
        ])->assertSuccessful();

        $usuario = User::where('email', 'ana@ejemplo.es')->firstOrFail();

        $this->assertSame('Ana Pérez', $usuario->name);
        $this->assertNotSame('una-clave-larga-123', $usuario->password);
        $this->assertTrue(Hash::check('una-clave-larga-123', $usuario->password));
        $this->assertTrue($usuario->esSuperadministrador());
    }

    public function test_crea_un_usuario_con_otro_rol(): void
    {
        $this->artisan('nga:crear-administrador', [
            '--nombre' => 'Luis',
            '--email' => 'luis@ejemplo.es',
            '--password' => 'una-clave-larga-123',
            '--rol' => 'produccion',
        ])->assertSuccessful();

        $this->assertSame(Rol::Produccion, User::where('email', 'luis@ejemplo.es')->firstOrFail()->rol());
    }

    public function test_rechaza_un_rol_que_no_existe(): void
    {
        $this->artisan('nga:crear-administrador', [
            '--nombre' => 'Luis',
            '--email' => 'luis@ejemplo.es',
            '--password' => 'una-clave-larga-123',
            '--rol' => 'jefe',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_pregunta_los_datos_si_no_se_indican(): void
    {
        $this->artisan('nga:crear-administrador')
            ->expectsQuestion('Nombre completo', 'Ana Pérez')
            ->expectsQuestion('Correo electrónico', 'ana@ejemplo.es')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'una-clave-larga-123')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'ana@ejemplo.es']);
    }

    public function test_rechaza_una_contrasena_corta(): void
    {
        $this->artisan('nga:crear-administrador', [
            '--nombre' => 'Ana',
            '--email' => 'ana@ejemplo.es',
            '--password' => 'corta',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_rechaza_un_correo_repetido(): void
    {
        User::factory()->create(['email' => 'ana@ejemplo.es']);

        $this->artisan('nga:crear-administrador', [
            '--nombre' => 'Ana',
            '--email' => 'ana@ejemplo.es',
            '--password' => 'una-clave-larga-123',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_rechaza_un_correo_no_valido(): void
    {
        $this->artisan('nga:crear-administrador', [
            '--nombre' => 'Ana',
            '--email' => 'no-es-un-correo',
            '--password' => 'una-clave-larga-123',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
