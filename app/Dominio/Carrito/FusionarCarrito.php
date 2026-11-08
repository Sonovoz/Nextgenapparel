<?php

namespace App\Dominio\Carrito;

use App\Dominio\Disenos\TraspasarDisenos;
use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\ImpresionCarrito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Al entrar en su cuenta (o registrarse), el carrito que llevaba sin cuenta se suma
 * al suyo (decisión del cliente, paso 5.5): sus líneas pasan detrás de las que ya
 * tenía y, si él no tenía cupón, se queda el del invitado. Los diseños de esas líneas
 * pasan a ser suyos, para que la limpieza de diseños sin cuenta no los borre.
 */
final class FusionarCarrito
{
    public const TOPE = 2 * EditarCarrito::MAXIMO_LINEAS;

    public function __construct(private readonly TraspasarDisenos $traspasar) {}

    public function __invoke(Request $request, Cliente $cliente): void
    {
        $invitado = CarritoActual::delInvitado($request);
        if ($invitado === null) {
            return;
        }

        DB::transaction(function () use ($invitado, $cliente): void {
            $invitado = Carrito::whereKey($invitado->id)->whereNull('cliente_id')->lockForUpdate()->first();
            if ($invitado === null) {
                return; // otra petición ya lo sumó
            }
            $suyo = Carrito::firstOrCreate(['cliente_id' => $cliente->id]);
            $suyo = Carrito::whereKey($suyo->id)->lockForUpdate()->first();

            $disenos = ImpresionCarrito::whereIn('linea_carrito_id', $invitado->lineas()->select('id'))->whereNotNull('diseno_id')->pluck('diseno_id')->unique()->values()->all();
            $this->traspasar->estos($disenos, $cliente);

            // Sus líneas primero y las del invitado detrás, renumeradas. Como freno al abuso
            // (entrar y salir sumando carritos), no pasa del doble del máximo: lo que sobra se
            // queda en el carrito del invitado, que se borra (revisión N3, B2).
            $orden = 0;
            foreach ($suyo->lineas()->get() as $linea) {
                $linea->update(['orden' => ++$orden]);
            }
            foreach ($invitado->lineas()->get() as $linea) {
                if ($orden >= self::TOPE) {
                    break;
                }
                $linea->update(['carrito_id' => $suyo->id, 'orden' => ++$orden]);
            }
            $suyo->update(['cupon' => $suyo->cupon ?? $invitado->cupon]);
            $suyo->touch();
            $invitado->delete();
        });

        CarritoActual::olvidarInvitado();
    }
}
