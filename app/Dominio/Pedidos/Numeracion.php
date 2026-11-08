<?php

namespace App\Dominio\Pedidos;

use App\Models\Ajuste;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Números correlativos (PED-0001…): se suman bajo el bloqueo de su fila dentro de la
 * transacción del pedido, así dos pedidos a la vez nunca comparten número. Se pide al
 * final de la transacción, para tener el contador bloqueado lo menos posible (M2).
 */
final class Numeracion
{
    public static function siguiente(string $clave): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('La numeración solo se usa dentro de una transacción.');
        }
        // Un UPDATE directo coge el bloqueo exclusivo de la fila a la primera: con
        // «INSERT IGNORE + SELECT … FOR UPDATE» dos pedidos a la vez se interbloqueaban (M1).
        $filas = DB::table('contadores')->where('clave', $clave)->update(['valor' => DB::raw('valor + 1')]);
        if ($filas === 0) {
            DB::table('contadores')->insert(['clave' => $clave, 'valor' => 1]);

            return 1;
        }

        return (int) DB::table('contadores')->where('clave', $clave)->value('valor');
    }

    /** «PED-0042»: prefijo de Ajustes → General y al menos 4 cifras. */
    public static function numeroPedido(int $n): string
    {
        return Ajuste::obtener('pedidos.prefijo').str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
