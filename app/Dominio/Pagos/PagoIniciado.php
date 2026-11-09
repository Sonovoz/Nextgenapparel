<?php

namespace App\Dominio\Pagos;

/** El navegador envía `campos` a `url` (la página de pago de la pasarela). */
final readonly class PagoIniciado
{
    /** @param  array<string, string>  $campos */
    public function __construct(public string $referencia, public string $url, public array $campos = []) {}
}
