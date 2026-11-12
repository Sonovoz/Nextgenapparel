<?php

namespace App\Dominio\Presupuestos;

/**
 * Huella de una línea (prenda o pack, color, tallas, elecciones y diseños): para saber si una línea de un
 * presupuesto ya está en el carrito y no ponerla dos veces. Las claves y los números se normalizan, porque
 * el presupuesto los guarda en JSON (las claves numéricas vuelven como texto).
 */
final class HuellaLinea
{
    /** @param array<string, mixed> $origen */
    public static function de(array $origen): string
    {
        return hash('sha256', (string) json_encode(self::normalizar($origen)));
    }

    private static function normalizar(mixed $valor): mixed
    {
        if (is_array($valor)) {
            $valor = array_map(self::normalizar(...), array_filter($valor, fn ($v) => $v !== null && $v !== []));
            ksort($valor);

            return $valor;
        }

        return is_string($valor) && is_numeric($valor) ? $valor + 0 : $valor;
    }
}
