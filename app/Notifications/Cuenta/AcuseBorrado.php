<?php

namespace App\Notifications\Cuenta;

use App\Models\Ajuste;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Acuse al cliente: su solicitud de borrado ha llegado (revisión N3 del paso 5.3, B3). */
class AcuseBorrado extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600];

    public function __construct()
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
            ->subject(__('cuenta.acuse_borrado_asunto', ['tienda' => Ajuste::obtener('tienda.nombre')]))
            ->greeting(__('cuenta.correo_hola', ['nombre' => $notifiable->name]))
            ->line(__('cuenta.acuse_borrado_texto'))
            ->salutation(__('cuenta.correo_despedida', ['tienda' => Ajuste::obtener('tienda.nombre')]));
    }
}
