<?php

namespace App\Notifications\Cuenta;

use App\Models\Ajuste;
use App\Support\RutaCuenta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Enlace para poner una contraseña nueva (paso 5.2), en el idioma del cliente. */
class RestablecerContrasena extends Notification implements ShouldQueue
{
    use Queueable;

    /** Por la cola, tras confirmar la transacción y con reintentos: si el correo falla,
     *  el formulario no da error ni delata si la cuenta existe (regla 6; revisión N3, A3). */
    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public readonly string $token)
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
        $minutos = (int) config('auth.passwords.users.expire', 60);
        $url = RutaCuenta::url('restablecer', ['token' => $this->token, 'correo' => $notifiable->getEmailForPasswordReset()], $idioma);

        return (new MailMessage)
            ->subject(__('cuenta.correo_restablecer_asunto', ['tienda' => Ajuste::obtener('tienda.nombre')]))
            ->greeting(__('cuenta.correo_hola', ['nombre' => $notifiable->name]))
            ->line(__('cuenta.correo_restablecer_texto'))
            ->action(__('cuenta.correo_restablecer_boton'), $url)
            ->line(__('cuenta.correo_caduca', ['minutos' => $minutos]))
            ->line(__('cuenta.correo_no_fuiste'))
            ->salutation(__('cuenta.correo_despedida', ['tienda' => Ajuste::obtener('tienda.nombre')]));
    }
}
