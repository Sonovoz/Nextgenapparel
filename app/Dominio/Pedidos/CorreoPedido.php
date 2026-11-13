<?php

namespace App\Dominio\Pedidos;

use App\Dominio\Sistema\Correo\RegistrarCorreos;
use App\Models\Pedido;
use App\Notifications\Pedidos\CorreoDelPedido;
use Illuminate\Support\Facades\Notification;

/** Envía un correo del pedido a su dirección, en su idioma (paso 6.4). Solo con el correo confirmado. */
final class CorreoPedido
{
    public static function enviar(Pedido $pedido, CorreoDelPedido $correo): bool
    {
        if (! $pedido->correoConfirmado()) {
            // D235: queda en el registro de correos por qué no se ha mandado.
            RegistrarCorreos::noEnviado($correo, $pedido->correo, 'El cliente aún no ha confirmado su correo: hasta entonces solo se le envía el enlace de confirmación.');

            return false;
        }
        Notification::route('mail', $pedido->correo)->notify($correo->locale($pedido->idioma));

        return true;
    }
}
