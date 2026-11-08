<?php

namespace App\Notifications\Cuenta;

use App\Models\Ajuste;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso al correo de siempre: alguien ha pedido cambiarlo (revisión N3 del paso 5.3, A1). */
class AvisoCambioCorreo extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public readonly string $nuevo)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('cuenta.correo_aviso_cambio_asunto', ['tienda' => Ajuste::obtener('tienda.nombre')]))
            ->greeting(__('cuenta.correo_hola', ['nombre' => $notifiable->name]))
            ->line(__('cuenta.correo_aviso_cambio_texto', ['nuevo' => $this->nuevo]))
            ->line(__('cuenta.correo_aviso_cambio_no_fuiste'))
            ->salutation(__('cuenta.correo_despedida', ['tienda' => Ajuste::obtener('tienda.nombre')]));
    }
}
