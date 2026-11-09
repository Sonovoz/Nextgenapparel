<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Pedidos\CambiarEstado;
use App\Dominio\Pedidos\CambioNoValido;
use App\Dominio\Pedidos\EstadoPedido as E;
use App\Dominio\Pedidos\PlazoEntrega;
use App\Models\Ajuste;
use App\Models\Cupon;
use App\Models\Pedido;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Estados, cancelación, caducidad y plazo de entrega del pedido (paso 6.3). */
class EstadosPedidoTest extends TestCase
{
    use RefreshDatabase;

    private function cambiar(Pedido $pedido, E $nuevo, array $envio = []): Pedido
    {
        return app(CambiarEstado::class)($pedido, $nuevo, User::factory()->create(), 'Nota', $envio);
    }

    #[DataProvider('cambios')]
    public function test_solo_los_cambios_de_la_ficha(E $desde, E $hasta, bool $vale): void
    {
        $pedido = Pedido::factory()->create(['estado' => $desde]);
        if (! $vale) {
            $this->expectException(CambioNoValido::class);
        }

        $this->assertSame($hasta, $this->cambiar($pedido, $hasta, ['transportista' => 'SEUR', 'numero_seguimiento' => 'X1'])->estado);
        $cambio = $pedido->historial()->first(); // al pagar se anota también la factura (6.5a)
        $this->assertSame([$hasta, 'Nota'], [$cambio->estado, $cambio->nota]);
        $this->assertNotNull($cambio->user_id);
    }

    public static function cambios(): array
    {
        return [
            'pendiente → pagado' => [E::PendientePago, E::Pagado, true],
            'pagado → revisando el diseño' => [E::Pagado, E::DisenoEnRevision, true],
            'revisando → necesitamos tu ayuda' => [E::DisenoEnRevision, E::RequiereCambios, true],
            'revisando → en producción' => [E::DisenoEnRevision, E::EnProduccion, true],
            'en producción → enviado' => [E::EnProduccion, E::Enviado, true],
            'enviado → entregado' => [E::Enviado, E::Entregado, true],
            'pagado → cancelado' => [E::Pagado, E::Cancelado, true],
            'en producción ya no se cancela' => [E::EnProduccion, E::Cancelado, false],
            'no se salta la producción' => [E::Pagado, E::Enviado, false],
            'un cancelado no vuelve' => [E::Cancelado, E::Pagado, false],
            'sin pagar no se produce' => [E::PendientePago, E::EnProduccion, false],
        ];
    }

    public function test_para_enviar_hacen_falta_transportista_y_seguimiento(): void
    {
        $pedido = Pedido::factory()->create(['estado' => E::EnProduccion]);

        try {
            $this->cambiar($pedido, E::Enviado, ['transportista' => 'SEUR']);
            $this->fail('Se ha enviado sin número de seguimiento.');
        } catch (CambioNoValido) {
        }
        $this->assertSame(E::EnProduccion, $pedido->fresh()->estado);

        $this->cambiar($pedido, E::Enviado, ['transportista' => 'SEUR', 'numero_seguimiento' => ' 123ABC ']);
        $this->assertSame(['SEUR', '123ABC'], [$pedido->fresh()->transportista, $pedido->fresh()->numero_seguimiento]);
    }

    public function test_no_se_da_por_pagado_sin_el_correo_confirmado(): void
    {
        $this->expectException(CambioNoValido::class);
        $this->cambiar(Pedido::factory()->create(['correo_confirmado_en' => null]), E::Pagado);
    }

    /** D173: al cancelar se devuelve el uso del cupón, una sola vez. */
    public function test_cancelar_devuelve_el_uso_del_cupon(): void
    {
        Cupon::create(['codigo' => 'BIENVENIDA', 'tipo' => 'importe', 'valor' => 1000, 'usos_maximos' => 1, 'usos' => 1]);
        $pedido = Pedido::factory()->create(['cupon' => 'BIENVENIDA']);

        $this->cambiar($pedido, E::Cancelado);

        $this->assertSame(0, Cupon::sole()->usos);
        $this->expectException(CambioNoValido::class); // y no se puede volver a cancelar (ni a devolver otro uso)
        $this->cambiar($pedido, E::Cancelado);
    }

    /** B1: quien decidió con una lectura anterior (la caducidad) no pisa un pago hecho mientras tanto. */
    public function test_con_desde_solo_cambia_si_sigue_en_ese_estado(): void
    {
        $pedido = Pedido::factory()->create(['estado' => E::Pagado]);

        $this->expectException(CambioNoValido::class);
        try {
            app(CambiarEstado::class)($pedido, E::Cancelado, desde: E::PendientePago);
        } finally {
            $this->assertSame(E::Pagado, $pedido->fresh()->estado);
        }
    }

    public function test_los_pedidos_sin_pagar_caducan_a_los_dias_de_ajustes(): void
    {
        Ajuste::guardar('pedidos.dias_sin_pago', 5);
        Cupon::create(['codigo' => 'BIENVENIDA', 'tipo' => 'importe', 'valor' => 1000, 'usos' => 1]);
        $viejo = Pedido::factory()->create(['created_at' => now()->subDays(6), 'cupon' => 'BIENVENIDA']);
        $reciente = Pedido::factory()->create(['created_at' => now()->subDays(4)]);
        $pagado = Pedido::factory()->create(['created_at' => now()->subDays(30), 'estado' => E::Pagado]);

        $this->artisan('pedidos:caducar')->assertSuccessful();

        $this->assertSame([E::Cancelado, E::PendientePago, E::Pagado], [$viejo->fresh()->estado, $reciente->fresh()->estado, $pagado->fresh()->estado]);
        $this->assertSame(0, Cupon::sole()->usos);
        $this->assertNull($viejo->historial()->sole()->user_id); // lo hizo el sistema
    }

    /** Ficha § 8: familia más lenta + días por unidades + transportista, solo laborables. */
    #[DataProvider('plazos')]
    public function test_fecha_estimada_en_dias_laborables(array $familias, int $unidades, int $transportista, string $esperada): void
    {
        Ajuste::guardar('pedidos.plazo_tramos', [['desde' => 50, 'dias' => 2], ['desde' => 200, 'dias' => 5]]);
        $lunes = CarbonImmutable::parse('2026-11-09 18:00');

        $this->assertSame($esperada, PlazoEntrega::fecha($familias, $unidades, $transportista, $lunes)->toDateString());
    }

    public static function plazos(): array
    {
        return [
            '2 + 0 + 2 = jueves' => [[2], 15, 2, '2026-11-13'],
            'la familia más lenta manda: 5 + 0 + 2 = miércoles siguiente' => [[2, 5], 15, 2, '2026-11-18'],
            '50 unidades: +2 días' => [[2], 50, 2, '2026-11-17'],
            '250 unidades: el tramo mayor (+5)' => [[2], 250, 2, '2026-11-20'],
            'sin días de ningún tipo: el mismo día' => [[], 1, 0, '2026-11-09'],
        ];
    }
}
