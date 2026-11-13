<?php

namespace Tests\Feature\Carrito;

use App\Dominio\Carrito\CarritoActual;
use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\Escaparate;
use App\Dominio\Catalogo\OtrasPrendas;
use App\Dominio\Catalogo\TipoFoto;
use App\Models\Carrito;
use App\Models\Color;
use App\Models\Diseno;
use App\Models\Familia;
use App\Models\Foto;
use App\Models\FotoZona;
use App\Models\LineaCarrito;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** D213: «Añade otra prenda con este diseño»: la sudadera abre con el diseño de la camiseta ya colocado. */
class OtraPrendaTest extends TestCase
{
    use RefreshDatabase;

    private Prenda $camiseta;

    private Prenda $sudadera;

    private LineaCarrito $linea;

    private Diseno $diseno;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Foto::DISCO);
        $negro = Color::factory()->create(['nombre' => ['es' => 'Negro']]);
        $m = Talla::factory()->create(['nombre' => 'M']);
        $frontal = ZonaImpresion::factory()->create(['clave' => 'frontal', 'cara' => Cara::Delantera, 'nombre' => ['es' => 'Frontal'], 'ancho_max_mm' => 280, 'alto_max_mm' => 380, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]);

        foreach (['camiseta' => 'Camisetas', 'sudadera' => 'Sudaderas'] as $clave => $familia) {
            $prenda = Prenda::factory()->for(Familia::factory()->create(['nombre' => ['es' => $familia]]))->create(['nombre' => ['es' => ucfirst($clave).' prueba']]);
            Variante::factory()->for($prenda)->create(['color_id' => $negro->id, 'talla_id' => $m->id, 'precio_base' => 400]);
            $prenda->zonas()->attach($frontal->id, ['precio' => 300, 'coste' => 100]);
            $foto = Foto::factory()->create(['prenda_id' => $prenda->id, 'color_id' => $negro->id, 'tipo' => TipoFoto::Maqueta, 'cara' => Cara::Delantera]);
            FotoZona::create(['foto_id' => $foto->id, 'zona_impresion_id' => $frontal->id, 'x' => 3770, 'y' => 2260, 'ancho' => 2460, 'alto' => 2790]);
            $this->{$clave} = $prenda;
        }

        $this->diseno = Diseno::forceCreate(['uuid' => '00000000-0000-0000-0000-0000000000bb', 'propietario' => 'visita', 'nombre_original' => 'logo.png',
            'ruta' => 'x.png', 'miniatura' => 'mini.webp', 'bytes' => 1, 'sha256' => str_repeat('b', 64), 'ancho_px' => 3000, 'alto_px' => 3000,
            'util_x' => 0, 'util_y' => 0, 'util_ancho_px' => 3000, 'util_alto_px' => 3000, 'fondo' => 'transparente', 'profundidad_bits' => 8]);
        $carrito = Carrito::create(['token' => hash('sha256', str_repeat('q', 40))]);
        $this->linea = $carrito->lineas()->create(['tipo' => 'prenda', 'prenda_id' => $this->camiseta->id, 'color_id' => $negro->id, 'tallas' => [$m->id => 20]]);
        $this->linea->impresiones()->create(['zona_impresion_id' => $frontal->id, 'diseno_id' => $this->diseno->id, 'ancho_mm' => 200, 'alto_mm' => 200, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]);
    }

    private function url(Prenda $prenda): string
    {
        return route('tienda.prenda', ['idioma' => 'es', 'familia' => Escaparate::slug($prenda->familia, 'es'), 'prenda' => Escaparate::slug($prenda, 'es')]);
    }

    private function conCarrito(): self
    {
        return $this->withCookie(CarritoActual::COOKIE, str_repeat('q', 40));
    }

    public function test_la_prenda_nueva_recibe_los_disenos_de_la_linea_y_el_contador_del_pedido(): void
    {
        $html = $this->conCarrito()->get($this->url($this->sudadera).'?con='.$this->linea->uuid)->assertOk()->getContent();

        $this->assertStringContainsString($this->diseno->uuid, $html);
        $this->assertStringContainsString('frontal', $html);
        $this->assertStringContainsString('Tu pedido: 1 prenda · 20 uds', $html);
        // Ofrece otras prendas para seguir (la camiseta), no la que se está viendo.
        $this->assertStringContainsString('Camiseta prueba', $html);
    }

    public function test_una_linea_que_no_es_de_tu_carrito_no_trae_nada(): void
    {
        $html = $this->get($this->url($this->sudadera).'?con='.$this->linea->uuid)->assertOk()->getContent();

        $this->assertStringNotContainsString($this->diseno->uuid, $html);
    }

    public function test_el_carrito_ofrece_completar_la_coleccion_con_el_diseno_puesto(): void
    {
        $this->conCarrito()->get('/es/carrito')->assertOk()
            ->assertSee(__('carrito.completa_titulo'))
            ->assertSee($this->url($this->sudadera).'?con='.$this->linea->uuid, false)
            ->assertSee(route('disenos.miniatura', ['diseno' => $this->diseno->uuid]), false);
    }

    public function test_primero_otra_familia_y_nunca_la_que_ya_se_esta_configurando(): void
    {
        $otraCamiseta = Prenda::factory()->for($this->camiseta->familia)->create(['nombre' => ['es' => 'Camiseta oversize']]);
        Variante::factory()->for($otraCamiseta)->create(['color_id' => Color::first()->id, 'talla_id' => Talla::first()->id, 'precio_base' => 500]);
        $foto = Foto::factory()->create(['prenda_id' => $otraCamiseta->id, 'color_id' => Color::first()->id, 'tipo' => TipoFoto::Maqueta, 'cara' => Cara::Delantera]);
        FotoZona::create(['foto_id' => $foto->id, 'zona_impresion_id' => ZonaImpresion::first()->id, 'x' => 3770, 'y' => 2260, 'ancho' => 2460, 'alto' => 2790]);
        $otraCamiseta->zonas()->attach(ZonaImpresion::first()->id, ['precio' => 300, 'coste' => 100]);

        $nombres = array_column(OtrasPrendas::para([$this->camiseta->id]), 'nombre');

        $this->assertSame('Sudadera prueba', $nombres[0]); // otro tipo de producto primero
        $this->assertNotContains('Camiseta prueba', $nombres);
    }
}
