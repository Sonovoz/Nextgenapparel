<?php

namespace App\Notifications\Cuenta;

use App\Support\RutaCuenta;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Enlace para poner una contraseña nueva (paso 5.2), en el idioma del cliente. */
class RestablecerContrasena extends Notification
{
    public function __construct(public readonly string $token) {}

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
            ->subject(__('cuenta.correo_restablecer_asunto'))
            ->greeting(__('cuenta.correo_hola', ['nombre' => $notifiable->name]))
            ->line(__('cuenta.correo_restablecer_texto'))
            ->action(__('cuenta.correo_restablecer_boton'), $url)
            ->line(__('cuenta.correo_caduca', ['minutos' => $minutos]))
            ->line(__('cuenta.correo_no_fuiste'));
    }
}
