<?php

namespace Tests\Feature\Sistema;

use App\Dominio\Pedidos\CorreoPedido;
use App\Dominio\Pedidos\EstadoPedido as E;
use App\Dominio\Sistema\Acceso\Rol;
use App\Dominio\Sistema\Salud\Comprobaciones\CorreosPendientes;
use App\Dominio\Sistema\Salud\EstadoSalud;
use App\Filament\Resources\RegistroCorreos\RegistroCorreoResource;
use App\Models\Pedido;
use App\Models\RegistroCorreo;
use App\Notifications\Pedidos\CorreoDelPedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D235: cada correo queda registrado (en cola, enviado, fallido o no enviado) para encontrar por qué no llega. */
class RegistroCorreosTest extends TestCase
{
    use RefreshDatabase;

    private function pedido(bool $confirmado = true): Pedido
    {
        return Pedido::factory()->create(['estado' => E::Pagado, 'correo' => 'Ana@Ejemplo.es', 'correo_confirmado_en' => $confirmado ? now() : null]);
    }

    public function test_un_correo_de_pedido_queda_en_cola_y_luego_enviado_con_su_asunto(): void
    {
        config()->set('queue.default', 'database');
        $pedido = $this->pedido();

        CorreoPedido::enviar($pedido, new CorreoDelPedido($pedido, 'estado', E::Pagado));

        $registro = RegistroCorreo::sole();
        $this->assertSame(['en_cola', 'ana@ejemplo.es', 'CorreoDelPedido', $pedido->id], [$registro->estado, $registro->destinatario, $registro->tipo, $registro->pedido_id]);

        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        $registro->refresh();
        $this->assertSame('enviado', $registro->estado);
        $this->assertNotNull($registro->enviado_en);
        $this->assertNotEmpty($registro->asunto);
    }

    public function test_si_el_smtp_falla_queda_el_error_y_los_reintentos(): void
    {
        config()->set('queue.default', 'database');
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp', ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]);
        $pedido = $this->pedido();

        CorreoPedido::enviar($pedido, new CorreoDelPedido($pedido, 'estado', E::Pagado));
        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        $registro = RegistroCorreo::sole();
        $this->assertSame(['reintentando', 1], [$registro->estado, $registro->intentos]);
        $this->assertNotEmpty($registro->error);
    }

    public function test_sin_el_correo_confirmado_queda_como_no_enviado_con_el_motivo(): void
    {
        $pedido = $this->pedido(confirmado: false);

        $this->assertFalse(CorreoPedido::enviar($pedido, new CorreoDelPedido($pedido, 'estado', E::Pagado)));

        $registro = RegistroCorreo::sole();
        $this->assertSame('no_enviado', $registro->estado);
        $this->assertStringContainsString('no ha confirmado su correo', (string) $registro->error);
    }

    public function test_la_salud_avisa_de_correos_atascados_y_el_registro_se_ve_en_el_backend(): void
    {
        $this->assertSame(EstadoSalud::Correcto, (new CorreosPendientes)->comprobar()[0]);
        $atascado = RegistroCorreo::create(['tipo' => 'CorreoDelPedido', 'destinatario' => 'ana@ejemplo.es', 'estado' => 'en_cola']);
        $atascado->forceFill(['updated_at' => now()->subMinutes(20)])->saveQuietly();

        [$estado, $mensaje] = (new CorreosPendientes)->comprobar();
        $this->assertSame(EstadoSalud::Fallo, $estado);
        $this->assertStringContainsString('cron del servidor', $mensaje);

        $this->actingAs($this->usuarioConRol(Rol::Superadministrador))->get(RegistroCorreoResource::getUrl('index'))->assertOk()->assertSee('ana@ejemplo.es');
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda))->get(RegistroCorreoResource::getUrl('index'))->assertForbidden();
    }
}
