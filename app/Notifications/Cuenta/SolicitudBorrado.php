<?php

namespace App\Notifications\Cuenta;

use App\Models\Ajuste;
use App\Models\Cliente;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso a la tienda: un cliente pide borrar su cuenta (paso 5.3). En español, como el backend. */
class SolicitudBorrado extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public readonly Cliente $cliente)
    {
        $this->afterCommit();
        $this->locale('es');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('cuenta.correo_borrado_asunto', ['tienda' => Ajuste::obtener('tienda.nombre')]))
            ->line(__('cuenta.correo_borrado_texto', ['nombre' => $this->cliente->nombre, 'correo' => $this->cliente->correo]))
            ->action(__('cuenta.correo_borrado_boton'), url('/admin'))
            ->line(__('cuenta.correo_borrado_plazo'));
    }
}
