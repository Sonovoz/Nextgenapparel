<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Facturacion\EmitirFactura;
use App\Dominio\Informes\InformeVentas;
use App\Dominio\Pedidos\EstadoPedido as E;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Pages\Informes;
use App\Models\Ajuste;
use App\Models\Factura;
use App\Models\Pedido;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Informe de ventas (paso 8.2, D184): sale de las facturas y cuadra con ellas. */
class InformesTest extends TestCase
{
    use RefreshDatabase;

    private function pedidoFacturado(string $numero, int $base, ?string $cupon = null): Pedido
    {
        $iva = intdiv($base * 21 + 50, 100);
        $pedido = Pedido::factory()->create(['estado' => E::Pagado, 'numero' => $numero, 'cupon' => $cupon, 'total' => $base + $iva, 'impuestos' => $iva,
            'desglose' => ['pasos' => [[], [], [], [], [], ['paso' => 'impuestos', 'detalle' => [['tipo_iva' => 2100, 'recargo' => 0, 'base' => $base, 'cuota' => $iva, 'cuota_recargo' => 0]]]]]]);
        $pedido->lineas()->create(['tipo' => 'prenda', 'nombre' => ['es' => 'Camiseta Essential'], 'unidades' => 15, 'prendas' => 0, 'impresion' => 0, 'descuento' => 0, 'cupon' => 0, 'neto' => $base, 'tipo_iva' => 2100, 'recargo' => 0]);
        app(EmitirFactura::class)->delPedido($pedido);

        return $pedido;
    }

    public function test_el_informe_cuadra_con_las_facturas_del_periodo(): void
    {
        Ajuste::guardar('facturacion.emisor', ['razon_social' => 'DTI Textil SL', 'nif' => 'B12345674', 'direccion' => 'Calle Industria 4', 'codigo_postal' => '28001', 'localidad' => 'Madrid']);
        $this->pedidoFacturado('PED-0001', 10000, 'BIENVENIDA');
        $cancelado = $this->pedidoFacturado('PED-0002', 20000);
        app(EmitirFactura::class)->rectificativa($cancelado, 'Cancelación');
        $hoy = CarbonImmutable::now('Europe/Madrid');

        $d = InformeVentas::datos($hoy->startOfMonth(), $hoy);

        // 121,00 + 242,00 facturado; −242,00 rectificado; neto 121,00 (base 100,00 e IVA 21,00).
        $this->assertSame([36300, 24200, 12100, 10000, 2100, 2, 18150], [$d['facturado'], $d['rectificado'], $d['neto'], $d['base'], $d['iva'], $d['pedidos'], $d['ticket']]);
        $this->assertSame([['prenda' => 'Camiseta Essential', 'unidades' => 30, 'base' => 30000, 'coste' => 0, 'margen' => 30000]], $d['prendas']);
        $this->assertSame([['cupon' => 'BIENVENIDA', 'pedidos' => 1]], $d['cupones']);
        $csv = InformeVentas::csv($hoy->startOfMonth(), $hoy);
        $this->assertSame(4, substr_count($csv, "\n")); // cabecera + 3 facturas
        // Auditoría final, M3: un nombre que empieza por «=» no es una fórmula en Excel.
        $raro = $this->pedidoFacturado('PED-0003', 1000);
        Factura::where('pedido_id', $raro->id)->update(['destinatario' => json_encode(['nombre' => '=HYPERLINK("http://x")', 'nif' => null])]);
        $this->assertStringContainsString(";\"'=HYPERLINK(\"\"http://x\"\")\";", InformeVentas::csv($hoy->startOfMonth(), $hoy));
        $this->assertSame(0, InformeVentas::datos($hoy->subYear()->startOfMonth(), $hoy->subYear())['pedidos']);
    }

    /** D191: margen = vendido sin IVA ni envío − coste guardado (o estimado con el coste actual); sin los cancelados. */
    public function test_el_margen_resta_el_coste_y_no_cuenta_los_cancelados(): void
    {
        Ajuste::guardar('facturacion.emisor', ['razon_social' => 'DTI Textil SL', 'nif' => 'B12345674', 'direccion' => 'Calle Industria 4', 'codigo_postal' => '28001', 'localidad' => 'Madrid']);
        $guardado = $this->pedidoFacturado('PED-0001', 10000);
        $guardado->lineas()->update(['coste' => 6000]);
        $cancelado = $this->pedidoFacturado('PED-0002', 50000);
        $cancelado->update(['estado' => E::Cancelado]);
        $hoy = CarbonImmutable::now('Europe/Madrid');

        $m = InformeVentas::datos($hoy->startOfMonth(), $hoy)['margen'];

        $this->assertSame([10000, 6000, 4000, 4000, 0], [$m['ingresos'], $m['coste'], $m['margen'], $m['porcentaje'], $m['estimadas']]);
    }

    public function test_solo_lo_ve_quien_tiene_analiticas(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::Administrador))->get(Informes::getUrl())->assertOk()->assertSee('Facturado');
        $this->actingAs($this->usuarioConRol(Rol::Produccion))->get(Informes::getUrl())->assertForbidden();
    }
}
