<?php

namespace App\Notifications\Marketing;

use App\Http\Controllers\CarritoController;
use App\Models\Ajuste;
use App\Models\Carrito;
use App\Models\Cliente;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** «Tu pedido te espera» (v2, D189): lo que dejó en el carrito, el enlace para volver, el cupón si lo hay y la baja. */
class CarritoAbandonado extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Carrito $carrito, public Cliente $cliente, public ?string $cupon = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->cliente->idioma ?? 'es';
        $tienda = Ajuste::obtener('tienda.nombre');
        $correo = (new MailMessage)
            ->subject(trans('marketing.carrito_asunto', ['tienda' => $tienda], $i))
            ->greeting(trans('marketing.carrito_hola', ['nombre' => $this->cliente->nombre], $i))
            ->line(trans('marketing.carrito_texto', [], $i));
        foreach ($this->carrito->lineas()->with('prenda', 'pack', 'color')->get() as $linea) {
            $nombre = $linea->pack?->getTranslation('nombre', $i) ?? $linea->prenda?->getTranslation('nombre', $i) ?? '';
            $correo->line('· '.$nombre.($linea->color ? ' · '.$linea->color->getTranslation('nombre', $i) : ''));
        }
        if ($this->cupon !== null) {
            $correo->line(trans('marketing.carrito_cupon', ['cupon' => $this->cupon], $i));
        }

        return $correo->action(trans('marketing.carrito_boton', [], $i), CarritoController::url('ver', idioma: $i))
            ->line(trans('marketing.baja_texto', ['enlace' => URL::signedRoute('marketing.baja', ['cliente' => $this->cliente->uuid])], $i))
            ->salutation(trans('cuenta.correo_despedida', ['tienda' => $tienda], $i));
    }
}
