<?php

namespace App\Dominio\Clientes;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Tramitar el borrado de un cliente desde el backend (paso 5.4, D156): borra su cuenta,
 * su ficha, sus direcciones y sus diseños (con sus archivos), y no deja datos personales
 * en ninguna parte (revisión N3 del 5.4, A2): los apuntes anteriores del registro de
 * actividad pierden sus datos (nombre, correo, NIF…) y se borran sus tokens de contraseña
 * y sus sesiones. Queda un apunte del borrado con la referencia interna.
 *
 * Un cliente con pedidos no se borra (revisión N3 del 6.1, A2): sus pedidos guardan una
 * copia de sus datos (nombre, NIF, dirección, correo, IP). Se anonimizará y se conservará
 * lo que obliga la ley con el criterio del asesor; hasta entonces, ClienteConPedidos.
 */
final class BorrarCliente
{
    public function __invoke(Cliente $cliente, User $quien): void
    {
        $referencia = ['cliente' => $cliente->uuid, 'solicitado_en' => $cliente->borrado_solicitado_en?->toDateTimeString()];
        if ($cliente->pedidos()->exists() || Pedido::where('correo', $cliente->correo)->exists()) {
            throw new ClienteConPedidos;
        }

        try {
            DB::transaction(function () use ($cliente): void {
                $usuario = $cliente->usuario;
                $esEmpleado = $usuario !== null && $usuario->roles()->exists();

                $this->vaciarRegistro($cliente, $esEmpleado ? null : $usuario);

                // Uno a uno, para que cada diseño borre también sus archivos (Diseno::booted).
                foreach ($cliente->disenos()->get() as $diseno) {
                    $diseno->delete();
                }
                $cliente->delete(); // direcciones en cascada

                // La cuenta solo si es de un cliente: un empleado que compraba conserva su usuario.
                if ($usuario !== null && ! $esEmpleado) {
                    DB::table('password_reset_tokens')->whereIn('email', array_filter([$usuario->email, $usuario->correo_pendiente]))->delete();
                    DB::table('sessions')->where('user_id', $usuario->id)->delete();
                    activity()->withoutLogs(fn () => $usuario->delete());
                }
            });
        } finally {
            // El apunte del borrado se escribe aunque falle algo después de confirmar (B3).
            if (! Cliente::whereKey($cliente->id)->exists()) {
                Actividad::apuntar('clientes', 'Cliente borrado', propiedades: $referencia, quien: $quien);
            }
        }
    }

    /** Los apuntes del cliente y de su cuenta se quedan (lo que pasó), sin los datos personales. */
    private function vaciarRegistro(Cliente $cliente, ?User $usuario): void
    {
        Activity::query()
            ->where(fn ($q) => $q->where('subject_type', $cliente->getMorphClass())->where('subject_id', $cliente->id))
            ->when($usuario !== null, fn ($consulta) => $consulta
                ->orWhere(fn ($q) => $q->where('subject_type', $usuario->getMorphClass())->where('subject_id', $usuario->id))
                ->orWhere(fn ($q) => $q->where('causer_type', $usuario->getMorphClass())->where('causer_id', $usuario->id)))
            ->update(['properties' => json_encode(['datos' => 'borrados con el cliente'])]);
    }
}
