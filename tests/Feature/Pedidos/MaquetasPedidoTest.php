<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\TipoFoto;
use App\Dominio\Documentos\FotosFactura;
use App\Dominio\Pedidos\EstadoPedido;
use App\Dominio\Pedidos\GenerarMaquetas;
use App\Dominio\Presupuestos\MaquetasPresupuesto;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Pedidos\Pages\ViewPedido;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PresupuestoController;
use App\Models\Color;
use App\Models\Factura;
use App\Models\Familia;
use App\Models\Foto;
use App\Models\FotoZona;
use App\Models\LineaPedido;
use App\Models\MaquetaPedido;
use App\Models\Pedido;
use App\Models\Prenda;
use App\Models\Presupuesto;
use App\Models\ZonaImpresion;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickPixel;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Maquetas del pedido (paso 6.2, D98). Foto de 500 × 600 px gris medio; la zona frontal
 * (28 × 38 cm, a 7,5 cm del cuello) ocupa en la foto x 30 %, y 25 %, 40 % × 50 %:
 * 200 × 300 px desde (150, 150). Un diseño de 140 × 100 mm centrado arriba queda en
 * x 200–300, y 150–229 px.
 */
class MaquetasPedidoTest extends TestCase
{
    use RefreshDatabase;

    private Pedido $pedido;

    private LineaPedido $linea;

