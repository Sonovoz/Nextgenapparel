<?php

namespace Tests\Feature\Consola;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CambiarContrasenaTest extends TestCase
{
    use RefreshDatabase;

    private const NUEVA = 'Contraseña nueva (mínimo 12 caracteres)';

    private const REPETIDA = 'Repite la contraseña nueva';

    public function test_cambia_la_contrasena_cifrada_y_anula_el_recordarme(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@ejemplo.es', 'password' => 'la-clave-antigua-1']);
        $usuario->forceFill(['remember_token' => 'token-viejo'])->save();

        $this->artisan('usuarios:cambiar-contrasena', ['correo' => 'ANA@ejemplo.es'])
            ->expectsQuestion(self::NUEVA, 'una-clave-nueva-123')
            ->expectsQuestion(self::REPETIDA, 'una-clave-nueva-123')
            ->assertSuccessful();

        $usuario->refresh();
        $this->assertTrue(Hash::check('una-clave-nueva-123', $usuario->password));
        $this->assertNotSame('token-viejo', $usuario->remember_token);
        $this->assertDatabaseHas('activity_log', ['subject_id' => $usuario->id, 'description' => 'Contraseña cambiada desde el servidor']);
        $this->assertStringNotContainsString('una-clave-nueva', (string) json_encode(DB::table('activity_log')->get()));
    }

    public function test_no_cambia_nada_si_no_coinciden_o_es_corta(): void
    {
        User::factory()->create(['email' => 'ana@ejemplo.es', 'password' => 'la-clave-antigua-1']);

        $this->artisan('usuarios:cambiar-contrasena', ['correo' => 'ana@ejemplo.es'])
            ->expectsQuestion(self::NUEVA, 'una-clave-nueva-123')
            ->expectsQuestion(self::REPETIDA, 'otra-clave-nueva-123')
            ->assertFailed();

        $this->artisan('usuarios:cambiar-contrasena', ['correo' => 'ana@ejemplo.es'])
            ->expectsQuestion(self::NUEVA, 'corta')
            ->expectsQuestion(self::REPETIDA, 'corta')
            ->assertFailed();

        $this->assertTrue(Hash::check('la-clave-antigua-1', User::firstOrFail()->password));
    }

    public function test_avisa_si_el_correo_no_existe(): void
    {
        $this->artisan('usuarios:cambiar-contrasena', ['correo' => 'nadie@ejemplo.es'])->assertFailed();
    }
}
