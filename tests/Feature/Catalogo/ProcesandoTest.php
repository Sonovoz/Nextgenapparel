<?php

namespace Tests\Feature\Catalogo;

use Database\Seeders\EjemplosCatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Capa «Procesando» en toda la tienda y tallas de la prenda como información, no como botones. */
class ProcesandoTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_tienda_lleva_la_capa_procesando(): void
    {
        $this->get('/es')->assertOk()
            ->assertSee('id="procesando"', false)
            ->assertSee('window.Procesando', false);
    }

    public function test_la_ficha_muestra_las_tallas_como_texto_y_el_configurador_avisa_al_preparar_el_diseno(): void
    {
        $this->seed(EjemplosCatalogoSeeder::class);

        $html = $this->get('/es/camisetas/camiseta-essential')->assertOk()->getContent();

        $this->assertStringContainsString('class="tallas-texto"', $html);
        $this->assertStringNotContainsString('class="chip"', $html);
        $this->assertStringContainsString('procesando_diseno', $html); // texto de la capa al pasar a tallas
    }
}
