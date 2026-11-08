<?php

namespace Tests\Feature\Catalogo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Sobre nosotros (D145). */
class NosotrosTest extends TestCase
{
    use RefreshDatabase;

    public static function idiomas(): array
    {
        return [
            'español' => ['/es/sobre-nosotros', '/en/about-us', 'es', 'Una gran empresa detrás de este proyecto'],
            'inglés' => ['/en/about-us', '/es/sobre-nosotros', 'en', 'A great company behind this project'],
        ];
    }

    #[DataProvider('idiomas')]
    public function test_la_pagina_se_muestra_en_cada_idioma_con_su_url_y_su_alternativa(string $url, string $alterna, string $idioma, string $lema): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee('<html lang="'.$idioma.'">', false)
            ->assertSee('<h1 class="titulo-seccion" id="titulo-nosotros">'.e($lema).'</h1>', false)
            ->assertSee('<link rel="canonical" href="'.url($url).'">', false)
            ->assertSee('hreflang="'.($idioma === 'es' ? 'en' : 'es').'" href="'.url($alterna).'"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_la_url_de_otro_idioma_redirige_a_la_del_idioma_de_la_pagina(): void
    {
        $this->get('/es/about-us')->assertRedirect('/es/sobre-nosotros')->assertStatus(301);
        $this->get('/en/sobre-nosotros')->assertRedirect('/en/about-us')->assertStatus(301);
    }

    public function test_el_menu_y_el_pie_enlazan_la_pagina_y_la_marcan_como_actual(): void
    {
        $this->get('/es')->assertSee('href="'.url('/es/sobre-nosotros').'"', false);
        $this->assertMatchesRegularExpression(
            '#<li><a href="'.preg_quote(url('/es/sobre-nosotros'), '#').'"\s+aria-current="page"\s*>Sobre nosotros</a>#',
            $this->get('/es/sobre-nosotros')->getContent(),
        );
    }

    public function test_las_imagenes_llevan_la_fecha_del_archivo_para_no_servir_la_antigua_de_la_cache(): void
    {
        // El cráneo se sustituyó por el dragón con el mismo nombre y el navegador seguía mostrando el cráneo.
        $macro = asset('img/portada/macro.webp').'?v='.filemtime(public_path('img/portada/macro.webp'));

        $this->get('/es')->assertSee('src="'.$macro.'"', false);
        $this->get('/es/sobre-nosotros')->assertSee('src="'.$macro.'"', false);
    }

    public function test_el_sitemap_incluye_la_pagina(): void
    {
        $this->get('/sitemap-en.xml')->assertOk()->assertSee('<loc>'.url('/en/about-us').'</loc>', false);
    }
}
