<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Carrito\CarritoActual;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Presupuestos\PresupuestoResource;
use App\Http\Controllers\PresupuestoController;
use App\Models\Carrito;
use App\Models\Color;
use App\Models\Familia;
use App\Models\Prenda;
use App\Models\Presupuesto;
use App\Models\Talla;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Presupuestos en PDF (D187). Ejemplo del cierre (D166): 15 camisetas a 4,00 € + 3,00 € de impresión
 * de texto, 105,00 € sin IVA; con IVA 127,05 € (sin envío).
 */
class PresupuestosTest extends TestCase
{
    use RefreshDatabase;

    private Carrito $carrito;

    private Prenda $prenda;

    protected function setUp(): void
    {
        parent::setUp();
        $negro = Color::factory()->create(['nombre' => ['es' => 'Negro', 'en' => 'Black']]);
        $m = Talla::factory()->create(['nombre' => 'M']);
        $frontal = ZonaImpresion::factory()->create(['nombre' => ['es' => 'Frontal'], 'ancho_max_mm' => 280, 'alto_max_mm' => 380, 'desde_cuello_mm' => 75]);
        $this->prenda = Prenda::factory()->for(Familia::factory()->create())->create(['nombre' => ['es' => 'Camiseta Essential']]);
        Variante::factory()->for($this->prenda)->create(['color_id' => $negro->id, 'talla_id' => $m->id, 'precio_base' => 400]);
        $this->prenda->zonas()->attach($frontal->id, ['precio' => 300, 'coste' => 100]);

        $this->carrito = Carrito::create(['token' => hash('sha256', str_repeat('p', 40))]);
        $linea = $this->carrito->lineas()->create(['tipo' => 'prenda', 'prenda_id' => $this->prenda->id, 'color_id' => $negro->id, 'tallas' => [$m->id => 15]]);
        $linea->impresiones()->create(['zona_impresion_id' => $frontal->id, 'texto' => 'NG', 'fuente' => 0, 'tinta' => 'auto', 'ancho_mm' => 100, 'alto_mm' => 60, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]);
    }

    private function conCarrito(): self
    {
        return $this->withCookie(CarritoActual::COOKIE, str_repeat('p', 40));
    }

    public function test_el_presupuesto_copia_las_lineas_e_importes_del_motor_y_se_descarga(): void
    {
        $this->conCarrito()->post(route('presupuestos.es.crear'))->assertRedirect();

        $p = Presupuesto::sole();
        $this->assertSame(['PRE-0001', 10500, 12705], [$p->numero, $p->contenido['bruto'], $p->total]);
        $this->assertSame('Camiseta Essential · Negro', $p->contenido['lineas'][0]['descripcion']);
        $this->assertStringContainsString('Frontal 10 × 6 cm', $p->contenido['lineas'][0]['detalle']);
        $this->assertSame(now()->addHours(48)->format('Y-m-d H:i'), $p->valido_hasta->format('Y-m-d H:i')); // D216: 48 horas
        $this->get(PresupuestoController::url('ver', $p))->assertOk()->assertSee('PRE-0001')->assertSee('127,05');
        $pdf = $this->get(PresupuestoController::url('pdf', $p))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertSame(1, $this->carrito->lineas()->count()); // el carrito sigue igual
    }

    public function test_un_carrito_que_no_se_puede_comprar_no_da_presupuesto(): void
    {
        $this->prenda->update(['activa' => false]);

        $this->conCarrito()->post(route('presupuestos.es.crear'))->assertSessionHas('estado', __('presupuestos.no_se_puede'));
        $this->assertSame(0, Presupuesto::count());
    }

