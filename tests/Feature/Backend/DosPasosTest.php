<?php

namespace Tests\Feature\Backend;

use App\Dominio\Sistema\Acceso\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Verificación en dos pasos del equipo (paso 10.1, D186), portada de Clave de Alma. */
class DosPasosTest extends TestCase
{
    use RefreshDatabase;

    public function test_en_el_servidor_el_equipo_no_entra_al_panel_sin_configurarla(): void
    {
        config()->set('seguridad.dos_pasos_obligatorios', true);
        $usuario = $this->usuarioConRol(Rol::GestorTienda);

        $this->actingAs($usuario)->get('/admin')->assertRedirect();

        config()->set('seguridad.dos_pasos_obligatorios', false);
        $this->actingAs($usuario)->get('/admin')->assertOk();
    }

    public function test_la_clave_se_guarda_cifrada_y_el_comando_la_quita(): void
    {
        $usuario = $this->usuarioConRol(Rol::Administrador, ['email' => 'ana@nextgenapparel.test']);
        $usuario->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $usuario->saveAppAuthenticationRecoveryCodes(['uno', 'dos']);

        $guardado = (string) DB::table('users')->where('id', $usuario->id)->value('app_authentication_secret');
        $this->assertNotSame('JBSWY3DPEHPK3PXP', $guardado);
        $this->assertSame('JBSWY3DPEHPK3PXP', $usuario->fresh()->getAppAuthenticationSecret());
        $this->assertArrayNotHasKey('app_authentication_secret', $usuario->fresh()->toArray());

        $this->artisan('usuarios:quitar-dos-pasos', ['correo' => 'ANA@nextgenapparel.test'])->assertSuccessful();
        $this->assertNull($usuario->fresh()->getAppAuthenticationSecret());
        $this->artisan('usuarios:quitar-dos-pasos', ['correo' => 'nadie@x.es'])->assertFailed();
    }
}
