<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Carrito\CarritoActual;
use App\Dominio\Clientes\BorrarCliente;
use App\Dominio\Clientes\ClienteConPedidos;
use App\Http\Controllers\PedidoController;
use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\Color;
use App\Models\Familia;
use App\Models\Pedido;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\TarifaEnvio;
use App\Models\User;
use App\Models\Variante;
use App\Models\ZonaEnvio;
use App\Models\ZonaImpresion;
use App\Notifications\Pedidos\ConfirmaTuCorreo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Confirmar el pedido desde el cierre, verlo y confirmar el correo del invitado
 * (paso 6.1, D169). Mismo carrito que el cierre: total 132,98 €.
 */
class ConfirmarPedidoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $negro = Color::factory()->create();
        $m = Talla::factory()->create(['nombre' => 'M']);
        $zona = ZonaImpresion::factory()->create(['ancho_max_mm' => 280, 'alto_max_mm' => 380, 'desde_cuello_mm' => 75]);
        $prenda = Prenda::factory()->for(Familia::factory()->create())->create();
        Variante::factory()->for($prenda)->create(['color_id' => $negro->id, 'talla_id' => $m->id, 'precio_base' => 400, 'peso_gramos' => 150]);
        $prenda->zonas()->attach($zona->id, ['precio' => 300, 'coste' => 100]);
        $peninsula = ZonaEnvio::create(['nombre' => ['es' => 'Península'], 'paises' => ['ES'], 'metodo' => 'por_kilo', 'precio_kg_extra' => 80, 'plazo_dias' => 2]);
        TarifaEnvio::create(['zona_envio_id' => $peninsula->id, 'hasta_gramos' => 5000, 'precio' => 490]);

        $carrito = Carrito::create(['token' => hash('sha256', $token = str_repeat('t', 40))]);
        $linea = $carrito->lineas()->create(['tipo' => 'prenda', 'prenda_id' => $prenda->id, 'color_id' => $negro->id, 'tallas' => [$m->id => 15]]);
        $linea->impresiones()->create(['zona_impresion_id' => $zona->id, 'texto' => 'NG', 'fuente' => 0, 'tinta' => 'auto', 'ancho_mm' => 100, 'alto_mm' => 60, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0]);
        $this->withCookie(CarritoActual::COOKIE, $token);
    }

    private function datos(array $cambios = []): array
    {
        return [...['correo' => 'invitada@ejemplo.com', 'tipo' => 'particular', 'nif' => '', 'nombre' => 'Lucía Pérez', 'direccion' => 'Calle Luna 3',
            'codigo_postal' => '28004', 'localidad' => 'Madrid', 'pais' => 'ES', 'telefono' => '600 123 123'], ...$cambios];
    }

    /** El invitado calcula el total y confirma: ve su pedido y le llega el enlace para confirmar el correo. */
    private function pedidoDeInvitado(): Pedido
    {
        $this->post('/es/carrito/cerrar-pedido', $this->datos());
        $this->post('/es/carrito/cerrar-pedido/confirmar', ['total' => 13298, 'condiciones' => '1'])->assertRedirect();

        return Pedido::sole();
    }

    public function test_el_invitado_confirma_ve_su_pedido_y_recibe_el_enlace(): void
    {
        $this->post('/es/carrito/cerrar-pedido', $this->datos());
        $this->get('/es/carrito/cerrar-pedido')->assertSee('Confirmar pedido')->assertSee('· 132,98 €')->assertSee('He leído y acepto');

        $this->post('/es/carrito/cerrar-pedido/confirmar', ['total' => 13298])->assertSessionHasErrors('condiciones');
        $this->assertSame(0, Pedido::count());

        $respuesta = $this->post('/es/carrito/cerrar-pedido/confirmar', ['total' => 13298, 'condiciones' => '1']);
        $pedido = Pedido::sole();
        $respuesta->assertRedirect('/es/pedidos/'.$pedido->uuid);
        $this->get('/es/pedidos/'.$pedido->uuid)->assertOk()->assertSee('Pedido PED-0001')->assertSee('Pendiente de pago')
            ->assertSee('invitada@ejemplo.com')->assertSee('132,98 €')->assertSee('Texto «NG»')->assertSee('noindex', false);
        $this->get('/en/orders/'.$pedido->uuid)->assertOk()->assertSee('Order PED-0001')->assertSee('Awaiting payment');

        Notification::assertSentOnDemand(ConfirmaTuCorreo::class, fn ($n, $canales, AnonymousNotifiable $a) => $a->routes['mail'] === 'invitada@ejemplo.com' && $n->pedido->is($pedido));
        $this->assertSame(0, Carrito::count());
        $this->assertSame([$pedido->uuid], session(PedidoController::SESION));
    }

    public function test_el_total_viejo_no_crea_el_pedido_y_explica_por_que(): void
    {
        $this->post('/es/carrito/cerrar-pedido', $this->datos());
        $this->post('/es/carrito/cerrar-pedido/confirmar', ['total' => 12000, 'condiciones' => '1'])->assertRedirect('/es/carrito/cerrar-pedido#total');

        $this->assertSame(0, Pedido::count());
        $this->get('/es/carrito/cerrar-pedido')->assertSee('El total ha cambiado');
    }

    public function test_sin_los_datos_del_cierre_vuelve_al_cierre(): void
    {
        $this->post('/es/carrito/cerrar-pedido/confirmar', ['total' => 13298, 'condiciones' => '1'])->assertRedirect('/es/carrito/cerrar-pedido');
        $this->assertSame(0, Pedido::count());
    }

    public function test_un_pedido_solo_lo_ve_quien_lo_hizo(): void
    {
        $pedido = $this->pedidoDeInvitado();

        // Otro navegador, sin enlace ni cuenta: como si no existiera.
        $this->flushSession();
        $this->get('/es/pedidos/'.$pedido->uuid)->assertNotFound();
        // Otra cuenta, tampoco.
        $otra = User::factory()->create();
        Cliente::factory()->create(['user_id' => $otra->id, 'correo' => $otra->email]);
        $this->actingAs($otra)->get('/es/pedidos/'.$pedido->uuid)->assertNotFound();
    }

    public function test_el_enlace_confirma_el_correo_y_pasa_el_pedido_a_una_ficha_nueva(): void
    {
        $pedido = $this->pedidoDeInvitado();
        $this->flushSession(); // lo abre en el móvil

        $this->get(PedidoController::enlaceConfirmar($pedido))->assertRedirect('/es/pedidos/'.$pedido->uuid);
        $this->get('/es/pedidos/'.$pedido->uuid)->assertOk()->assertSee('tu correo está confirmado')->assertDontSee('Enviarme otro enlace');

        $pedido->refresh();
        $cliente = Cliente::where('correo', 'invitada@ejemplo.com')->sole();
        $this->assertSame([$cliente->id, true, null], [$pedido->cliente_id, $pedido->correoConfirmado(), $cliente->user_id]);
        $this->assertSame(['Lucía Pérez', '600 123 123'], [$cliente->nombre, $cliente->telefono]);
    }

    public function test_si_el_correo_ya_tiene_cuenta_el_pedido_aparece_en_ella_solo_tras_confirmar(): void
    {
        $usuario = User::factory()->create(['email' => 'invitada@ejemplo.com']);
        $suya = Cliente::factory()->create(['user_id' => $usuario->id, 'correo' => 'invitada@ejemplo.com']);
        $pedido = $this->pedidoDeInvitado();

        $this->assertNull($pedido->cliente_id);
        $this->actingAs($usuario)->get('/es/pedidos/'.$pedido->uuid)->assertOk(); // en esta sesión lo creó ella
        $this->flushSession();
        $this->actingAs($usuario)->get('/es/pedidos/'.$pedido->uuid)->assertNotFound(); // sin confirmar, no es suyo

        $this->get(PedidoController::enlaceConfirmar($pedido));
        $this->flushSession();
        $this->actingAs($usuario)->get('/es/pedidos/'.$pedido->uuid)->assertOk();
        $this->assertSame($suya->id, $pedido->fresh()->cliente_id);
        $this->assertSame(1, Cliente::count());
    }

    public function test_un_enlace_caducado_o_manipulado_permite_pedir_otro(): void
    {
        $pedido = $this->pedidoDeInvitado();
        Notification::fake();

        $this->travel(8)->days();
        $this->get(PedidoController::enlaceConfirmar($pedido).'x')->assertOk()->assertSee('Este enlace ya no vale');
        $caducado = URL::temporarySignedRoute('pedidos.es.confirmar-correo', now()->subMinute(), ['pedido' => $pedido->uuid]);
        $this->get($caducado)->assertOk()->assertSee('Enviarme otro enlace');
        $this->assertFalse($pedido->fresh()->correoConfirmado());

        $this->flushSession();
        $this->from($caducado)->post('/es/pedidos/'.$pedido->uuid.'/reenviar-enlace')->assertRedirect($caducado);
        Notification::assertSentOnDemandTimes(ConfirmaTuCorreo::class, 1);
    }

    public function test_con_cuenta_el_pedido_es_suyo_desde_el_principio_y_no_hay_enlace(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@ejemplo.com']);
        $cliente = Cliente::factory()->create(['user_id' => $usuario->id, 'correo' => 'ana@ejemplo.com', 'nombre' => 'Ana García']);
        Carrito::query()->update(['token' => null, 'cliente_id' => $cliente->id]);

        $this->actingAs($usuario)->post('/es/carrito/cerrar-pedido', $this->datos());
        $this->actingAs($usuario)->post('/es/carrito/cerrar-pedido/confirmar', ['total' => 13298, 'condiciones' => '1']);

        $pedido = Pedido::sole();
        $this->assertSame([$cliente->id, true], [$pedido->cliente_id, $pedido->correoConfirmado()]);
        Notification::assertNothingSent();
        $this->flushSession();
        $this->actingAs($usuario)->get('/es/pedidos/'.$pedido->uuid)->assertOk()->assertSee('Ana García')->assertDontSee('Enviarme otro enlace');
    }

    /** Doble clic: la segunda petición lleva al pedido que creó la primera, no a «carrito vacío» (M3). */
    public function test_el_doble_clic_lleva_al_mismo_pedido(): void
    {
        $pedido = $this->pedidoDeInvitado();

        $this->post('/es/carrito/cerrar-pedido/confirmar', ['total' => 13298, 'condiciones' => '1'])->assertRedirect('/es/pedidos/'.$pedido->uuid);
        $this->assertSame(1, Pedido::count());
    }

    public function test_si_la_cuenta_se_bloquea_despues_del_cierre_no_se_confirma(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@ejemplo.com']);
        $cliente = Cliente::factory()->create(['user_id' => $usuario->id, 'correo' => 'ana@ejemplo.com']);
        Carrito::query()->update(['token' => null, 'cliente_id' => $cliente->id]);
        $this->actingAs($usuario)->post('/es/carrito/cerrar-pedido', $this->datos());

        $cliente->update(['activo' => false]);
        $this->actingAs($usuario->fresh())->post('/es/carrito/cerrar-pedido/confirmar', ['total' => 13298, 'condiciones' => '1'])->assertRedirect('/es/carrito/cerrar-pedido');
        $this->assertSame(0, Pedido::count());
    }

    /** Revisión N3 del 6.1, A2: sus pedidos guardan sus datos; se anonimizará con el criterio del asesor. */
    public function test_un_cliente_con_pedidos_no_se_borra(): void
    {
        $pedido = $this->pedidoDeInvitado();
        $this->get(PedidoController::enlaceConfirmar($pedido));
        $cliente = Cliente::sole();

        $this->expectException(ClienteConPedidos::class);
        app(BorrarCliente::class)($cliente, User::factory()->create());
    }
}
