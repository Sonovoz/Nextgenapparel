<?php

namespace Tests\Feature\Sistema;

use App\Dominio\Sistema\Acceso\Rol;
use App\Dominio\Sistema\PuestaEnMarcha\Paso;
use App\Dominio\Sistema\PuestaEnMarcha\PuestaEnMarcha;
use App\Filament\Pages\AjustesFacturacion;
use App\Filament\Pages\PuestaEnMarcha as Pagina;
use App\Models\Ajuste;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Herramientas → Puesta en marcha (D196): cada paso se marca solo con los datos reales. */
class PuestaEnMarchaTest extends TestCase
{
    use RefreshDatabase;

    private function paso(string $titulo): Paso
    {
        return collect(PuestaEnMarcha::pasos())->sole(fn (Paso $p) => $p->titulo === $titulo);
    }

    public function test_cada_paso_se_marca_al_hacerlo(): void
    {
        $antes = PuestaEnMarcha::pendientes();
        $this->assertFalse($this->paso('Datos fiscales')->hecho);
        $this->assertFalse($this->paso('Redsys real')->hecho);

        Ajuste::guardar('facturacion.emisor', ['razon_social' => 'Nextgenapparel SL', 'nif' => 'B12345678', 'direccion' => 'Calle Mayor 1']);
        config()->set('pagos.pasarela', 'redsys');
        config()->set('pagos.redsys', ['comercio' => '999008881', 'clave' => 'secreta', 'entorno' => 'live']);

        $this->assertTrue($this->paso('Datos fiscales')->hecho);
        $this->assertTrue($this->paso('Redsys real')->hecho);
        $this->assertSame($antes - 2, PuestaEnMarcha::pendientes());
        $this->assertTrue($this->paso('Asistente con IA')->opcional); // no cuenta como pendiente
    }

    public function test_la_pagina_enlaza_donde_se_hace_sin_mostrar_secretos(): void
    {
        config()->set('pagos.redsys', ['comercio' => '999008881', 'clave' => 'clave-muy-secreta', 'entorno' => 'test']);

        $this->actingAs($this->usuarioConRol(Rol::Administrador))->get(Pagina::getUrl(panel: 'admin'))->assertOk()
            ->assertSee('Datos fiscales')->assertSee(AjustesFacturacion::getUrl(panel: 'admin'))->assertSee('Pendiente')->assertDontSee('clave-muy-secreta');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda))->get(Pagina::getUrl(panel: 'admin'))->assertForbidden();
    }
}
