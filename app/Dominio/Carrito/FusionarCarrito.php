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
            $suyo = Carrito::createOrFirst(['cliente_id' => $cliente->id]);
            $suyo = Carrito::whereKey($suyo->id)->lockForUpdate()->first();

            $disenos = ImpresionCarrito::whereIn('linea_carrito_id', $invitado->lineas()->select('id'))->whereNotNull('diseno_id')->pluck('diseno_id')->unique()->values()->all();
            $this->traspasar->estos($disenos, $cliente);

            $orden = (int) $suyo->lineas()->max('orden');
            foreach ($invitado->lineas()->get() as $linea) {
                $linea->update(['carrito_id' => $suyo->id, 'orden' => ++$orden]);
            }
            $suyo->update(['cupon' => $suyo->cupon ?? $invitado->cupon]);
            $suyo->touch();
            $invitado->delete();
        });

        CarritoActual::olvidarInvitado();
    }
}
