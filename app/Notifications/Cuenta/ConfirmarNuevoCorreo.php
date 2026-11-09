<?php

namespace App\Notifications\Cuenta;

use App\Models\Ajuste;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** Enlace al correo nuevo para confirmar el cambio (revisión N3 del paso 5.3, A1). */
class ConfirmarNuevoCorreo extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public readonly User $usuario, public readonly string $correo)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $idioma = $this->locale ?? app()->getLocale();
        $minutos = (int) config('auth.verification.expire', 60);
        $url = URL::temporarySignedRoute("cuenta.{$idioma}.confirmar-correo", now()->addMinutes($minutos), ['id' => $this->usuario->getKey(), 'hash' => sha1($this->correo)]);

        return (new MailMessage)
            ->subject(__('cuenta.correo_cambio_asunto', ['tienda' => Ajuste::obtener('tienda.nombre')]))
            ->greeting(__('cuenta.correo_hola', ['nombre' => $this->usuario->name]))
            ->line(__('cuenta.correo_cambio_texto'))
            ->action(__('cuenta.correo_verificar_boton'), $url)
            ->line(__('cuenta.correo_caduca', ['minutos' => $minutos]))
            ->line(__('cuenta.correo_verificar_no_fuiste'))
            ->salutation(__('cuenta.correo_despedida', ['tienda' => Ajuste::obtener('tienda.nombre')]));
    }
}
