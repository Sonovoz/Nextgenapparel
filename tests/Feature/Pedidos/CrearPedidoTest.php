<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Carrito\ValorarCarrito;
use App\Dominio\Disenos\GuardarDiseno;
use App\Dominio\Disenos\LimpiarDisenos;
use App\Dominio\Pedidos\CrearPedido;
use App\Dominio\Pedidos\EstadoPedido;
use App\Dominio\Pedidos\PedidoNoCreado;
use App\Dominio\Precios\Motor\Destino;
use App\Models\Ajuste;
use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\Color;
use App\Models\Cupon;
use App\Models\Diseno;
use App\Models\Familia;
use App\Models\LineaPedido;
use App\Models\Pack;
use App\Models\Pedido;
use App\Models\Prenda;
use App\Models\ReglaMinimo;
use App\Models\Talla;
use App\Models\TarifaEnvio;
use App\Models\Variante;
use App\Models\ZonaEnvio;
use App\Models\ZonaImpresion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

/**
 * El pedido desde el carrito (paso 6.1, D169). Mismo ejemplo que el cierre (D166):
 * 15 camisetas negras M a 4,00 € + 3,00 € de impresión, envío a la península 4,90 €;
 * base 109,90 €, IVA 23,08 €, total 132,98 €.
 */
class CrearPedidoTest extends TestCase
{
    use RefreshDatabase;

    private const TOTAL = 13298;

    private Carrito $carrito;

    private Diseno $logo;

    /** @var array{prenda: int, color: int, talla: int, zona: int} */
    private array $linea;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(GuardarDiseno::DISCO);
        Storage::fake(Pedido::DISCO);

        $negro = Color::factory()->create(['nombre' => ['es' => 'Negro', 'en' => 'Black']]);
        $m = Talla::factory()->create(['nombre' => 'M']);
        $frontal = ZonaImpresion::factory()->create(['nombre' => ['es' => 'Frontal', 'en' => 'Front'], 'ancho_max_mm' => 280, 'alto_max_mm' => 380, 'desde_cuello_mm' => 75]);
        $prenda = Prenda::factory()->for(Familia::factory()->create())->create(['nombre' => ['es' => 'Camiseta Essential', 'en' => 'Essential T-shirt']]);
        Variante::factory()->for($prenda)->create(['color_id' => $negro->id, 'talla_id' => $m->id, 'precio_base' => 400, 'peso_gramos' => 150]);
        $prenda->zonas()->attach($frontal->id, ['precio' => 300, 'coste' => 100]);
        $peninsula = ZonaEnvio::create(['nombre' => ['es' => 'Península'], 'paises' => ['ES'], 'metodo' => 'por_kilo', 'precio_kg_extra' => 80, 'plazo_dias' => 2]);
        TarifaEnvio::create(['zona_envio_id' => $peninsula->id, 'hasta_gramos' => 5000, 'precio' => 490]);

        Storage::disk(GuardarDiseno::DISCO)->put('sesion/logo.png', 'PNG-ORIGINAL');
        Storage::disk(GuardarDiseno::DISCO)->put('sesion/logo-mini.webp', 'MINI');
        $this->logo = Diseno::forceCreate([
            'uuid' => '00000000-0000-0000-0000-000000000001', 'propietario' => str_repeat('a', 64), 'nombre_original' => 'logo.png',
            'ruta' => 'sesion/logo.png', 'miniatura' => 'sesion/logo-mini.webp', 'bytes' => 12, 'sha256' => str_repeat('b', 64), 'ancho_px' => 1200, 'alto_px' => 1200,
            'util_x' => 0, 'util_y' => 0, 'util_ancho_px' => 1200, 'util_alto_px' => 1200, 'fondo' => 'transparente', 'profundidad_bits' => 8,
            'derechos_declarados_en' => now(), 'derechos_texto' => 'Declaro…',
        ]);

