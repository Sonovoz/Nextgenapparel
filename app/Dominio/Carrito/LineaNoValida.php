<?php

namespace App\Dominio\Carrito;

use RuntimeException;

/** La línea no se puede añadir o cambiar; `problemas` son claves de lang/{es,en}/carrito.php. */
final class LineaNoValida extends RuntimeException
{
    /** @param  list<string>  $problemas */
    public function __construct(public readonly array $problemas)
    {
        parent::__construct('Línea de carrito no válida: '.implode(', ', $problemas));
    }

    /** @return list<string> */
    public function textos(?string $idioma = null): array
    {
        return array_map(fn (string $p) => __('carrito.'.$p, ['maximo' => EditarCarrito::MAXIMO_LINEAS], $idioma), $this->problemas);
    }
}
