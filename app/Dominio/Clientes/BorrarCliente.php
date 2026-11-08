<?php

namespace App\Dominio\Clientes;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Tramitar el borrado de un cliente desde el backend (paso 5.4, D156): borra su cuenta,
 * su ficha, sus direcciones y sus diseños (con sus archivos). Queda en el registro de
 * actividad sin datos personales, solo la referencia interna.
 *
 * IMPORTANTE para la fase 6: un cliente con pedidos o facturas no se borra: se anonimiza
 * y se conserva lo que obliga la ley (con el criterio del asesor). Aquí habrá que comprobarlo.
 */
final class BorrarCliente
{
    public function __invoke(Cliente $cliente, User $quien): void
    {
        $referencia = ['cliente' => $cliente->uuid, 'solicitado_en' => $cliente->borrado_solicitado_en?->toDateTimeString()];

        DB::transaction(function () use ($cliente): void {
            // Uno a uno, para que cada diseño borre también sus archivos (Diseno::booted).
            foreach ($cliente->disenos()->get() as $diseno) {
                $diseno->delete();
            }
            $usuario = $cliente->usuario;
            $cliente->delete(); // direcciones en cascada
            // La cuenta solo si es de un cliente: un empleado que compraba conserva su usuario.
            if ($usuario !== null && ! $usuario->roles()->exists()) {
                activity()->withoutLogs(fn () => $usuario->delete());
            }
        });

        Actividad::apuntar('clientes', 'Cliente borrado', propiedades: $referencia, quien: $quien);
    }
}