        $this->linea = ['prenda' => $prenda->id, 'color' => $negro->id, 'talla' => $m->id, 'zona' => $frontal->id];
        $this->nuevoCarrito();
    }

    /** Un carrito con 15 camisetas negras M y el logo en el frontal. */
    private function nuevoCarrito(): void
    {
        $this->carrito = Carrito::create(['token' => hash('sha256', str_repeat('t', 39).Carrito::count())]);
        $linea = $this->carrito->lineas()->create(['tipo' => 'prenda', 'prenda_id' => $this->linea['prenda'], 'color_id' => $this->linea['color'], 'tallas' => [$this->linea['talla'] => 15]]);
        // 100 mm de ancho con 1200 px: 305 ppp.
        $linea->impresiones()->create(['zona_impresion_id' => $this->linea['zona'], 'diseno_id' => $this->logo->id, 'ancho_mm' => 100, 'alto_mm' => 100, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]);
    }

    private function crear(array $cambios = [], ?Cliente $cliente = null, int $total = self::TOTAL): Pedido
    {
        $datos = [...['correo' => 'Lucia@Ejemplo.com', 'tipo' => 'particular', 'nif' => null, 'recargo' => false, 'nombre' => 'Lucía Pérez', 'empresa' => null,
            'direccion' => 'Calle Luna 3', 'direccion_2' => null, 'codigo_postal' => '28004', 'localidad' => 'Madrid', 'provincia' => null, 'pais' => 'ES', 'telefono' => '600 123 123'], ...$cambios];

        return app(CrearPedido::class)($this->carrito, $datos, $cliente, $total, 'es', '203.0.113.7');
    }

    public function test_el_pedido_copia_lo_que_el_cliente_confirmo_y_vacia_el_carrito(): void
    {
        $pedido = $this->crear();

        $this->assertSame('PED-0001', $pedido->numero);
        $this->assertSame(EstadoPedido::PendientePago, $pedido->estado);
        $this->assertSame([10500, 0, 10500, 490], [$pedido->bruto, $pedido->descuentos, $pedido->subtotal, $pedido->envio]);
        $this->assertSame([10990, 2308, self::TOTAL], [$pedido->base_imponible, $pedido->impuestos, $pedido->total]);
        $this->assertSame(['lucia@ejemplo.com', null, null], [$pedido->correo, $pedido->cliente_id, $pedido->correo_confirmado_en]);
        $this->assertSame(['Lucía Pérez', 'Calle Luna 3', '28004'], [$pedido->nombre_fiscal, $pedido->direccion_envio['direccion'], $pedido->direccion_facturacion['codigo_postal']]);
        $this->assertStringStartsWith('He leído y acepto', $pedido->condiciones_texto);

        $linea = $pedido->lineas->sole();
        $this->assertSame(['Essential T-shirt', 'Black', [['talla_id' => $linea->tallas[0]['talla_id'], 'nombre' => 'M', 'unidades' => 15]]], [$linea->nombreEn('en'), $linea->colorEn('en'), $linea->tallas]);
        $this->assertSame([15, 6000, 4500, 10500], [$linea->unidades, $linea->prendas, $linea->impresion, $linea->neto]);
        $this->assertSame((250 + 100) * 15, $linea->coste); // coste de la variante (fábrica) + de la zona, por unidad (D191)
        $impresion = $linea->impresiones->sole();
        $this->assertSame(['Frontal', 100, 75, 305, 'correcta'], [$impresion->zonaEn('es'), $impresion->ancho_mm, $impresion->desde_cuello_mm, $impresion->ppp, $impresion->nivel_calidad]);

        // Familia (2 días por defecto) + transportista (2): 4 días laborables.
        $this->assertTrue($pedido->fresh()->fecha_estimada_entrega->greaterThan(now()->addDays(3)));
        $this->assertSame(EstadoPedido::PendientePago, $pedido->historial->sole()->estado);
        $this->assertNull(Carrito::find($this->carrito->id));
    }

    public function test_el_diseno_se_copia_al_pedido_y_no_depende_de_la_biblioteca(): void
    {
        $impresion = $this->crear()->lineas->sole()->impresiones->sole();

        Storage::disk(Pedido::DISCO)->assertExists([$impresion->archivo, $impresion->miniatura]);
        $this->logo->delete(); // el cliente lo quita de «Tus diseños» o lo borra la limpieza (D150)

        $this->assertSame('PNG-ORIGINAL', Storage::disk(Pedido::DISCO)->get($impresion->archivo));
        $this->assertSame(['logo.png', str_repeat('b', 64)], [$impresion->nombre_original, $impresion->sha256]);
    }

    public function test_los_numeros_son_correlativos_con_el_prefijo_de_ajustes(): void
    {
        $primero = $this->crear();
        $this->nuevoCarrito();
        Ajuste::guardar('pedidos.prefijo', 'NG-');
        $segundo = $this->crear();

        $this->assertSame(['PED-0001', 'NG-0002'], [$primero->numero, $segundo->numero]);
    }

    /** Todo o nada: ni pedido, ni archivos, ni número gastado, y el carrito sigue igual. */
    #[DataProvider('noSeCrea')]
    public function test_si_algo_no_cuadra_no_se_crea_nada(callable $preparar, string $motivo): void
    {
        $total = $preparar($this);

        try {
            $this->crear(total: $total ?? self::TOTAL);
            $this->fail('Se ha creado un pedido que no debía.');
        } catch (PedidoNoCreado $e) {
            $this->assertSame($motivo, $e->motivo);
        }
        $this->assertSame(0, Pedido::count());
        $this->assertSame([], Storage::disk(Pedido::DISCO)->allFiles());
        $this->assertSame(0, (int) DB::table('contadores')->where('clave', 'pedido')->value('valor'));
        if ($motivo !== 'vacio') {
            $this->assertSame(1, $this->carrito->lineas()->count());
        }
    }

    public static function noSeCrea(): array
    {
        return [
            'el total no es el que vio' => [fn () => self::TOTAL - 1, 'total_cambiado'],
            'el precio ha cambiado desde entonces' => [function () {
                Variante::query()->update(['precio_base' => 450]);

                return null;
            }, 'total_cambiado'],
            'el cupón ya no tiene usos' => [function (self $t) {
                Cupon::create(['codigo' => 'ULTIMO', 'tipo' => 'importe', 'valor' => 1000, 'usos_maximos' => 1, 'usos' => 1]);
                $t->carrito->update(['cupon' => 'ULTIMO']);

                return 12088; // el total con el descuento que ya no vale
            }, 'total_cambiado'],
            'no llega al mínimo' => [function () {
                ReglaMinimo::create(['nombre' => ['es' => 'Mínimo'], 'tipo' => 'unidades_total', 'valor' => 20, 'ambito' => 'catalogo']);

                return null;
            }, 'no_confirmable'],
            'el carrito se vació entre medias' => [function (self $t) {
                $t->carrito->lineas()->delete();

                return null;
            }, 'vacio'],
        ];
    }

    public function test_el_cupon_cuenta_un_uso_y_no_vale_dos_veces_para_el_mismo_correo(): void
    {
        Cupon::create(['codigo' => 'BIENVENIDA', 'tipo' => 'importe', 'valor' => 1000, 'usos_maximos' => 5]);
        $this->carrito->update(['cupon' => 'bienvenida']);
        // 10,00 € menos: base 99,90, IVA 20,98, total 120,88.
        $pedido = $this->crear(total: 12088);
        $this->assertSame(['BIENVENIDA', 1000, 12088], [$pedido->cupon, $pedido->descuentos, $pedido->total]);
        $this->assertSame(1, Cupon::sole()->usos);

        // Mismo correo (en mayúsculas), otro carrito: el cierre ya no le muestra el descuento.
        $this->nuevoCarrito();
        $this->carrito->update(['cupon' => 'BIENVENIDA']);
        $this->expectExceptionObject(new PedidoNoCreado('total_cambiado'));
        $this->crear(['correo' => 'LUCIA@ejemplo.com'], total: 12088);
    }

    public function test_con_cuenta_el_pedido_es_de_su_ficha_con_sus_datos_fiscales(): void
    {
        $cliente = Cliente::factory()->create(['correo' => 'ana@ejemplo.com', 'nombre' => 'Estudio Ana SL', 'tipo' => 'empresa', 'nif' => 'B12345674', 'recargo_equivalencia' => false]);

        $pedido = $this->crear(['correo' => 'otra@ejemplo.com', 'tipo' => 'particular', 'nif' => '12345678Z'], $cliente);

        $this->assertSame([$cliente->id, 'ana@ejemplo.com', 'Estudio Ana SL', 'B12345674'], [$pedido->cliente_id, $pedido->correo, $pedido->nombre_fiscal, $pedido->nif]);
        $this->assertNotNull($pedido->correo_confirmado_en);
    }

    public function test_un_pedido_cancelado_no_gasta_el_cupon_de_un_solo_uso(): void
    {
        Cupon::create(['codigo' => 'BIENVENIDA', 'tipo' => 'importe', 'valor' => 1000]);
        $this->carrito->update(['cupon' => 'BIENVENIDA']);
        $this->crear(total: 12088)->update(['estado' => EstadoPedido::Cancelado]);

        $this->nuevoCarrito();
        $this->carrito->update(['cupon' => 'BIENVENIDA']);

        $this->assertSame(1000, $this->crear(total: 12088)->descuentos);
    }

    /** Si falla la copia de un archivo, no queda nada: ni filas, ni carpeta, ni número. */
    public function test_un_fallo_al_copiar_no_deja_nada(): void
    {
        Storage::disk(GuardarDiseno::DISCO)->delete('sesion/logo.png');

        try {
            $this->crear();
            $this->fail('Se ha creado un pedido sin el archivo del diseño.');
        } catch (Throwable) {
        }
        $this->assertSame([0, 0, 0], [Pedido::count(), LineaPedido::count(), (int) DB::table('contadores')->where('clave', 'pedido')->value('valor')]);
        $this->assertSame([], Storage::disk(Pedido::DISCO)->allFiles());
        $this->assertSame(1, $this->carrito->lineas()->count());
    }

    public static function repartos(): array
    {
        return ['reparto libre' => ['libre'], 'reparto fijo' => ['fijo']];
    }

    /**
     * El motor da primero las prendas y luego los packs: cada línea del pedido lleva sus propios
     * importes. Y el reparto del pack queda congelado (prenda, color y tallas), también el fijo.
     */
    #[DataProvider('repartos')]
    public function test_un_pack_delante_de_una_prenda_conserva_sus_importes_y_su_reparto(string $reparto): void
    {
        $pack = Pack::create(['nombre' => ['es' => 'Pack joven'], 'slug' => ['es' => 'pack-joven'], 'precio' => 8182, 'reparto' => $reparto]);
        $lineaPack = $pack->lineas()->create(['prenda_id' => $this->linea['prenda'], 'unidades' => 10, 'colores' => [$this->linea['color']], 'tallas_permitidas' => [$this->linea['talla']],
            'reparto_tallas' => $reparto === 'fijo' ? [$this->linea['talla'] => 10] : null]);
        $pack->zonas()->attach($this->linea['zona']);
        $this->carrito->lineas()->update(['orden' => 1]);
        $enPack = $this->carrito->lineas()->create(['tipo' => 'pack', 'pack_id' => $pack->id, 'orden' => 0,
            'elecciones' => $reparto === 'fijo' ? [] : [$lineaPack->id => ['color' => $this->linea['color'], 'tallas' => [$this->linea['talla'] => 10]]]]);
        $enPack->impresiones()->create(['zona_impresion_id' => $this->linea['zona'], 'texto' => 'NG', 'fuente' => 0, 'tinta' => 'auto', 'ancho_mm' => 100, 'alto_mm' => 60, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]);
        $total = app(ValorarCarrito::class)($this->carrito->fresh(), new Destino('ES', '28004'))->resultado->total();

        $lineas = $this->crear(total: $total)->lineas;

        $this->assertSame([['pack', 8182, 10], ['prenda', 10500, 15]], $lineas->map(fn ($l) => [$l->tipo, $l->neto, $l->unidades])->all());
        $texto = $lineas[0]->impresiones->sole();
        $this->assertSame(['NG', null, null], [$texto->texto, $texto->archivo, $texto->ppp]);
        $this->assertEquals([$lineaPack->id => ['prenda' => $this->linea['prenda'], 'color' => $this->linea['color'], 'tallas' => [$this->linea['talla'] => 10]]], $lineas[0]->elecciones);
    }

    public function test_un_diseno_usado_en_un_pedido_no_lo_borra_la_limpieza(): void
    {
        $this->crear();
        Diseno::query()->update(['created_at' => now()->subDays(60)]);

        app(LimpiarDisenos::class)();

        $this->assertTrue($this->logo->fresh() !== null);
    }
}
