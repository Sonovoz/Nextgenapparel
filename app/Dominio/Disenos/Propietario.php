<?php

namespace App\Dominio\Disenos;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Dueño de los diseños de un visitante sin cuenta: un token aleatorio en su
 * sesión. En la base de datos solo se guarda su hash, para que una copia de la
 * tabla no permita hacerse pasar por nadie. Al registrarse (fase 5) los diseños
 * pasan a su ficha de cliente.
 */
final class Propietario
{
    private const CLAVE = 'disenos.propietario';

    /** Hash del dueño; sin `crear`, null si la sesión todavía no tiene diseños. */
    public static function hash(Request $request, bool $crear = true): ?string
    {
        $token = $request->session()->get(self::CLAVE);

        if (! is_string($token) || $token === '') {
            if (! $crear) {
                return null;
            }
            $token = Str::random(40);
            $request->session()->put(self::CLAVE, $token);
        }

        return hash('sha256', $token);
    }
}
