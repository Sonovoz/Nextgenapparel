<?php

namespace App\Dominio\Pagos;

use App\Models\Devolucion;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Support\Facades\DB;

/**
 * Devuelve dinero del cobro de un pedido por la pasarela (paso 7.2, D182), portado de Clave de Alma:
 * nunca más de lo cobrado menos lo ya devuelto, y la misma clave nunca devuelve dos veces.
 */
final class DevolverDinero
{
    public function __construct(private readonly Pasarela $pasarela) {}

    public function __invoke(Pedido $pedido, int $importe, string $clave, string $motivo): ResultadoDevolucion
    {
        $hecha = Devolucion::where('clave', $clave)->where('estado', 'hecha')->first();
        if ($hecha !== null) {
            return new ResultadoDevolucion(true, $hecha->referencia);
        }
        if (Devolucion::where('clave', $clave)->where('estado', 'dudosa')->exists()) {
            return new ResultadoDevolucion(false, null, 'Hay una devolución sin confirmar: compruébala en el TPV antes de repetirla.', dudosa: true);
        }
        if ($importe < 1) {
            return new ResultadoDevolucion(false, null, 'No hay importe que devolver.');
        }

        return DB::transaction(function () use ($pedido, $importe, $clave, $motivo): ResultadoDevolucion {
            Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail(); // el pedido primero, como el resto del dinero
            $pago = Pago::where('pedido_id', $pedido->id)->where('estado', 'cobrado')->lockForUpdate()->latest('id')->first();
            if ($pago === null) {
                return new ResultadoDevolucion(false, null, 'Este pedido no tiene un cobro por la pasarela que devolver.');
            }
            if ($pago->pasarela !== $this->pasarela->nombre()) {
                return new ResultadoDevolucion(false, null, "El cobro se hizo con «{$pago->pasarela}» y la pasarela activa es «{$this->pasarela->nombre()}».");
            }
            $disponible = $pago->importe - self::devuelto($pago);
            if ($importe > $disponible) {
                return new ResultadoDevolucion(false, null, 'No se puede devolver más de lo cobrado (quedan '.number_format($disponible / 100, 2, ',', '.').' €).');
            }

            // Tras un intento fallido, la pasarela repetiría el error con la misma clave.
            $intentos = (int) Devolucion::where('clave', $clave)->value('intentos');
            $resultado = $this->pasarela->devolver($pago->referencia, $importe, $intentos === 0 ? $clave : $clave.':'.$intentos);
            Devolucion::updateOrCreate(['clave' => $clave], [
                'pago_id' => $pago->id, 'pedido_id' => $pedido->id, 'importe' => $importe, 'referencia' => $resultado->referencia,
                'estado' => $resultado->hecha ? 'hecha' : ($resultado->dudosa ? 'dudosa' : 'fallida'), 'motivo' => mb_substr($motivo, 0, 255),
                'error' => $resultado->error === null ? null : mb_substr($resultado->error, 0, 500), 'intentos' => $resultado->hecha ? $intentos : $intentos + 1,
            ]);

            return $resultado;
        });
    }

    /** Lo que queda por devolver del pedido: lo cobrado (en el último cobro) menos lo devuelto. */
    public static function pendiente(Pedido $pedido): int
    {
        $pago = Pago::where('pedido_id', $pedido->id)->where('estado', 'cobrado')->latest('id')->first();

        return $pago === null ? 0 : max(0, $pago->importe - self::devuelto($pago));
    }

    private static function devuelto(Pago $pago): int
    {
        // Las dudosas cuentan como hechas: nunca se devuelve de más por no saberlo.
        return (int) Devolucion::where('pago_id', $pago->id)->whereIn('estado', ['hecha', 'dudosa'])->sum('importe');
    }
}
