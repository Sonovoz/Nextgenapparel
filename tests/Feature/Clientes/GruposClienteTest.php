<?php

namespace Tests\Feature\Clientes;

use App\Dominio\Carrito\ValorarCarrito;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\GruposCliente\GrupoClienteResource;
use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\Color;
use App\Models\Familia;
use App\Models\GrupoCliente;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tarifas por grupo (v2, D192): el carrito de un cliente de un grupo lleva su descuento. */
class GruposClienteTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_carrito_de_un_cliente_del_grupo_lleva_su_descuento(): void
    {
        $negro = Color::factory()->create();
        $m = Talla::factory()->create();
        $frontal = ZonaImpresion::factory()->create(['ancho_max_mm' => 280, 'alto_max_mm' => 380, 'desde_cuello_mm' => 75]);
        $prenda = Prenda::factory()->for(Familia::factory()->create())->create();
        Variante::factory()->for($prenda)->create(['color_id' => $negro->id, 'talla_id' => $m->id, 'precio_base' => 400]);
        $prenda->zonas()->attach($frontal->id, ['precio' => 300, 'coste' => 100]);
        $grupo = GrupoCliente::create(['nombre' => ['es' => 'Mayorista', 'en' => 'Wholesale'], 'descuento' => 2000]);
        $cliente = Cliente::factory()->create(['grupo_cliente_id' => $grupo->id]);
        $carrito = Carrito::create(['cliente_id' => $cliente->id]);
        $linea = $carrito->lineas()->create(['tipo' => 'prenda', 'prenda_id' => $prenda->id, 'color_id' => $negro->id, 'tallas' => [$m->id => 15]]);
        $linea->impresiones()->create(['zona_impresion_id' => $frontal->id, 'texto' => 'NG', 'fuente' => 0, 'tinta' => 'auto', 'ancho_mm' => 100, 'alto_mm' => 60, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]);

        $r = app(ValorarCarrito::class)($carrito)->resultado;

        $this->assertSame([2100, 8400], [$r->descuentos(), $r->subtotal()]); // 20 % de 105,00
        $this->assertContains('Aplicamos tu tarifa «Mayorista»: ahorras 21,00 €.', $r->textos('avisos', 'es'));
        $cliente->update(['grupo_cliente_id' => null]);
        $this->assertSame(0, app(ValorarCarrito::class)($carrito->fresh())->resultado->descuentos());
    }

    public function test_los_gestiona_quien_tiene_clientes(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda))->get(GrupoClienteResource::getUrl('index'))->assertOk();
        $this->actingAs($this->usuarioConRol(Rol::Produccion))->get(GrupoClienteResource::getUrl('index'))->assertForbidden();
    }
}
