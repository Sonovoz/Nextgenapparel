<?php

namespace App\Notifications\Sistema;

use App\Models\Ajuste;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** «Enviar correo de prueba» de Ajustes → Correos: se manda al momento (sin cola) para ver si el SMTP funciona. */
class CorreoDePrueba extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tienda = Ajuste::obtener('tienda.nombre');

        return (new MailMessage)->subject(__('correos.prueba_asunto', ['tienda' => $tienda]))->line(__('correos.prueba_texto'));
    }
}
