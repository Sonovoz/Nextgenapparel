<?php

namespace App\Notifications\Pedidos;

use App\Filament\Resources\Incidencias\IncidenciaResource;
use App\Models\Incidencia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso al equipo (correo de contacto de Ajustes → General): un cliente abre una incidencia (D178). */
class IncidenciaAbierta extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Incidencia $incidencia) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->incidencia;

        return (new MailMessage)
            ->subject("Incidencia {$i->codigo} · pedido {$i->pedido->numero}")
            ->line("El cliente del pedido {$i->pedido->numero} abre la incidencia {$i->codigo}: {$i->motivo->etiqueta('es')}.")
            ->line('Fotos: '.$i->fotos()->count().'.')
            ->action('Ver la incidencia', IncidenciaResource::getUrl('view', ['record' => $i], panel: 'admin'));
    }
}
