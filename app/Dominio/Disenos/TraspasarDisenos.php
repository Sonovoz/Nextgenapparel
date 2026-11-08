<?php

namespace App\Dominio\Disenos;

use App\Models\Cliente;
use App\Models\Diseno;
use App\Models\ImpresionCarrito;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Al entrar en su cuenta, los diseños que el visitante subió en esta sesión pasan
 * a ser del cliente (fase 5), y también los que lleva en el carrito aunque los subiera
 * en otra visita (paso 5.5). Si el cliente ya tenía el mismo archivo, se queda el suyo,
 * el carrito pasa a usar el suyo y el de la sesión se borra con sus archivos.
 */
final class TraspasarDisenos
{
    /** @return int diseños traspasados */
    public function __invoke(string $propietario, Cliente $cliente): int
    {
        return $this->traspasar(Diseno::whereNull('cliente_id')->where('propietario', $propietario), $cliente);
    }

    /** @param  list<int>  $ids  diseños de un carrito de invitado; solo pasan los que no tienen dueño con cuenta */
    public function estos(array $ids, Cliente $cliente): int
    {
        return $ids === [] ? 0 : $this->traspasar(Diseno::whereNull('cliente_id')->whereIn('id', $ids), $cliente);
    }

    /** @param  Builder<Diseno>  $consulta */
    private function traspasar(Builder $consulta, Cliente $cliente): int
    {
        return DB::transaction(function () use ($consulta, $cliente): int {
            $traspasados = 0;
            $suyos = $cliente->disenos()->pluck('id', 'sha256');

            foreach ($consulta->lockForUpdate()->get() as $diseno) {
                if ($suyos->has($diseno->sha256)) {
                    $this->sustituir($diseno, $suyos[$diseno->sha256]);

                    continue;
                }
                try {
                    // Un punto de guardado por diseño: si choca, se deshace solo este.
                    DB::transaction(fn () => $diseno->update(['cliente_id' => $cliente->id, 'propietario' => null]));
                    $suyos[$diseno->sha256] = $diseno->id;
                    $traspasados++;
                } catch (UniqueConstraintViolationException) {
                    // El mismo archivo llegó a la cuenta mientras tanto (revisión N3, M6).
                    $this->sustituir($diseno->refresh(), (int) $cliente->disenos()->where('sha256', $diseno->sha256)->value('id'));
                }
            }

            return $traspasados;
        });
    }

    /** El carrito pasa a usar el diseño que ya tenía el cliente y se borra el repetido. */
    private function sustituir(Diseno $repetido, int $suyo): void
    {
        ImpresionCarrito::where('diseno_id', $repetido->id)->update(['diseno_id' => $suyo]);
        $repetido->delete();
    }
}
