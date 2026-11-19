<?php

namespace Tests\Feature\Catalogo;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\Origen;
use App\Dominio\Catalogo\Proveedores\ImportarTopTex;
use App\Dominio\Catalogo\TipoFoto;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\AjustesProveedores;
use App\Filament\Resources\Catalogo\Prendas\Pages\ListPrendas;
use App\Jobs\ImportarFotosTopTex;
use App\Models\Ajuste;
use App\Models\Color;
use App\Models\Familia;
use App\Models\Foto;
use App\Models\Prenda;
use App\Models\Variante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Livewire\Livewire;
use Tests\TestCase;

/** D254: importar una referencia de TopTex como prenda con sus variaciones, precios con margen y fotos. */
class ImportarTopTexTest extends TestCase
{
    use RefreshDatabase;

    private Familia $familia;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('toptex', ['url' => 'https://api.toptex.io', 'api_key' => 'k', 'usuario' => 'toes_prueba', 'clave' => 'c', 'timeout' => 5]);
        $this->familia = Familia::factory()->create(['nombre' => ['es' => 'Camisetas'], 'slug' => ['es' => 'camisetas']]);
        Ajuste::guardar('proveedores.margen', 80);
    }

    /** @return array<string, mixed> la ficha real de GI6400B (dos colores y dos tallas de cada uno) */
    private function ficha(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/fixtures/toptex-GI6400B.json'), true);
    }

    /** @param  array<string, int>  $costes  sku => céntimos */
    private function fingir(array $ficha, array $costes): void
    {
        $precios = collect($costes)->map(fn ($c, $sku) => ['sku' => $sku, 'catalogReference' => 'GI6400B', 'prices' => [['quantity' => '1', 'price' => $c / 100], ['quantity' => '50', 'price' => $c / 200]]])->values()->all();
        Http::fake([
            'api.toptex.io/v3/authenticate' => Http::response(['token' => 't']),
            'api.toptex.io/v3/products/price*' => Http::response($precios),
            'api.toptex.io/v3/products*' => Http::response($ficha),
        ]);
    }

    private const COSTES = ['GI6400BC_37609_37608' => 650, 'GI6400BC_37609_37621' => 650, 'GI6400B_37597_37596' => 590, 'GI6400B_37597_37598' => 590];

    public function test_crea_la_prenda_oculta_con_sus_colores_tallas_coste_y_precio_con_margen(): void
    {
        Queue::fake();
        $this->fingir($this->ficha(), self::COSTES);

        $r = app(ImportarTopTex::class)('gi6400b', $this->familia);

        $prenda = $r['prenda']->fresh();
        $this->assertTrue($r['creada']);
        $this->assertFalse($prenda->activa); // oculta hasta tener fotos y zonas
        $this->assertSame('GI6400B', $prenda->referencia_proveedor);
        $this->assertSame(Origen::Api, $prenda->proveedor->tipo_conector);
        $this->assertSame('Camiseta SoftStyle niños Gildan', $prenda->getTranslation('nombre', 'es'));
        $this->assertSame('100% Algodón', $prenda->getTranslation('composicion', 'es'));
        $this->assertSame(150, (int) $prenda->gramaje);
        $this->assertSame([2, 4, 0], [$r['colores'], $r['variantes_nuevas'], $r['sin_precio']]);

        $negroXs = Variante::where('sku', 'GI6400BC_37609_37608')->firstOrFail();
        $this->assertSame([650, 1170, 167, 'XS', '#25282A', 'Black'], [$negroXs->coste, $negroXs->precio_base, $negroXs->peso_gramos, $negroXs->talla->nombre, $negroXs->color->codigo_hex, $negroXs->color->getTranslation('nombre', 'es')]);
        $this->assertSame(1065, Variante::where('sku', 'GI6400B_37597_37596')->value('precio_base')); // 5,90 € + 80 % = 10,62 → 10,65 €

        // Sin la carta de fotos aceptada en TopTex, no hay fotos que traer: se avisa.
        $this->assertTrue($r['fotos_bloqueadas']);
        Queue::assertNothingPushed();
    }

    public function test_al_actualizar_cambian_costes_y_precios_pero_no_los_textos_del_equipo(): void
    {
        Queue::fake();
        $this->fingir($this->ficha(), self::COSTES);
        $prenda = app(ImportarTopTex::class)('GI6400B', $this->familia)['prenda'];
        $prenda->update(['nombre' => ['es' => 'Camiseta infantil'], 'margen' => 100]);

        // TopTex sube el negro, retira una talla del blanco y deja otra sin precio.
        $ficha = $this->ficha();
        $ficha['colors'][1]['sizes'][0]['saleState'] = 'discontinued';
        $costes = self::COSTES;
        $costes['GI6400BC_37609_37608'] = 700;
        unset($costes['GI6400B_37597_37598']);
        Http::swap(new HttpFactory);
        $this->fingir($ficha, $costes);

        $r = app(ImportarTopTex::class)('GI6400B');

        $this->assertFalse($r['creada']);
        $this->assertSame('Camiseta infantil', $prenda->fresh()->getTranslation('nombre', 'es'));
        $this->assertSame(1400, Variante::where('sku', 'GI6400BC_37609_37608')->value('precio_base')); // 7,00 € + su 100 %
        $this->assertFalse((bool) Variante::where('sku', 'GI6400B_37597_37596')->value('activa'));
        $this->assertFalse((bool) Variante::where('sku', 'GI6400B_37597_37598')->value('activa')); // sin precio: no se vende
        $this->assertSame([1, 2], [$r['sin_precio'], $r['desactivadas']]);
        $this->assertSame(4, Variante::count());
    }

    public function test_trae_las_fotos_de_maniqui_invisible_en_la_cola_sin_pisar_las_reales(): void
    {
        Storage::fake(Foto::DISCO);
        Queue::fake();
        $ficha = $this->ficha();
        $ficha['colors'][0]['packshots'] = [
            'FACE' => ['url' => 'https://cdn.toptex.io/ps/negro-plano.png'],
            'GHOST_FACE' => ['url' => 'https://cdn.toptex.io/ps/negro-fantasma.png'],
            'BACK' => ['url_packshot' => 'https://cdn.toptex.io/ps/negro-espalda.png', 'last_update' => '2026-01-01'],
        ];
        $ficha['colors'][1]['packshots'] = ['FACE' => ['url' => 'https://cdn.toptex.io/ps/blanco.png']];
        $this->fingir($ficha, self::COSTES);

        $prenda = app(ImportarTopTex::class)('GI6400B', $this->familia)['prenda'];
        $negro = Color::where('codigo_hex', '#25282A')->firstOrFail();
        $blanco = Color::where('codigo_hex', '#FFFFFF')->firstOrFail();

        $trabajo = null;
        Queue::assertPushed(ImportarFotosTopTex::class, function (ImportarFotosTopTex $j) use (&$trabajo) {
            $trabajo = $j;

            return count($j->fotos) === 3;
        });
        $this->assertContains('https://cdn.toptex.io/ps/negro-fantasma.png', array_column($trabajo->fotos, 'url')); // la de maniquí invisible
        $this->assertNotContains('https://cdn.toptex.io/ps/negro-plano.png', array_column($trabajo->fotos, 'url'));

        // El blanco ya tiene su foto real: no se toca.
        $real = Foto::create(['prenda_id' => $prenda->id, 'color_id' => $blanco->id, 'tipo' => TipoFoto::Maqueta->value, 'cara' => Cara::Delantera->value, 'ruta' => 'catalogo/real.png']);
        $png = new Imagick;
        $png->newImage(40, 50, 'transparent');
        $png->setImageFormat('png');
        Http::swap(new HttpFactory);
        Http::fake(['cdn.toptex.io/*' => Http::response($png->getImageBlob(), 200, ['Content-Type' => 'image/png'])]);

        $trabajo->handle();

        $this->assertSame(2, Foto::where('color_id', $negro->id)->where('origen', 'api')->count());
        $this->assertSame('catalogo/real.png', $real->fresh()->ruta);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'blanco.png'));
    }

    public function test_cambiar_el_margen_general_recalcula_solo_las_prendas_de_proveedor(): void
    {
        Queue::fake();
        $this->fingir($this->ficha(), self::COSTES);
        app(ImportarTopTex::class)('GI6400B', $this->familia);
        $manual = Variante::factory()->create(['precio_base' => 999, 'coste' => 400]);
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(AjustesProveedores::class)->set('data.margen', 50)->callAction('guardar')->assertNotified();

        $this->assertSame(975, Variante::where('sku', 'GI6400BC_37609_37608')->value('precio_base')); // 6,50 € + 50 %
        $this->assertSame(999, $manual->fresh()->precio_base);
    }

    public function test_el_boton_importar_de_toptex_crea_la_prenda_desde_productos(): void
    {
        Queue::fake();
        $this->fingir($this->ficha(), self::COSTES);
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(ListPrendas::class)
            ->callAction('importar_toptex', data: ['referencia' => 'GI6400B', 'familia_id' => $this->familia->id])
            ->assertHasNoActionErrors()->assertNotified();

        $this->assertSame(1, Prenda::where('referencia_proveedor', 'GI6400B')->count());
    }
}
