<?php

namespace App\Dominio\Disenos;

use App\Models\Cliente;
use App\Models\Diseno;
use Illuminate\Support\Facades\DB;

/**
 * Al entrar en su cuenta, los diseños que el visitante subió en esta sesión pasan
 * a ser del cliente (fase 5). Si el cliente ya tenía el mismo archivo, se queda el
 * suyo y el de la sesión se borra con sus archivos.
 */
final class TraspasarDisenos
{
    /** @return int diseños traspasados */
    public function __invoke(string $propietario, Cliente $cliente): int
    {
        return DB::transaction(function () use ($propietario, $cliente): int {
            $traspasados = 0;
            $suyos = $cliente->disenos()->pluck('sha256')->flip();

            foreach (Diseno::whereNull('cliente_id')->where('propietario', $propietario)->lockForUpdate()->get() as $diseno) {
                if ($suyos->has($diseno->sha256)) {
                    $diseno->delete();

                    continue;
                }
                $diseno->update(['cliente_id' => $cliente->id, 'propietario' => null]);
                $traspasados++;
            }

            return $traspasados;
        });
    }
}
