<?php

namespace App\Dominio\Pedidos;

use App\Models\Pedido;
use App\Notifications\Pedidos\CorreoDelPedido;
use Illuminate\Support\Facades\Notification;

/** Envía un correo del pedido a su dirección, en su idioma (paso 6.4). Solo con el correo confirmado. */
final class CorreoPedido
{
    public static function enviar(Pedido $pedido, CorreoDelPedido $correo): bool
    {
        if (! $pedido->correoConfirmado()) {
            return false;
        }
        Notification::route('mail', $pedido->correo)->notify($correo->locale($pedido->idioma));

        return true;
    }
}
