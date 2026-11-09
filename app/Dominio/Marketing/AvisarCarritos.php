<?php

namespace App\Dominio\Marketing;

use App\Models\Ajuste;
use App\Models\AvisoCarrito;
use App\Models\Carrito;
use App\Models\Cupon;
use App\Notifications\Marketing\CarritoAbandonado;
use Illuminate\Support\Facades\Notification;

/**
 * Carritos abandonados (v2, D189): la secuencia de Marketing → Carritos abandonados (por defecto 1 h, 24 h y
 * 72 h, el último con cupón si se elige uno) a quien tiene **cuenta, el correo verificado y el consentimiento
 * comercial**. Cuenta desde el último cambio del carrito: si lo vuelve a tocar, la secuencia empieza de nuevo;
 * si compra, el carrito desaparece y se para sola. Cada correo lleva el enlace para darse de baja.
 */
final class AvisarCarritos
{
    /** @return int avisos enviados */
    public function __invoke(): int
    {
        if (! (bool) Ajuste::obtener('carritos.avisos_activos')) {
            return 0;
        }
        $secuencia = array_values((array) Ajuste::obtener('carritos.secuencia'));
        if ($secuencia === []) {
            return 0;
        }
        $enviados = 0;
        $minimo = now()->subHours(max(1, (int) ($secuencia[0]['horas'] ?? 1)));
        Carrito::query()->whereNotNull('cliente_id')->where('updated_at', '<=', $minimo)->whereHas('lineas')
            ->whereHas('cliente', fn ($q) => $q->where('activo', true)->whereNotNull('marketing_consentido_en')
                ->whereHas('usuario', fn ($u) => $u->whereNotNull('email_verified_at')->where('activo', true)))
            ->with('cliente')->lazyById()->each(function (Carrito $carrito) use ($secuencia, &$enviados): void {
                // Si ha tocado el carrito después del último aviso, la secuencia vuelve a empezar.
                AvisoCarrito::where('carrito_id', $carrito->id)->where('enviado_en', '<', $carrito->updated_at)->delete();
                $paso = AvisoCarrito::where('carrito_id', $carrito->id)->count();
                if (! isset($secuencia[$paso]) || $carrito->updated_at->greaterThan(now()->subHours(max(1, (int) $secuencia[$paso]['horas'])))) {
                    return;
                }
                $cupon = filled($secuencia[$paso]['cupon'] ?? null) && Cupon::where('codigo', $secuencia[$paso]['cupon'])->where('activo', true)->exists()
                    ? (string) $secuencia[$paso]['cupon'] : null;
                AvisoCarrito::create(['carrito_id' => $carrito->id, 'cliente_id' => $carrito->cliente_id, 'paso' => $paso, 'cupon' => $cupon, 'enviado_en' => now()]);
                $cliente = $carrito->cliente;
                Notification::route('mail', $cliente->correo)->notify((new CarritoAbandonado($carrito, $cliente, $cupon))->locale($cliente->idioma ?? 'es'));
                $enviados++;
            });

        return $enviados;
    }
}
