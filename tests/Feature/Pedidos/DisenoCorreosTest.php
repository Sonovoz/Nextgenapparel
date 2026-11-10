<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Pedidos\EstadoPedido;
use App\Models\Ajuste;
use App\Models\Pedido;
use App\Notifications\Pedidos\CorreoDelPedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

/** Diseño de los correos (D204): logo, colores de la marca, pie de la tienda y resumen del pedido. */
class DisenoCorreosTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_correo_del_pedido_lleva_la_marca_el_pie_y_el_resumen(): void
    {
        Ajuste::guardar('tienda.correo_contacto', 'hola@nextgenapparel.es');
        $pedido = Pedido::factory()->create(['estado' => EstadoPedido::Pagado, 'idioma' => 'es', 'numero' => 'PED-0042', 'total' => 19421, 'envio' => 690]);
        $pedido->lineas()->create(['tipo' => 'prenda', 'nombre' => ['es' => 'Camiseta Essential'], 'color_nombre' => ['es' => 'Blanco'], 'unidades' => 22,
            'prendas' => 0, 'impresion' => 0, 'descuento' => 0, 'cupon' => 0, 'neto' => 0, 'tipo_iva' => 2100, 'recargo' => 0]);

        $html = (string) (new CorreoDelPedido($pedido, 'estado', EstadoPedido::Pagado))->toMail(new AnonymousNotifiable)->render();

        $this->assertStringContainsString('img/marca/logotipo.png', $html);          // el logo (incrustado al enviarlo)
        $this->assertStringContainsString('#2D4BFF', $html);                         // los colores de la marca
        $this->assertStringContainsString('Camiseta Essential', $html);              // el resumen
        $this->assertStringContainsString('194,21', $html);
        $this->assertStringContainsString('Total (IVA incluido)', $html);
        $this->assertStringContainsString('hola@nextgenapparel.es', $html);          // el pie de la tienda
        $this->assertStringContainsString('Condiciones de venta', $html);
        $this->assertStringNotContainsString('Laravel', $html);

        if ($ruta = getenv('CAPTURA_CORREO')) {
            file_put_contents($ruta, $html); // para mirarlo en el navegador al diseñar
        }
    }
}
