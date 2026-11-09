<?php

namespace App\Notifications\Pedidos;

use App\Dominio\Facturacion\PdfFactura;
use App\Dominio\Incidencias\ResolucionIncidencia;
use App\Dominio\Precios\Formato;
use App\Http\Controllers\PedidoController;
use App\Models\Ajuste;
use App\Models\Incidencia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al cliente, en su idioma: cómo se ha resuelto su incidencia, con la rectificativa si hay reembolso (D178). */
class IncidenciaResuelta extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public Incidencia $incidencia)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->incidencia->fresh() ?? $this->incidencia;
        $pedido = $i->pedido;
        $idioma = $pedido->idioma;
        $tienda = Ajuste::obtener('tienda.nombre');
        $resolucion = $i->resolucion ?? ResolucionIncidencia::Rechazo;

        $correo = (new MailMessage)
            ->subject(trans('incidencias.correo_asunto', ['codigo' => $i->codigo, 'numero' => $pedido->numero], $idioma).' · '.$tienda)
            ->line(trans('incidencias.correo_'.$resolucion->value, ['codigo' => $i->codigo, 'importe' => Formato::euros((int) $i->importe_reembolso, $idioma)], $idioma))
            ->line('«'.trim((string) $i->respuesta).'»')
            ->action(trans('correos.ver_pedido', [], $idioma), PedidoController::enlacePrivado($pedido))
            ->salutation(trans('cuenta.correo_despedida', ['tienda' => $tienda], $idioma));
        if ($i->factura !== null) {
            $correo->attachData(PdfFactura::generar($i->factura), $i->factura->nombreArchivo(), ['mime' => 'application/pdf']);
        }

        return $correo;
    }
}
