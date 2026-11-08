<?php

namespace Tests\Feature\Idiomas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdiomasTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_raiz_redirige_al_espanol_si_el_navegador_no_indica_idioma(): void
    {
        // El cliente de pruebas envía «en-us» por defecto: se vacía para simular
        // un navegador sin preferencia de idioma.
        $this->get('/', ['Accept-Language' => ''])->assertRedirect('/es');
    }

    public function test_la_raiz_da_prioridad_al_idioma_preferido_del_navegador(): void
    {
        $this->get('/', ['Accept-Language' => 'en;q=0.5,es;q=0.9'])->assertRedirect('/es');
    }

    public function test_la_raiz_respeta_el_idioma_del_navegador_si_esta_disponible(): void
    {
        $this->get('/', ['Accept-Language' => 'en-GB,en;q=0.9'])->assertRedirect('/en');
    }

    public function test_un_idioma_del_navegador_no_disponible_cae_al_espanol(): void
    {
        $this->get('/', ['Accept-Language' => 'fr-FR,fr;q=0.9'])->assertRedirect('/es');
    }

    public function test_la_portada_en_espanol(): void
    {
        $this->get('/es')
            ->assertOk()
            ->assertSee('<html lang="es">', false)
            ->assertSee('Creamos marcas que comunican');
    }

    public function test_la_portada_en_ingles(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('We create brands that speak');
    }

    public function test_un_idioma_no_disponible_devuelve_404(): void
    {
        $this->get('/fr')->assertNotFound();
    }

    public function test_la_portada_enlaza_las_versiones_de_cada_idioma(): void
    {
        $this->get('/es')
            ->assertSee('hreflang="es" href="'.url('/es').'"', false)
            ->assertSee('hreflang="en" href="'.url('/en').'"', false)
            ->assertSee('hreflang="x-default" href="'.url('/es').'"', false);
    }

    public function test_la_portada_queda_fuera_del_indice_mientras_esta_en_construccion(): void
    {
        $this->get('/es')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_el_selector_marca_el_idioma_actual(): void
    {
        $this->get('/en')->assertSeeInOrder(['lang="en"', 'aria-current="page"', 'English'], false);
    }

    public function test_cada_idioma_tiene_los_mismos_textos(): void
    {
        $es = require lang_path('es/portada.php');
        $en = require lang_path('en/portada.php');

        $this->assertSame(array_keys($es), array_keys($en));
        $this->assertNotContains('', $es);
        $this->assertNotContains('', $en);
    }

    public function test_la_zona_horaria_es_la_de_madrid(): void
    {
        $this->assertSame('Europe/Madrid', config('app.timezone'));
    }
}