    public function test_vigente_pasa_al_carrito_y_avisa_si_cambia_el_precio_caducado_no(): void
    {
        $this->conCarrito()->post(route('presupuestos.es.crear'));
        $p = Presupuesto::sole();
        $this->carrito->delete();
        Variante::query()->update(['precio_base' => 500]); // sube la tarifa

        $this->post(PresupuestoController::url('pasar', $p))->assertSessionHas('estado', fn (string $m) => str_contains($m, '1 línea') && str_contains($m, 'antes 127,05 €'));
        $this->assertNotNull($p->fresh()->convertido_en);
        $this->assertSame(1, Carrito::sole()->lineas()->count());

        $this->travel(49)->hours();
        $this->post(PresupuestoController::url('pasar', $p))->assertSessionHas('estado', __('presupuestos.caducado'));
    }

    public function test_el_backend_los_lista(): void
    {
        Filament::setCurrentPanel('admin');
        $this->conCarrito()->post(route('presupuestos.es.crear'));

        $this->actingAs($this->usuarioConRol(Rol::GestorTienda))->get(PresupuestoResource::getUrl('index'))->assertOk()->assertSee('PRE-0001');
        $this->actingAs($this->usuarioConRol(Rol::Produccion))->get(PresupuestoResource::getUrl('index'))->assertForbidden();
    }

    public function test_pedir_el_presupuesto_descargarlo_y_volver_no_duplica_el_carrito(): void
    {
        // Error real: tras bajar el PDF, «Pasar al carrito» volvía a poner las líneas que ya estaban.
        $this->conCarrito()->post(route('presupuestos.es.crear'));
        $p = Presupuesto::sole();
        $this->conCarrito()->get(PresupuestoController::url('pdf', $p))->assertOk();

        // La página del presupuesto ofrece volver al carrito, no pasarlo otra vez.
        $this->conCarrito()->get(PresupuestoController::url('ver', $p))->assertOk()
            ->assertSee(__('presupuestos.volver_carrito'))->assertDontSee(__('presupuestos.pasar'));

        // Y aunque se envíe (doble clic, pestaña vieja), no se duplica.
        $this->conCarrito()->post(PresupuestoController::url('pasar', $p))->assertSessionHas('estado', __('presupuestos.ya_en_carrito'));
        $this->assertSame(1, $this->carrito->lineas()->count());

        // Una segunda vuelta tampoco: el carrito sigue con su producto, una vez.
        $this->conCarrito()->post(route('presupuestos.es.crear'));
        $p2 = Presupuesto::latest('id')->first();
        $this->conCarrito()->get(PresupuestoController::url('pdf', $p2))->assertOk();
        $this->conCarrito()->post(PresupuestoController::url('pasar', $p2));
        $this->assertSame(1, $this->carrito->lineas()->count());
        $this->assertSame(1, Carrito::count());
    }

    public function test_si_el_carrito_ya_no_lo_tiene_se_puede_pasar_otra_vez(): void
    {
        $this->conCarrito()->post(route('presupuestos.es.crear'));
        $p = Presupuesto::sole();
        $this->carrito->lineas()->delete(); // lo ha quitado del carrito

        $this->conCarrito()->get(PresupuestoController::url('ver', $p))->assertSee(__('presupuestos.pasar'));
        $this->conCarrito()->post(PresupuestoController::url('pasar', $p));
        $this->assertSame(1, $this->carrito->lineas()->count());
    }

    public function test_el_pdf_es_el_premium_con_jost_y_enlaza_preguntas_y_condiciones(): void
    {
        // D215: la letra de la marca, el texto de la colección y los enlaces a las preguntas frecuentes y las condiciones.
        $this->conCarrito()->post(route('presupuestos.es.crear'));
        $p = Presupuesto::sole();

        $pdf = $this->get(PresupuestoController::url('pdf', $p))->assertOk()->getContent();

        $this->assertStringContainsString('Jost', $pdf);
        $this->assertStringContainsString(route('portada', ['idioma' => 'es']).'#preguntas', $pdf);
        $this->assertStringContainsString(route('legal', ['idioma' => 'es', 'pagina' => 'condiciones-de-venta']), $pdf);
        $this->assertStringContainsString(PresupuestoController::url('ver', $p), $pdf); // «Abrir mi presupuesto»
    }
}
