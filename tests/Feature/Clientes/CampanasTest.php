<?php

namespace Tests\Feature\Clientes;

use App\Dominio\Marketing\Campanas;
use App\Dominio\Pedidos\EstadoPedido as E;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Campanas\CampanaResource;
use App\Models\Ajuste;
use App\Models\Campana;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\EnvioCampana;
use App\Models\Pedido;
use App\Models\User;
use App\Notifications\Marketing\CorreoComercial;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Campañas (v2, D190): nueva compra, reactivación y campañas manuales, solo con consentimiento y una vez. */
class CampanasTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $usuario = User::factory()->create(['email' => 'ana@ejemplo.com', 'email_verified_at' => now()]);
        $this->cliente = Cliente::factory()->create(['user_id' => $usuario->id, 'correo' => 'ana@ejemplo.com', 'marketing_consentido_en' => now()]);
    }

    private function pedidoEntregadoHace(int $dias, ?Cliente $cliente = null): Pedido
    {
        $pedido = Pedido::factory()->create(['estado' => E::Entregado, 'cliente_id' => ($cliente ?? $this->cliente)->id, 'created_at' => now()->subDays($dias + 5)]);
        $pedido->historial()->create(['estado' => E::Entregado, 'created_at' => now()->subDays($dias)]);

        return $pedido;
    }

    public function test_nueva_compra_a_los_dias_de_la_entrega_y_una_vez_por_pedido(): void
    {
        Ajuste::guardar('campanas.nueva_compra_activa', true);
        $this->pedidoEntregadoHace(10);
        $this->assertSame(0, app(Campanas::class)->nuevaCompra()); // aún no: 30 días

        $this->pedidoEntregadoHace(31);
        $this->assertSame(1, app(Campanas::class)->nuevaCompra());
        $this->assertSame(0, app(Campanas::class)->nuevaCompra());
        Notification::assertSentOnDemand(CorreoComercial::class, fn (CorreoComercial $n) => str_contains($n->enlace, '/acceso'));
    }

    public function test_reactivacion_a_quien_no_compra_y_una_vez_por_periodo(): void
    {
        Ajuste::guardar('campanas.reactivacion_activa', true);
        Cupon::create(['codigo' => 'VUELVE', 'tipo' => 'porcentaje', 'valor' => 1000, 'activo' => true]);
        Ajuste::guardar('campanas.reactivacion_cupon', 'VUELVE');
        $this->pedidoEntregadoHace(200);
        $reciente = Cliente::factory()->create(['user_id' => User::factory()->create(['email_verified_at' => now()])->id, 'marketing_consentido_en' => now()]);
        Pedido::factory()->create(['cliente_id' => $reciente->id, 'created_at' => now()->subDays(20)]);

        $this->assertSame(1, app(Campanas::class)->reactivacion());
        $this->assertSame(0, app(Campanas::class)->reactivacion());
        Notification::assertSentOnDemand(CorreoComercial::class, fn (CorreoComercial $n) => $n->cupon === 'VUELVE' && $n->cliente->is($this->cliente));
    }

    public function test_sin_consentimiento_o_desactivadas_no_sale_nada(): void
    {
        $this->pedidoEntregadoHace(40);
        $this->assertSame(0, app(Campanas::class)->nuevaCompra()); // desactivada por defecto

        Ajuste::guardar('campanas.nueva_compra_activa', true);
        $this->cliente->update(['marketing_consentido_en' => null]);
        $this->assertSame(0, app(Campanas::class)->nuevaCompra());
        Notification::assertNothingSent();
    }

    public function test_una_campana_va_a_su_segmento_una_sola_vez(): void
    {
        $this->pedidoEntregadoHace(5);
        Cliente::factory()->create(['user_id' => User::factory()->create(['email_verified_at' => now()])->id, 'marketing_consentido_en' => now()]); // sin pedidos
        $campana = Campana::create(['nombre' => 'Verano', 'asunto' => ['es' => 'Verano', 'en' => 'Summer'], 'texto' => ['es' => 'Nuevos colores.', 'en' => 'New colours.'], 'segmento' => 'con_pedidos']);

        $this->assertSame(1, app(Campanas::class)->campana($campana));
        $this->assertSame(0, app(Campanas::class)->campana($campana->fresh()));
        $this->assertSame([1, 1], [$campana->fresh()->enviados, EnvioCampana::where('tipo', 'campana')->count()]);
        Notification::assertSentOnDemand(CorreoComercial::class, fn (CorreoComercial $n) => $n->asunto === 'Verano');
    }

    public function test_marketing_gestiona_las_campanas(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda))->get(CampanaResource::getUrl('index'))->assertOk();
        $this->actingAs($this->usuarioConRol(Rol::Produccion))->get(CampanaResource::getUrl('index'))->assertForbidden();
    }
}
