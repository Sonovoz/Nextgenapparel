<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Facturacion\EmisorIncompleto;
use App\Dominio\Facturacion\EmitirFactura;
use App\Dominio\Facturacion\PdfFactura;
use App\Dominio\Facturacion\RepartoReembolso;
use App\Dominio\Facturacion\TipoFactura;
use App\Dominio\Facturacion\Verifactu;
use App\Dominio\Pedidos\CambiarEstado;
use App\Dominio\Pedidos\EstadoPedido as E;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\AjustesFacturacion;
use App\Filament\Resources\Facturas\FacturaResource;
use App\Http\Controllers\PedidoController;
use App\Jobs\EmitirFacturaPedido;
use App\Models\Ajuste;
use App\Models\Factura;
use App\Models\Pedido;
use App\Notifications\Pedidos\CorreoDelPedido;
use Filament\Facades\Filament;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Facturas (paso 6.5a, D175). Pedido de ejemplo: 15 camisetas, 105,00 € sin IVA + envío
 * 4,90 €; base 109,90 €, IVA 21 % 23,08 €, total 132,98 €.
 */
class FacturasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Ajuste::guardar('facturacion.emisor', ['razon_social' => 'DTI Textil SL', 'nif' => 'B12345674', 'direccion' => 'Calle Industria 4', 'codigo_postal' => '28001', 'localidad' => 'Madrid']);
    }

    private function pedido(array $datos = [], bool $recargo = false): Pedido
    {
        $impuesto = $recargo ? ['tipo_iva' => 2100, 'recargo' => 520, 'base' => 10990, 'cuota' => 2308, 'cuota_recargo' => 571] : ['tipo_iva' => 2100, 'recargo' => 0, 'base' => 10990, 'cuota' => 2308, 'cuota_recargo' => 0];
        $pedido = Pedido::factory()->create([...['estado' => E::Pagado, 'impuestos' => 2308 + $impuesto['cuota_recargo'], 'total' => 13298 + $impuesto['cuota_recargo'],
            'desglose' => ['pasos' => [[], [], [], [], [], ['paso' => 'impuestos', 'detalle' => [$impuesto]]]]], ...$datos]);
        $linea = $pedido->lineas()->create(['tipo' => 'prenda', 'nombre' => ['es' => 'Camiseta Essential'], 'color_nombre' => ['es' => 'Negro'],
            'tallas' => [['talla_id' => 1, 'nombre' => 'M', 'unidades' => 15]], 'unidades' => 15, 'prendas' => 6000, 'impresion' => 4500,
            'descuento' => 0, 'cupon' => 0, 'neto' => 10500, 'tipo_iva' => 2100, 'recargo' => $recargo ? 520 : 0]);
        $linea->impresiones()->create(['zona_nombre' => ['es' => 'Frontal'], 'cara' => 'delantera', 'texto' => 'NG', 'ancho_mm' => 100, 'alto_mm' => 60, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]);

        return $pedido;
    }

    public function test_la_huella_coincide_con_los_ejemplos_de_la_aeat_y_se_encadena(): void
    {
        $primera = Verifactu::huella('89890001K', '12345678/G33', '01-01-2024', 'F1', 1235, 12345, null, '2024-01-01T19:20:30+01:00');
        $this->assertSame('3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60', $primera);
        $this->assertSame('F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97',
            Verifactu::huella('89890001K', '12345679/G34', '01-01-2024', 'F1', 1235, 12345, $primera, '2024-01-01T19:20:35+01:00'));
        $this->assertSame('-10.50', Verifactu::importe(-1050));
    }

    /** Simplificada sin NIF y completa con NIF (con recargo de equivalencia): cuadran con el pedido. */
    #[DataProvider('tipos')]
    public function test_la_factura_cuadra_con_el_pedido(array $datos, bool $recargo, TipoFactura $tipo, string $serie, int $total): void
    {
        $factura = app(EmitirFactura::class)->delPedido($this->pedido($datos, $recargo));

        $this->assertSame([$tipo, $serie.now()->year.'-000001', 10990, 2308, $recargo ? 571 : 0, $total],
            [$factura->tipo, $factura->codigo, $factura->base_imponible, $factura->iva, $factura->recargo, $factura->total]);
        $this->assertSame(['Camiseta Essential · Negro (M × 15) · impresión en Frontal', 'Envío'], $factura->lineas->pluck('descripcion')->all());
        $this->assertSame([10500, 490], $factura->lineas->pluck('base')->all());
        $this->assertSame(['DTI Textil SL', 'B12345674'], [$factura->emisor['razon_social'], $factura->emisor['nif']]);
        $this->assertSame('desactivado', $factura->aeat_estado);
        $this->assertStringStartsWith('%PDF', PdfFactura::generar($factura));
    }

    public static function tipos(): array
    {
        return [
            'particular sin NIF: simplificada' => [[], false, TipoFactura::Simplificada, 'T', 13298],
            'con NIF y recargo: completa' => [['nif' => 'B87654321', 'nombre_fiscal' => 'Tienda Luna SL', 'tipo_cliente' => 'empresa', 'recargo_equivalencia' => true], true, TipoFactura::Completa, 'F', 13869],
        ];
    }

    public function test_numeracion_correlativa_por_serie_y_huella_encadenada(): void
    {
        $emitir = app(EmitirFactura::class);
        $primera = $emitir->delPedido($this->pedido());
        $completa = $emitir->delPedido($this->pedido(['nif' => 'B87654321']));
        $segunda = $emitir->delPedido($this->pedido());

        $anio = now()->year;
        $this->assertSame(["T{$anio}-000001", "F{$anio}-000001", "T{$anio}-000002"], [$primera->codigo, $completa->codigo, $segunda->codigo]);
        $this->assertSame([null, $primera->huella, $completa->huella], [$primera->huella_anterior, $completa->huella_anterior, $segunda->huella_anterior]);
        $this->assertTrue($emitir->delPedido($primera->pedido)->is($primera)); // una sola factura por pedido
    }

    public function test_la_rectificativa_anula_la_factura_en_negativo(): void
    {
        $pedido = $this->pedido(['nif' => 'B87654321']);
        $factura = app(EmitirFactura::class)->delPedido($pedido);

        $rectificativa = app(EmitirFactura::class)->rectificativa($pedido, 'Cancelación');

        $this->assertSame([TipoFactura::Rectificativa, 'R'.now()->year.'-000001', -10990, -2308, -13298, $factura->id],
            [$rectificativa->tipo, $rectificativa->codigo, $rectificativa->base_imponible, $rectificativa->iva, $rectificativa->total, $rectificativa->rectifica_id]);
        $this->assertSame([-15, -10500], [$rectificativa->lineas[0]->cantidad, $rectificativa->lineas[0]->base]);
        $this->assertTrue(app(EmitirFactura::class)->rectificativa($pedido, 'Otra vez')->is($rectificativa));
        $this->assertNull(app(EmitirFactura::class)->rectificativa($this->pedido(), 'Sin factura'));
    }

    /**
     * Reembolso parcial (D178): el importe con impuestos se reparte entre los tipos en proporción a lo
     * que queda (mayor resto) y base + IVA + recargo = importe al céntimo.
     */
    #[DataProvider('repartos')]
    public function test_el_reembolso_parcial_se_reparte_sin_errores_de_redondeo(array $pendiente, int $importe, array $esperado): void
    {
        $reparto = RepartoReembolso::repartir($pendiente, $importe);

        $this->assertSame($esperado, array_map(fn ($g) => [$g['base'], $g['cuota'], $g['cuota_recargo']], $reparto));
        $this->assertSame($importe, array_sum(array_map(fn ($g) => $g['base'] + $g['cuota'] + $g['cuota_recargo'], $reparto)));
    }

    public static function repartos(): array
    {
        $iva21 = ['tipo_iva' => 2100, 'recargo' => 0, 'base' => 10000, 'cuota' => 2100, 'cuota_recargo' => 0];

        return [
            '21 %: 20,00 €' => [[$iva21], 2000, [[1653, 347, 0]]],
            '21 % con recargo 5,2 %: 20,00 €' => [[['tipo_iva' => 2100, 'recargo' => 520, 'base' => 10000, 'cuota' => 2100, 'cuota_recargo' => 520]], 2000, [[1585, 333, 82]]],
            'dos tipos: el céntimo sobrante, al resto mayor' => [[$iva21, ['tipo_iva' => 1000, 'recargo' => 0, 'base' => 1000, 'cuota' => 100, 'cuota_recargo' => 0]], 1000, [[758, 159, 0], [75, 8, 0]]],
            'todo lo que queda' => [[$iva21], 12100, [[10000, 2100, 0]]],
        ];
    }

    #[DataProvider('importesNoValidos')]
    public function test_un_reembolso_no_pasa_de_lo_que_queda(int $importe): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RepartoReembolso::repartir([['tipo_iva' => 2100, 'recargo' => 0, 'base' => 10000, 'cuota' => 2100, 'cuota_recargo' => 0]], $importe);
    }

    public static function importesNoValidos(): array
    {
        return ['cero' => [0], 'negativo' => [-100], 'más de lo facturado' => [12101]];
    }

    /** Dos reembolsos parciales y luego la cancelación: la rectificativa final es del resto y todo suma lo facturado. */
    public function test_reembolsos_parciales_y_cancelacion_suman_lo_facturado(): void
    {
        $pedido = $this->pedido(['nif' => 'B87654321']);
        $emitir = app(EmitirFactura::class);
        $factura = $emitir->delPedido($pedido);

        $primera = $emitir->rectificativaParcial($pedido, 2000, 'Reembolso parcial por la incidencia INC-0001', 'Incidencia INC-0001');
        $segunda = $emitir->rectificativaParcial($pedido, 1000, 'Reembolso parcial por la incidencia INC-0002', 'Incidencia INC-0002');
        $this->assertSame([TipoFactura::Rectificativa, true, -2000, -1653, -347, $factura->id], [$primera->tipo, $primera->parcial, $primera->total, $primera->base_imponible, $primera->iva, $primera->rectifica_id]);
        $this->assertSame([-1, -1653, 'Reembolso parcial por la incidencia INC-0001'], [$primera->lineas->sole()->cantidad, $primera->lineas->sole()->base, $primera->lineas->sole()->descripcion]);
        $this->assertSame(13298 - 3000, $emitir->pendienteDeRectificar($pedido));

        try {
            $emitir->rectificativaParcial($pedido, 10299, 'Demasiado', 'Demasiado');
            $this->fail('Un reembolso no puede pasar de lo que queda.');
        } catch (\InvalidArgumentException) {
        }

        $resto = $emitir->rectificativa($pedido, 'Cancelación');
        $this->assertSame([false, -10298], [$resto->parcial, $resto->total]);
        $this->assertSame(['Anulación del pedido (descontados los reembolsos ya rectificados)'], $resto->lineas->pluck('descripcion')->all());
        $this->assertSame([0, 0, 0], [
            $factura->base_imponible + $primera->base_imponible + $segunda->base_imponible + $resto->base_imponible,
            $factura->iva + $primera->iva + $segunda->iva + $resto->iva,
            $factura->total + $primera->total + $segunda->total + $resto->total,
        ]);
        $this->assertSame(0, $emitir->pendienteDeRectificar($pedido));
        $this->expectException(LogicException::class);
        $emitir->rectificativaParcial($pedido, 100, 'Tras cancelar', 'Tras cancelar');
    }

    public function test_si_los_reembolsos_cubren_todo_no_hay_rectificativa_al_cancelar(): void
    {
        $pedido = $this->pedido();
        $emitir = app(EmitirFactura::class);
        $emitir->delPedido($pedido);
        $emitir->rectificativaParcial($pedido, 13298, 'Todo', 'Todo');

        $this->assertNull($emitir->rectificativa($pedido, 'Cancelación'));
        $this->assertSame(0, $emitir->pendienteDeRectificar($pedido));
    }

    public function test_una_factura_no_se_cambia_ni_se_borra(): void
    {
        $factura = app(EmitirFactura::class)->delPedido($this->pedido());

        $this->expectException(LogicException::class);
        $factura->update(['total' => 1]);
    }

    public function test_sin_datos_fiscales_no_se_factura_y_se_emite_despues(): void
    {
        Ajuste::guardar('facturacion.emisor', ['razon_social' => 'DTI Textil SL']);
        $pedido = $this->pedido(['estado' => E::PendientePago]);

        app(CambiarEstado::class)($pedido, E::Pagado);

        $this->assertSame(0, Factura::count());
        $this->assertStringStartsWith('Factura pendiente: Faltan datos fiscales de la tienda: NIF, dirección', $pedido->historial()->get()->last()->nota);
        try {
            app(EmitirFactura::class)->delPedido($pedido);
            $this->fail('Ha facturado sin datos fiscales.');
        } catch (EmisorIncompleto) {
        }

        Ajuste::guardar('facturacion.emisor', ['razon_social' => 'DTI Textil SL', 'nif' => 'B12345674', 'direccion' => 'Calle Industria 4', 'codigo_postal' => '28001', 'localidad' => 'Madrid']);
        $this->artisan('facturas:emitir-pendientes')->assertSuccessful();
        $this->assertSame(1, $pedido->facturas()->count());
    }

    /** Al darlo por pagado se factura y se envía con el PDF; si se cancela, rectificativa. */
    public function test_pagar_factura_y_cancelar_rectifica(): void
    {
        $pedido = $this->pedido(['estado' => E::PendientePago]);

        app(CambiarEstado::class)($pedido, E::Pagado);

        $factura = $pedido->facturas()->sole();
        Notification::assertSentOnDemand(CorreoDelPedido::class, function (CorreoDelPedido $n, $c, AnonymousNotifiable $a) use ($factura) {
            if ($n->tipo !== 'factura') {
                return false;
            }
            $adjunto = $n->toMail($a)->rawAttachments[0];

            return $adjunto['name'] === 'factura-'.$factura->codigo.'.pdf' && str_starts_with($adjunto['data'], '%PDF');
        });

        app(CambiarEstado::class)($pedido, E::Cancelado, nota: 'Cliente pesado, no volver a venderle');
        $this->assertSame([TipoFactura::Simplificada, TipoFactura::RectificativaSimplificada], $pedido->facturas()->pluck('tipo')->all());
        // M2: el motivo de la rectificativa (impreso en el PDF del cliente) es fijo y en su idioma; la nota se queda en el historial.
        $this->assertSame('Cancelación del pedido '.$pedido->numero, $pedido->facturas()->get()->last()->motivo);
        $this->assertSame('Cliente pesado, no volver a venderle', $pedido->historial()->get()->first(fn ($h) => $h->estado === E::Cancelado)->nota);
    }

    /**
     * A1: la factura se decide bajo el bloqueo del pedido. Un reintento tras cancelar no deja una factura sin
     * rectificar, y otra emisión (la orden de las 06:15 o la cola) no la duplica.
     */
    public function test_un_pedido_cancelado_no_se_factura_y_nunca_hay_dos_facturas(): void
    {
        $emitir = app(EmitirFactura::class);
        $cancelado = $this->pedido(['estado' => E::Cancelado]);
        $this->assertNull($emitir->delPedido($cancelado));
        $this->assertSame(0, $cancelado->facturas()->count());
        $this->assertStringContainsString('Factura no emitida', (string) $cancelado->historial()->latest('id')->value('nota'));

        $pagado = $this->pedido();
        (new EmitirFacturaPedido($pagado->id))->handle($emitir);
        $this->artisan('facturas:emitir-pendientes')->assertSuccessful();
        (new EmitirFacturaPedido($pagado->id))->handle($emitir);
        $this->assertSame(1, $pagado->facturas()->count());

        // Y la base de datos lo impide aunque algo se saltara el bloqueo.
        $this->expectException(UniqueConstraintViolationException::class);
        Factura::query()->whereKey($pagado->facturas()->value('id'))->first()->replicate(['uuid', 'codigo', 'huella_anterior'])
            ->fill(['uuid' => (string) Str::uuid(), 'codigo' => 'X-1', 'numero' => 999])->saveQuietly();
    }

    /** M1: factura, rectificativa parcial y rectificativa del resto, cada una encadenada con la anterior. */
    public function test_la_cadena_de_huellas_no_se_parte_con_varios_tipos(): void
    {
        $emitir = app(EmitirFactura::class);
        $pedido = $this->pedido(['nif' => 'B87654321']);
        $otro = $this->pedido();
        $emitir->delPedido($pedido);
        $emitir->rectificativaParcial($pedido, 500, 'Reembolso', 'Incidencia INC-0001');
        $emitir->delPedido($otro);
        $emitir->rectificativa($pedido, 'Cancelación');

        $facturas = Factura::orderBy('id')->get();
        $this->assertCount(4, $facturas);
        foreach ($facturas as $i => $factura) {
            $this->assertSame($i === 0 ? null : $facturas[$i - 1]->huella, $factura->huella_anterior, "Factura {$factura->codigo}");
        }
    }

    public function test_el_pdf_solo_lo_descarga_quien_puede_ver_el_pedido(): void
    {
        Filament::setCurrentPanel('admin');
        $pedido = $this->pedido();
        $factura = app(EmitirFactura::class)->delPedido($pedido);
        $cliente = '/es/pedidos/'.$pedido->uuid.'/facturas/'.$factura->uuid;
        $backend = route('backend.pedidos.factura', ['pedido' => $pedido->uuid, 'factura' => $factura->uuid]);

        $this->get($cliente)->assertNotFound();
        $this->withSession([PedidoController::SESION => [$pedido->uuid]])->get($cliente)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->withSession([PedidoController::SESION => [$pedido->uuid]])->get('/es/pedidos/'.$pedido->uuid)->assertSee('Descargar T'.now()->year.'-000001 (PDF)');
        $this->actingAs($this->usuarioConRol(Rol::Produccion))->get($backend)->assertNotFound();
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda))->get($backend)->assertOk();
        // Fallo encontrado en la pasada de navegador: la lista de facturas daba un 500.
        $this->get(FacturaResource::getUrl('index'))->assertOk()->assertSee($factura->codigo)->assertSee('Factura simplificada');
        $this->actingAs($this->usuarioConRol(Rol::Produccion))->get(FacturaResource::getUrl('index'))->assertForbidden();
    }

    public function test_ajustes_de_facturacion_y_series_bloqueadas_tras_la_primera(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(AjustesFacturacion::class)->fillForm(['series.simplificada' => 'S', 'emisor.correo' => 'facturas@dti.es'])
            ->callAction('guardar')->assertNotified('Ajustes guardados');
        $this->assertSame(['S', 'facturas@dti.es'], [Ajuste::obtener('facturacion.series')['simplificada'], Ajuste::obtener('facturacion.emisor')['correo']]);

        app(EmitirFactura::class)->delPedido($this->pedido());
        Livewire::test(AjustesFacturacion::class)->assertFormFieldIsDisabled('series.simplificada');
    }
}
