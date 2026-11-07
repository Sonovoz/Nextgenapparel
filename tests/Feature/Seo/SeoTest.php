<?php

namespace Tests\Feature\Seo;

use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\AjustesGenerales;
use App\Models\Ajuste;
use App\Models\Foto;
use App\Models\Prenda;
use Database\Seeders\EjemplosCatalogoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Foto::DISCO);
        $this->seed(EjemplosCatalogoSeeder::class);
    }

    private function enEntorno(string $entorno): void
    {
        $this->app['env'] = $entorno;
    }

    /** @return array<int, array<string, mixed>> */
    private function esquemas(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.+?)</script>#s', $html, $bloques);

        return array_map(fn ($json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $bloques[1]);
    }

    // ── Indexación ────────────────────────────────────────────────────

    /** @return array<string, array{string, bool, bool}> */
    public static function casosIndexacion(): array
    {
        return [
            'producción con el interruptor encendido' => ['production', true, true],
            'producción con el interruptor apagado' => ['production', false, false],
            'pruebas aunque esté encendido' => ['staging', true, false],
            'local aunque esté encendido' => ['local', true, false],
        ];
    }

    #[DataProvider('casosIndexacion')]
    public function test_solo_se_indexa_en_produccion_con_el_interruptor_encendido(string $entorno, bool $interruptor, bool $indexa): void
    {
        Ajuste::guardar('seo.indexar', $interruptor);
        $this->enEntorno($entorno);

        $robots = $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->getContent();
        $portada = $this->get('/es')->getContent();

        if ($indexa) {
            $this->assertStringContainsString('Disallow: /admin', $robots);
            $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $robots);
            $this->assertStringNotContainsString("Disallow: /\n", $robots);
            $this->assertStringContainsString('<meta name="robots" content="index, follow', $portada);
        } else {
            $this->assertStringContainsString("Disallow: /\n", $robots);
            $this->assertStringNotContainsString('Sitemap:', $robots);
            $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $portada);
        }
    }

    public function test_detras_de_caddy_las_urls_salen_en_https(): void
    {
        config(['app.url' => 'https://nextgenapparel.es']);
        $cabeceras = ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'nextgenapparel.es', 'X-Forwarded-Port' => '443'];

        $html = $this->get('/es/camisetas/camiseta-essential', $cabeceras)->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="https://nextgenapparel.es/es/camisetas/camiseta-essential">', $html);
        $this->assertStringContainsString('src="https://nextgenapparel.es/js/visor.js', $html);
        // En el sitemap, ninguna URL en http (salvo los espacios de nombres XML).
        $sitemap = preg_replace('#xmlns(:\w+)?="http://[^"]+"#', '', $this->get('/sitemap-es.xml', $cabeceras)->getContent());
        $this->assertStringContainsString('<loc>https://nextgenapparel.es/es/camisetas/camiseta-essential</loc>', $sitemap);
        $this->assertStringNotContainsString('http://', $sitemap);
    }

    public function test_un_dominio_que_no_es_el_de_la_tienda_se_rechaza(): void
    {
        config(['app.url' => 'https://nextgenapparel.es']);
        $this->enEntorno('production');

        // Caddy reenvía la cabecera Host del cliente: solo se atiende el dominio de APP_URL.
        $this->get('https://dominio-falso.com/es')->assertStatus(400);
        $this->get('https://nextgenapparel.es/es')->assertOk();
        $this->get('https://www.nextgenapparel.es/es')->assertOk();
        $this->get('http://127.0.0.1/up')->assertOk(); // comprobación de salud del contenedor web
    }

    public function test_el_backend_queda_fuera_de_los_buscadores(): void
    {
        $this->get('/admin/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_el_interruptor_se_cambia_en_ajustes_y_queda_en_la_actividad(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(AjustesGenerales::class)
            ->assertSchemaStateSet(['indexar' => false])
            ->fillForm(['indexar' => true])
            ->callAction('guardar')
            ->assertNotified('Ajustes guardados');

        $this->assertTrue(Ajuste::obtener('seo.indexar'));
        $this->assertSame('Indexación en buscadores activada', Activity::latest('id')->value('description'));
    }

    // ── Sitemaps ──────────────────────────────────────────────────────

    public function test_el_indice_enlaza_un_sitemap_por_idioma(): void
    {
        $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent());

        $this->assertSame([url('/sitemap-es.xml'), url('/sitemap-en.xml')], array_map('strval', $xml->xpath('//*[local-name()="loc"]')));
    }

    public function test_el_sitemap_de_cada_idioma_lleva_sus_urls_y_las_alternativas(): void
    {
        $es = $this->get('/sitemap-es.xml')->assertOk()->getContent();
        $en = $this->get('/sitemap-en.xml')->assertOk()->getContent();
        $this->assertNotFalse(simplexml_load_string($es));
        $this->assertNotFalse(simplexml_load_string($en));

        $this->assertStringContainsString('<loc>'.url('/es/camisetas/camiseta-essential').'</loc>', $es);
        $this->assertStringContainsString('<loc>'.url('/en/t-shirts/essential-t-shirt').'</loc>', $en);
        $this->assertStringContainsString('hreflang="en" href="'.url('/en/t-shirts/essential-t-shirt').'"', $es);
        $this->assertStringContainsString('hreflang="x-default" href="'.url('/es/camisetas/camiseta-essential').'"', $en);
        $this->assertStringContainsString('<loc>'.url('/es/sudaderas').'</loc>', $es);
    }

    public function test_las_prendas_ocultas_no_salen_en_el_sitemap(): void
    {
        Prenda::where('slug->es', 'polo-classic')->update(['activa' => false]);

        $this->get('/sitemap-es.xml')->assertDontSee('polo-classic');
    }

    public function test_un_idioma_no_disponible_no_tiene_sitemap(): void
    {
        $this->get('/sitemap-fr.xml')->assertNotFound();
    }

    // ── Etiquetas y datos estructurados ───────────────────────────────

    public function test_la_ficha_lleva_canonical_open_graph_y_producto_con_su_oferta(): void
    {
        $html = $this->get('/es/camisetas/camiseta-essential')->assertOk()->getContent();
        $url = url('/es/camisetas/camiseta-essential');

        $this->assertStringContainsString('<link rel="canonical" href="'.$url.'">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="product">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="'.$url.'">', $html);
        $this->assertStringContainsString('<meta property="og:locale" content="es_ES">', $html);
        $this->assertStringContainsString('<meta property="og:locale:alternate" content="en_GB">', $html);

        [$producto, $migas] = $this->esquemas($html);
        $this->assertSame('Product', $producto['@type']);
        $this->assertSame('Camiseta Essential', $producto['name']);
        $this->assertSame('EUR', $producto['offers']['priceCurrency']);
        $this->assertSame('5.08', $producto['offers']['lowPrice']); // 4,20 € + 21 %, igual que en la página
        $this->assertSame(Prenda::where('slug->es', 'camiseta-essential')->sole()->variantes()->where('activa', true)->count(), $producto['offers']['offerCount']);

        $this->assertSame('BreadcrumbList', $migas['@type']);
        $this->assertSame(['Inicio', 'Camisetas', 'Camiseta Essential'], array_column($migas['itemListElement'], 'name'));
        $this->assertSame($url, $migas['itemListElement'][2]['item']);
    }

    public function test_en_ingles_la_canonical_y_los_datos_van_en_ingles(): void
    {
        $html = $this->get('/en/t-shirts/essential-t-shirt')->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.url('/en/t-shirts/essential-t-shirt').'">', $html);
        $this->assertSame(['Home', 'T-shirts', 'Essential T-shirt'], array_column($this->esquemas($html)[1]['itemListElement'], 'name'));
    }

    public function test_la_portada_presenta_la_organizacion_y_el_sitio(): void
    {
        $esquemas = $this->esquemas($this->get('/es')->getContent());

        $this->assertSame(['Organization', 'WebSite'], array_column($esquemas, '@type'));
        $this->assertSame('NextGen Apparel', $esquemas[0]['name']);
    }

    public function test_el_listado_lleva_su_ruta_de_migas(): void
    {
        $migas = $this->esquemas($this->get('/es/polos')->getContent())[0];

        $this->assertSame(['Inicio', 'Polos'], array_column($migas['itemListElement'], 'name'));
    }
}
