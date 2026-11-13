<?php

namespace Tests\Feature\Sistema;

use App\Dominio\Sistema\Acceso\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El crédito del desarrollo (Minimedia, www.minimedia.es) sale en la tienda y en el backend. */
class CreditoMinimediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_tienda_y_el_backend_dicen_que_lo_ha_desarrollado_minimedia(): void
    {
        $this->get('/es')->assertOk()->assertSee('Desarrollado por')->assertSee('https://www.minimedia.es', false);
        $this->get('/en')->assertOk()->assertSee('Developed by');

        $usuario = $this->usuarioConRol(Rol::Superadministrador);
        $login = $this->get('/admin/login')->assertOk()->getContent();
        $this->assertSame(1, substr_count((string) $login, 'href="https://www.minimedia.es"'));
        $this->actingAs($usuario)->get('/admin')->assertOk()->assertSee('https://www.minimedia.es', false);
    }
}
