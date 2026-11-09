<?php

namespace Tests\Feature\Pedidos;

use App\Dominio\Pedidos\CambiarEstado;
use App\Dominio\Pedidos\EstadoPedido as E;
use App\Dominio\Sistema\Acceso\Rol;
use App\Dominio\Sistema\Correo\Smtp;
use App\Filament\Pages\AjustesCorreos;
use App\Filament\Resources\Pedidos\Pages\ViewPedido;
use App\Models\Ajuste;
use App\Models\Pedido;
use App\Notifications\Pedidos\CorreoDelPedido;
use App\Notifications\Sistema\CorreoDePrueba;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Correos del pedido y Ajustes → Correos (paso 6.4). */
class CorreosPedidoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Filament::setCurrentPanel('admin');
    }

    /** @return array{0: string, 1: string, 2: string} asunto, texto y enlace del correo enviado */
    private function correo(): array
    {
        $enviado = null;
        Notification::assertSentOnDemand(CorreoDelPedido::class, function (CorreoDelPedido $n, $c, AnonymousNotifiable $a) use (&$enviado) {
            $enviado = [$n->toMail($a), $a->routes['mail']];

            return true;
        });
        [$mail, $para] = $enviado;

        return [$mail->subject, implode("\n", $mail->introLines), $mail->actionUrl, $para];
    }

    public function test_el_cambio_de_estado_avisa_en_el_idioma_del_cliente_con_su_enlace(): void
    {
        $pedido = Pedido::factory()->create(['estado' => E::EnProduccion, 'idioma' => 'en', 'numero' => 'PED-0007', 'correo' => 'lucy@example.com']);

        app(CambiarEstado::class)($pedido, E::Enviado, envio: ['transportista' => 'SEUR', 'numero_seguimiento' => 'ABC123']);

        [$asunto, $texto, $enlace, $para] = $this->correo();
        $this->assertSame('lucy@example.com', $para);
        $this->assertStringStartsWith('Your order PED-0007 is on its way', $asunto);
        $this->assertStringContainsString('out for delivery with SEUR', $texto);
        $this->assertStringContainsString('Tracking number: ABC123', $texto);
        $this->assertTrue($pedido->historial()->sole()->notificado_cliente);

        // El enlace del correo abre el pedido en otro navegador, sin cuenta.
        $this->get($enlace)->assertRedirect('/en/orders/'.$pedido->uuid);
        $this->get('/en/orders/'.$pedido->uuid)->assertOk()->assertSee('Order PED-0007');
        $this->flushSession();
        $this->get($enlace.'x')->assertOk()->assertSee('This link is no longer valid');
    }

    public function test_sin_correo_activo_o_sin_confirmar_no_se_avisa(): void
    {
        Ajuste::guardar('correo.estados_activos', ['enviado']);
        $pagado = Pedido::factory()->create(['estado' => E::Pagado]);
        app(CambiarEstado::class)($pagado, E::DisenoEnRevision);
        $sinConfirmar = Pedido::factory()->create(['correo_confirmado_en' => null]);
        app(CambiarEstado::class)($sinConfirmar, E::Cancelado);

        Notification::assertNothingSent();
        $this->assertFalse($pagado->historial()->sole()->notificado_cliente);
    }

    public function test_la_nota_para_el_cliente_le_llega_por_correo_y_la_privada_no(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda));
        $pedido = Pedido::factory()->create(['estado' => E::Pagado]);
        $ficha = Livewire::test(ViewPedido::class, ['record' => $pedido->getRouteKey()]);

        $ficha->callAction('nota', data: ['tipo' => 'privada', 'texto' => 'Solo para el equipo']);
        Notification::assertNothingSent();
        $ficha->callAction('nota', data: ['tipo' => 'cliente', 'texto' => 'Sale el lunes'])->assertNotified('Nota añadida');

        [$asunto, $texto] = $this->correo();
        $this->assertStringStartsWith('Novedades de tu pedido', $asunto);
        $this->assertStringContainsString('«Sale el lunes»', $texto);
    }

    public function test_pedir_otro_enlace_de_un_pedido_confirmado_manda_el_enlace_privado(): void
    {
        $pedido = Pedido::factory()->create();

        $this->post('/es/pedidos/'.$pedido->uuid.'/reenviar-enlace')->assertRedirect();

        [$asunto, , $enlace] = $this->correo();
        $this->assertStringStartsWith('Tu enlace al pedido', $asunto);
        $this->assertStringContainsString('/es/pedidos/'.$pedido->uuid.'/acceso', $enlace);
    }

    public function test_ajustes_correos_guarda_solo_lo_propio_y_cambia_lo_que_ve_el_cliente(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::Administrador));

        Livewire::test(AjustesCorreos::class)
            ->fillForm([
                'estados.diseno_en_revision.activo' => true,
                'estados.enviado.asunto.es' => 'Ya va: pedido :numero',
                'estados.pagado.nombre.es' => 'Pedido confirmado',
                'smtp.servidor' => 'ssl0.ovh.net', 'smtp.puerto' => 465, 'smtp.cifrado' => 'ssl', 'smtp.usuario' => 'hola@nextgenapparel.es',
                'smtp.contrasena' => 'secreta-123', 'smtp.remitente' => 'hola@nextgenapparel.es', 'smtp.nombre_remitente' => 'Nextgenapparel',
            ])
            ->callAction('guardar')->assertNotified('Ajustes guardados')
            ->assertSchemaStateSet(['smtp.contrasena' => null]); // la contraseña no se vuelve a mostrar

        $this->assertSame(['pagado', 'diseno_en_revision', 'requiere_cambios', 'enviado', 'cancelado'], Ajuste::obtener('correo.estados_activos'));
        $this->assertSame(['enviado' => ['asunto' => ['es' => 'Ya va: pedido :numero']]], Ajuste::obtener('correo.plantillas'));
        $this->assertSame('Pedido confirmado', E::Pagado->visible('es'));
        $this->assertSame('Order received', E::Pagado->visible('en'));
        // SMTP: la contraseña, cifrada; y lo del panel manda al crear el correo.
        $this->assertSame('secreta-123', Crypt::decryptString(Ajuste::obtener('correo.smtp')['contrasena']));
        Smtp::aplicar();
        $this->assertSame(['ssl0.ovh.net', 465, 'smtps', 'secreta-123'], [config('mail.mailers.smtp.host'), config('mail.mailers.smtp.port'), config('mail.mailers.smtp.scheme'), config('mail.mailers.smtp.password')]);

        // Dejar la contraseña vacía no la borra.
        Livewire::test(AjustesCorreos::class)->callAction('guardar');
        $this->assertSame('secreta-123', Smtp::contrasena());
    }

    public function test_el_correo_de_prueba_y_el_permiso(): void
    {
        $this->actingAs($this->usuarioConRol(Rol::GestorTienda))->get(AjustesCorreos::getUrl())->assertForbidden();

        $this->actingAs($this->usuarioConRol(Rol::Administrador));
        Livewire::test(AjustesCorreos::class)->callAction('prueba', data: ['para' => 'equipo@nextgenapparel.es'])->assertNotified('Correo de prueba enviado a equipo@nextgenapparel.es');
        Notification::assertSentOnDemand(CorreoDePrueba::class);
    }
}
