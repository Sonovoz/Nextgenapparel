<?php

namespace App\Dominio\Pedidos;

use RuntimeException;

/** El pedido no se crea y nada queda a medias. El motivo se explica al cliente (lang/{es,en}/pedidos.php). */
final class PedidoNoCreado extends RuntimeException
{
    /** @param  'vacio'|'no_confirmable'|'total_cambiado'  $motivo */
    public function __construct(public readonly string $motivo)
    {
        parent::__construct("Pedido no creado: {$motivo}");
    }
}
