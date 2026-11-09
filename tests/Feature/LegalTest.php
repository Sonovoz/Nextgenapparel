<?php

namespace Tests\Feature;

use App\Dominio\Legal\GenerarTextosLegales;
use App\Dominio\Legal\PaginaLegal;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\AjustesLegales;
use App\Models\Ajuste;
use App\Models\TextoLegal;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/** Textos legales generados (paso 8.1, D183). */
class LegalTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_generan_con_los_datos_del_sistema_y_marcan_lo_que_falta(): void
    {
        app(GenerarTextosLegales::class)();
        $this->assertSame(10, TextoLegal::count()); // 5 textos × es y en
        $condiciones = TextoLegal::actual(PaginaLegal::Condiciones);
        $this->assertContains('razón social', $condiciones->pendientes);
        $this->assertStringContainsString('103.c', $condiciones->contenido);

        Ajuste::guardar('facturacion.emisor', ['razon_social' => 'DTI Textil SL', 'nif' => 'B12345674', 'direccion' => 'Calle Industria 4', 'codigo_postal' => '28001', 'localidad' => 'Madrid', 'correo' => 'hola@dti.es']);
        $this->assertSame(10, app(GenerarTextosLegales::class)()); // todas cambian: versión nueva
        $this->assertSame(0, app(GenerarTextosLegales::class)()); // sin cambios: ninguna
        $condiciones = TextoLegal::actual(PaginaLegal::Condiciones);
        $this->assertSame([2, null], [$condiciones->version, $condiciones->pendientes]);
        $this->assertStringContainsString('DTI Textil SL, NIF B12345674', $condiciones->contenido);
        $this->assertStringContainsString('DTI Textil SL, tax ID B12345674', TextoLegal::actual(PaginaLegal::Condiciones, 'en')->contenido);
        $this->assertSame('condiciones-de-venta-en v2', TextoLegal::actual(PaginaLegal::Condiciones, 'en')->etiqueta());
    }

    public function test_un_texto_no_se_modifica_ni_se_borra(): void
    {
        app(GenerarTextosLegales::class)();
        $texto = TextoLegal::firstOrFail();

        $this->expectException(LogicException::class);
        $texto->update(['contenido' => 'Otro']);
    }

    public function test_la_pagina_publica_muestra_el_texto_y_si_es_borrador(): void
    {
        $this->get('/es/legal/privacidad')->assertOk()->assertSee('Política de privacidad')->assertSee(__('legal.borrador'))
            ->assertSee('/es/legal/condiciones-de-venta', false); // enlaces del pie
        $this->get('/en/legal/privacidad')->assertOk()->assertSee('Privacy policy')->assertSee('Spanish Data Protection Agency')->assertDontSee('Responsable del tratamiento');
        $this->get('/es/legal/otra-cosa')->assertNotFound();
    }

    public function test_la_asesoria_marca_la_version_como_revisada(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::Administrador, ['name' => 'Ana Asesora']));
        Livewire::test(AjustesLegales::class)->callAction('regenerar');
        $texto = TextoLegal::actual(PaginaLegal::Cookies);

        Livewire::test(AjustesLegales::class)->callTableAction('revisado', $texto);

        $this->assertSame('Ana Asesora', $texto->fresh()->revisado_por);
        $this->get('/es/legal/cookies')->assertDontSee(__('legal.borrador'));
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda))->get(AjustesLegales::getUrl())->assertForbidden();
    }
}
