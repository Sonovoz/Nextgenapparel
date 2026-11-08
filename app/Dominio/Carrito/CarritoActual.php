<?php

namespace App\Dominio\Carrito;

use App\Models\Ajuste;
use App\Models\Carrito;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * El carrito de quien hace la petición (paso 5.5): el del cliente si ha entrado en su
 * cuenta y, si no, el de la cookie del navegador, que dura lo que diga
 * Ajustes → General (30 días por defecto) desde la última vez que se usa. En la base de datos solo está el hash del
 * token, para que una copia de la tabla no permita abrir el carrito de nadie.
 */
final class CarritoActual
{
    public const COOKIE = 'nga_carrito';

    /** Sin `crear`, null si todavía no tiene carrito. */
    public static function de(Request $request, bool $crear = false): ?Carrito
    {
        $cliente = $request->user()?->cliente;
        if ($cliente instanceof Cliente) {
            return self::usado($crear
                ? Carrito::createOrFirst(['cliente_id' => $cliente->id])
                : Carrito::where('cliente_id', $cliente->id)->first());
        }

        return self::delInvitado($request, $crear);
    }

    /** El de la cookie, aunque haya entrado en su cuenta (para sumarlo al suyo al entrar). */
    public static function delInvitado(Request $request, bool $crear = false): ?Carrito
    {
        $token = $request->cookie(self::COOKIE);
        $carrito = is_string($token) && strlen($token) === 40
            ? Carrito::whereNull('cliente_id')->where('token', hash('sha256', $token))->first()
            : null;

        if ($carrito === null && $crear) {
            $token = Str::random(40);
            $carrito = Carrito::create(['token' => hash('sha256', $token)]);
            Cookie::queue(self::COOKIE, $token, self::dias() * 24 * 60, httpOnly: true, sameSite: 'lax');
        } elseif ($carrito !== null) {
            // El plazo cuenta desde el último uso: cada visita alarga la cookie y el carrito.
            Cookie::queue(self::COOKIE, $token, self::dias() * 24 * 60, httpOnly: true, sameSite: 'lax');
            self::usado($carrito);
        }

        return $carrito;
    }

    /** Marca el carrito como usado (como mucho una escritura al día) para la retención. */
    private static function usado(?Carrito $carrito): ?Carrito
    {
        if ($carrito?->updated_at !== null && $carrito->updated_at->lt(now()->subDay())) {
            $carrito->touch();
        }

        return $carrito;
    }

    public static function olvidarInvitado(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    /** Días que se guarda un carrito sin cambios (Ajustes → General). */
    public static function dias(): int
    {
        return max(1, (int) Ajuste::obtener('carrito.dias'));
    }
}