    private ZonaImpresion $zona;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Las maquetas necesitan Imagick.');
        }
        Storage::fake(Foto::DISCO);
        Storage::fake(Pedido::DISCO);

        $prenda = Prenda::factory()->for(Familia::factory()->create())->create();
        $color = Color::factory()->create(['codigo_hex' => '#808080']);
        $this->zona = ZonaImpresion::factory()->create(['nombre' => ['es' => 'Frontal'], 'cara' => Cara::Delantera, 'ancho_max_mm' => 280, 'alto_max_mm' => 380, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]);
        Storage::disk(Foto::DISCO)->put('catalogo/frente.webp', $this->imagen(500, 600, '#808080', 'webp'));
        $foto = Foto::create(['prenda_id' => $prenda->id, 'color_id' => $color->id, 'tipo' => TipoFoto::Maqueta, 'cara' => Cara::Delantera, 'ruta' => 'catalogo/frente.webp']);
        FotoZona::create(['foto_id' => $foto->id, 'zona_impresion_id' => $this->zona->id, 'x' => 3000, 'y' => 2500, 'ancho' => 4000, 'alto' => 5000]);

        $this->pedido = Pedido::forceCreate([
            'uuid' => '00000000-0000-0000-0000-0000000000aa', 'numero' => 'PED-0001', 'estado' => EstadoPedido::PendientePago, 'idioma' => 'es',
            'correo' => 'a@b.es', 'tipo_cliente' => 'particular', 'nombre_fiscal' => 'Ana', 'direccion_envio' => $direccion = ['nombre' => 'Ana', 'empresa' => null, 'direccion' => 'Calle Luna 3', 'direccion_2' => null, 'codigo_postal' => '28004', 'localidad' => 'Madrid', 'provincia' => null, 'pais' => 'ES', 'telefono' => '600123123'], 'direccion_facturacion' => $direccion,
            'bruto' => 0, 'descuentos' => 0, 'subtotal' => 0, 'envio' => 0, 'base_imponible' => 0, 'impuestos' => 0, 'total' => 0, 'desglose' => ['pasos' => []],
            'condiciones_aceptadas_en' => now(), 'condiciones_texto' => 'Acepto',
        ]);
        $this->linea = $this->pedido->lineas()->create(['tipo' => 'prenda', 'prenda_id' => $prenda->id, 'color_id' => $color->id, 'color_hex' => '#808080',
            'nombre' => ['es' => 'Camiseta'], 'unidades' => 15, 'prendas' => 0, 'impresion' => 0, 'descuento' => 0, 'cupon' => 0, 'neto' => 0, 'tipo_iva' => 2100, 'recargo' => 0]);
    }

    private function imagen(int $w, int $h, string $color, string $formato = 'png'): string
    {
        $i = new Imagick;
        $i->newImage($w, $h, new ImagickPixel($color));
        $i->setImageFormat($formato);

        return $i->getImageBlob();
    }

    private function impresion(array $datos): void
    {
        $this->linea->impresiones()->create([...['zona_impresion_id' => $this->zona->id, 'zona_nombre' => ['es' => 'Frontal'], 'cara' => 'delantera',
            'ancho_mm' => 140, 'alto_mm' => 100, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0], ...$datos]);
    }

    /** @return array{0: float, 1: float, 2: float} color del píxel de la maqueta (0–1) */
    private function pixel(int $x, int $y): array
    {
        $maqueta = new Imagick;
        $maqueta->readImageBlob(Storage::disk(Pedido::DISCO)->get(MaquetaPedido::sole()->ruta));
        $c = $maqueta->getImagePixelColor($x, $y)->getColor(true);

        return [round($c['r'], 2), round($c['g'], 2), round($c['b'], 2)];
    }

    public function test_el_logo_va_en_su_sitio_recortado_a_lo_que_se_imprime(): void
    {
        // Logo rojo de 200 × 140 px con 50 px de margen transparente alrededor: se imprime solo lo rojo.
        $logo = new Imagick;
        $logo->newImage(300, 240, new ImagickPixel('transparent'));
        $logo->setImageFormat('png');
        $rojo = new Imagick;
        $rojo->newImage(200, 140, new ImagickPixel('#E00000'));
        $logo->compositeImage($rojo, Imagick::COMPOSITE_OVER, 50, 50);
        Storage::disk(Pedido::DISCO)->put("{$this->pedido->uuid}/logo.png", $logo->getImageBlob());
        $this->impresion(['archivo' => "{$this->pedido->uuid}/logo.png", 'util_x' => 50, 'util_y' => 50, 'util_ancho_px' => 200, 'util_alto_px' => 140]);

        $this->assertSame(1, app(GenerarMaquetas::class)($this->pedido));

        $maqueta = MaquetaPedido::sole();
        $this->assertSame(['delantera', 500, 600], [$maqueta->cara, $maqueta->ancho_px, $maqueta->alto_px]);
        [$r, $g, $b] = $this->pixel(250, 190);          // dentro del logo: rojo, con la luz de la tela
        $this->assertGreaterThan(0.6, $r);
        $this->assertLessThan(0.15, max($g, $b));
        $this->assertSame([0.5, 0.5, 0.5], $this->pixel(250, 260)); // debajo del logo: la tela, sin tocar
        $this->assertSame([0.5, 0.5, 0.5], $this->pixel(180, 190)); // a su lado (el margen transparente no se imprime)
    }

    /**
     * D244: el rectángulo de la zona en la foto (200 × 300 px) no tiene la proporción de la zona en mm (280 × 380):
     * el diseño de 14 × 10 cm conserva su proporción (100 × 71 px), no se estira al alto de la caja (100 × 79 px).
     */
    public function test_el_diseno_no_se_deforma_si_la_zona_de_la_foto_tiene_otra_proporcion(): void
    {
        $logo = new Imagick;
        $logo->newImage(1400, 1000, new ImagickPixel('#E00000'));
        $logo->setImageFormat('png');
        Storage::disk(Pedido::DISCO)->put("{$this->pedido->uuid}/logo.png", $logo->getImageBlob());
        $this->impresion(['archivo' => "{$this->pedido->uuid}/logo.png", 'util_x' => 0, 'util_y' => 0, 'util_ancho_px' => 1400, 'util_alto_px' => 1000]);

        app(GenerarMaquetas::class)($this->pedido);

        $this->assertGreaterThan(0.6, $this->pixel(250, 216)[0]);           // aún dentro del diseño
        $this->assertSame([0.5, 0.5, 0.5], $this->pixel(250, 226));        // antes se estiraba hasta 229
        $this->assertGreaterThan(0.6, $this->pixel(203, 190)[0]);           // de lado a lado, 100 px
        $this->assertSame([0.5, 0.5, 0.5], $this->pixel(196, 190));
    }

    /** D244: desde la ficha del pedido se rehacen sus maquetas. */
    public function test_la_ficha_del_pedido_rehace_las_maquetas(): void
    {
        $this->impresion(['texto' => 'NG', 'fuente' => 0, 'tinta' => 'auto']);
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));

        Livewire::test(ViewPedido::class, ['record' => $this->pedido->getRouteKey()])
            ->callAction('maquetas')->assertNotified('1 maqueta rehecha');
        $this->assertSame(1, MaquetaPedido::count());
    }

    /** Tinta automática: blanca en una prenda oscura y negra en una clara, como en el visor. */
    #[DataProvider('tintas')]
    public function test_el_texto_lleva_su_letra_y_su_tinta(string $prenda, ?string $tinta, string $esperada): void
    {
        $this->linea->update(['color_hex' => $prenda]);
        $this->impresion(['texto' => 'NG', 'fuente' => 2, 'tinta' => $tinta]);

        app(GenerarMaquetas::class)($this->pedido);

        $maqueta = new Imagick;
        $maqueta->readImageBlob(Storage::disk(Pedido::DISCO)->get(MaquetaPedido::sole()->ruta));
        $caja = $maqueta->getImageRegion(100, 79, 200, 150);
        $caja->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        $extremos = $caja->getImageChannelRange(Imagick::CHANNEL_GRAY);
        $q = Imagick::getQuantum();
        // La caja tiene tinta: hay píxeles mucho más claros (blanco) o más oscuros (negro) que la tela gris.
        $esperada === 'clara'
            ? $this->assertGreaterThan(0.85 * $q, $extremos['maxima'])
            : $this->assertLessThan(0.2 * $q, $extremos['minima']);
    }

    public static function tintas(): array
    {
        return [
            'prenda oscura, tinta automática' => ['#16161A', 'auto', 'clara'],
            'prenda clara, tinta automática' => ['#F5F5F5', 'auto', 'oscura'],
            'tinta blanca elegida' => ['#F5F5F5', 'blanco', 'clara'],
        ];
    }

    public function test_sin_foto_de_maqueta_no_falla_ni_bloquea_el_pedido(): void
    {
        Foto::query()->delete();
        $this->impresion(['texto' => 'NG', 'fuente' => 0, 'tinta' => 'auto']);

        $this->assertSame(0, app(GenerarMaquetas::class)($this->pedido));
        $this->assertSame(0, MaquetaPedido::count());
    }

    public function test_volver_a_generarla_la_sustituye(): void
    {
        $this->impresion(['texto' => 'NG', 'fuente' => 0, 'tinta' => 'auto']);
        app(GenerarMaquetas::class)($this->pedido);
        app(GenerarMaquetas::class)($this->pedido);

        $this->assertSame(1, MaquetaPedido::count());
        Storage::disk(Pedido::DISCO)->assertExists(MaquetaPedido::sole()->ruta);
    }

    public function test_la_maqueta_solo_la_ve_quien_puede_ver_el_pedido(): void
    {
        $this->impresion(['texto' => 'NG', 'fuente' => 0, 'tinta' => 'auto']);
        app(GenerarMaquetas::class)($this->pedido);
        $url = '/es/pedidos/'.$this->pedido->uuid.'/maquetas/'.MaquetaPedido::sole()->id;

        $this->get($url)->assertNotFound();
        $this->withSession([PedidoController::SESION => [$this->pedido->uuid]])->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');

        // Con el uuid de otro pedido suyo no se puede pedir la maqueta de este.
        $otro = $this->pedido->replicate()->fill(['uuid' => '00000000-0000-0000-0000-0000000000bb', 'numero' => 'PED-0002']);
        $otro->save();
        $this->withSession([PedidoController::SESION => [$otro->uuid]])->get('/es/pedidos/'.$otro->uuid.'/maquetas/'.MaquetaPedido::sole()->id)->assertNotFound();
        $this->withSession([PedidoController::SESION => [$this->pedido->uuid]])->get('/es/pedidos/'.$this->pedido->uuid)->assertSee($url, false);
    }

    /** D200: el PDF del presupuesto lleva la foto de lo que ha creado, compuesta igual que la del pedido. */
    public function test_el_presupuesto_lleva_la_maqueta_de_lo_que_ha_creado(): void
    {
        $presupuesto = Presupuesto::create([
            'uuid' => (string) Str::uuid(), 'numero' => 'PRE-0001', 'idioma' => 'es', 'total' => 0, 'valido_hasta' => now()->addHours(48),
            'contenido' => ['lineas' => [['descripcion' => 'Camiseta', 'detalle' => 'Frontal', 'unidades' => 15, 'neto' => 0]], 'bruto' => 0, 'descuentos' => 0, 'descuento_nombre' => null, 'base' => 0, 'impuestos' => 0, 'total' => 0],
            'origen' => [['tipo' => 'prenda', 'prenda_id' => $this->linea->prenda_id, 'color_id' => $this->linea->color_id, 'pack_id' => null, 'tallas' => [], 'elecciones' => null,
                'impresiones' => [$this->zona->id => ['texto' => 'NEXT', 'fuente' => 2, 'tinta' => 'negro', 'ancho_mm' => 140, 'alto_mm' => 100, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]]]],
        ]);

        $maquetas = app(MaquetasPresupuesto::class)($presupuesto);

        $this->assertCount(1, $maquetas[0]);
        $this->assertSame('delantera', $maquetas[0][0]['cara']); // con su cara, para el rótulo del PDF
        $imagen = new Imagick;
        $imagen->readImageBlob((string) base64_decode(substr($maquetas[0][0]['src'], strlen('data:image/jpeg;base64,'))));
        $imagen->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        $this->assertLessThan(0.2 * Imagick::getQuantum(), $imagen->getImageChannelRange(Imagick::CHANNEL_GRAY)['minima']); // la tinta negra está
        $pdf = $this->get(PresupuestoController::url('pdf', $presupuesto))->assertOk()->getContent();
        $this->assertStringContainsString('/Subtype /Image', $pdf);
    }

    /** D215: la factura enseña la colección con la foto de cada cara; la rectificativa, no. */
    public function test_la_factura_lleva_las_fotos_de_la_coleccion(): void
    {
        $this->impresion(['texto' => 'NG', 'fuente' => 2, 'tinta' => 'negro']);
        app(GenerarMaquetas::class)($this->pedido);

        $coleccion = FotosFactura::de(new Factura(['pedido_id' => $this->pedido->id, 'idioma' => 'es']));

        $this->assertCount(1, $coleccion);
        $this->assertSame('delantera', $coleccion[0]['fotos'][0]['cara']);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $coleccion[0]['fotos'][0]['src']);
        $this->assertSame([], FotosFactura::de((new Factura(['pedido_id' => $this->pedido->id, 'idioma' => 'es', 'rectifica_id' => 999]))->setRelation('rectifica', new Factura)));
    }
}
