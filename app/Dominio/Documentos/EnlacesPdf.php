<?php

namespace App\Dominio\Documentos;

use App\Dominio\Legal\PaginaLegal;
use App\Models\Ajuste;

/**
 * Enlaces de los PDF para el cliente (D215): preguntas frecuentes y condiciones de venta, en su idioma,
 * y el correo de contacto de la tienda para «Estamos a tu disposición».
 */
final class EnlacesPdf
{
    /** @return array<string, string> texto => URL */
    public static function para(string $idioma): array
    {
        return [
            __('pdf.preguntas', [], $idioma) => route('portada', ['idioma' => $idioma]).'#preguntas',
            __('pdf.condiciones', [], $idioma) => route('legal', ['idioma' => $idioma, 'pagina' => PaginaLegal::Condiciones->value]),
        ];
    }

    /** Correo para escribirnos: el de contacto de la tienda o, si no hay, el de facturación. */
    public static function correo(?string $deFacturacion = null): ?string
    {
        $correo = Ajuste::obtener('tienda.correo_contacto') ?: $deFacturacion;

        return is_string($correo) && $correo !== '' ? $correo : null;
    }
}
