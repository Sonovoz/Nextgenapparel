<?php

namespace App\Notifications\Marketing;

use App\Models\Ajuste;
use App\Models\Cliente;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** Correo comercial (v2, D190): nueva compra, reactivación o campaña, en el idioma del cliente y con la baja. */
class CorreoComercial extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Cliente $cliente, public string $asunto, public string $texto, public string $boton, public string $enlace, public ?string $cupon = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->cliente->idioma ?? 'es';
        $tienda = Ajuste::obtener('tienda.nombre');
        $correo = (new MailMessage)->subject($this->asunto.' · '.$tienda)->greeting(trans('marketing.carrito_hola', ['nombre' => $this->cliente->nombre], $i));
        foreach (preg_split('/\n\s*\n/', trim($this->texto)) ?: [] as $parrafo) {
            $correo->line(trim($parrafo));
        }
        if ($this->cupon !== null) {
            $correo->line(trans('marketing.cupon', ['cupon' => $this->cupon], $i));
        }

        return $correo->action($this->boton, $this->enlace)
            ->line(trans('marketing.baja_texto', ['enlace' => URL::signedRoute('marketing.baja', ['cliente' => $this->cliente->uuid])], $i))
            ->salutation(trans('cuenta.correo_despedida', ['tienda' => $tienda], $i));
    }
}
