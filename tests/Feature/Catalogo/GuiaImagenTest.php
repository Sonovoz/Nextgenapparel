<?php

namespace Tests\Feature\Catalogo;

use App\Models\Ajuste;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** D249: guía «Prepara tu imagen para imprimir». */
class GuiaImagenTest extends TestCase
{
    use RefreshDatabase;

    public static function idiomas(): array
    {
        return [
            'español' => ['/es/prepara-tu-imagen', '/en/prepare-your-image', 'es', 'Lo que en el móvil se ve bien, puede no servir para imprimir', '¿Qué es un PNG?'],
            'inglés' => ['/en/prepare-your-image', '/es/prepara-tu-imagen', 'en', 'What looks good on your phone may not work for printing', 'What is a PNG?'],
        ];
    }

    #[DataProvider('idiomas')]
    public function test_la_guia_se_muestra_en_cada_idioma_con_su_url_y_su_alternativa(string $url, string $alterna, string $idioma, string $titular, string $png): void
    {
        $this->get($url)->assertOk()
            ->assertSee('<html lang="'.$idioma.'">', false)
            ->assertSee(e($titular), false)
            ->assertSee(e($png), false)
            ->assertSee('<link rel="canonical" href="'.url($url).'">', false)
            ->assertSee('hreflang="'.($idioma === 'es' ? 'en' : 'es').'" href="'.url($alterna).'"', false)
            ->assertSee('https://www.photopea.com', false)
            ->assertSee('id="guia-prompts"', false);
    }

    public function test_las_cifras_salen_de_la_resolucion_optima_de_ajustes(): void
    {
        // A 300 ppp, 25 cm son 2.953 puntos; a 150 ppp, 1.477.
        $this->get('/es/prepara-tu-imagen')->assertSee('2.953 px')->assertSee('2953 píxeles de ancho');
        Ajuste::guardar('disenos.ppp_correcto', 150);
        $this->get('/es/prepara-tu-imagen')->assertSee('1.477 px')->assertSee('data-ppp="150"', false);
    }

    public function test_esta_en_el_pie_en_el_sitemap_y_la_otra_url_redirige(): void
    {
        // En el menú de arriba y en el pie.
        $this->assertSame(3, substr_count($this->get('/es')->getContent(), 'href="'.url('/es/prepara-tu-imagen').'"'));
        $this->get('/sitemap-es.xml')->assertOk()->assertSee('<loc>'.url('/es/prepara-tu-imagen').'</loc>', false);
        $this->get('/es/prepare-your-image')->assertRedirect('/es/prepara-tu-imagen')->assertStatus(301);
    }
}
