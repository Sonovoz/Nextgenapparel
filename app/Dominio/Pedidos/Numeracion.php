<?php

namespace App\Dominio\Pedidos;

use App\Models\Ajuste;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Números correlativos (PED-0001…): se leen y se suman bajo bloqueo dentro de la
 * transacción del pedido, así dos pedidos a la vez nunca comparten número.
 */
final class Numeracion
{
    public static function siguiente(string $clave): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('La numeración solo se usa dentro de una transacción.');
        }
        DB::table('contadores')->insertOrIgnore(['clave' => $clave, 'valor' => 0]);
        $actual = (int) DB::table('contadores')->where('clave', $clave)->lockForUpdate()->value('valor');
        DB::table('contadores')->where('clave', $clave)->update(['valor' => $actual + 1]);

        return $actual + 1;
    }

    /** «PED-0042»: prefijo de Ajustes → General y al menos 4 cifras. */
    public static function numeroPedido(int $n): string
    {
        return Ajuste::obtener('pedidos.prefijo').str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
