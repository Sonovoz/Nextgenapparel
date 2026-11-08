<?php

namespace App\Notifications\Cuenta;

use App\Models\Ajuste;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Enlace para verificar el correo (paso 5.2), en el idioma del cliente. Caduca a los
 * minutos de config('auth.verification.expire'). Los textos están en lang/{es,en}/cuenta.php
 * hasta las plantillas editables de la fase 6 (D153).
 */
class VerificarCorreo extends Notification implements ShouldQueue
{
    use Queueable;

    /** Por la cola, tras confirmar la transacción y con reintentos: si el correo falla,
     *  el formulario no da error ni delata si la cuenta existe (regla 6; revisión N3, A3). */
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
        $idioma = $this->locale ?? app()->getLocale();
        $minutos = (int) config('auth.verification.expire', 60);
        $url = URL::temporarySignedRoute("cuenta.{$idioma}.verificar", now()->addMinutes($minutos), [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ]);

        return (new MailMessage)
            ->subject(__('cuenta.correo_verificar_asunto', ['tienda' => Ajuste::obtener('tienda.nombre')]))
            ->greeting(__('cuenta.correo_hola', ['nombre' => $notifiable->name]))
            ->line(__('cuenta.correo_verificar_texto'))
            ->action(__('cuenta.correo_verificar_boton'), $url)
            ->line(__('cuenta.correo_caduca', ['minutos' => $minutos]))
            ->line(__('cuenta.correo_verificar_no_fuiste'))
            ->salutation(__('cuenta.correo_despedida', ['tienda' => Ajuste::obtener('tienda.nombre')]));
    }
}
