<?php

namespace App\Dominio\Disenos;

use App\Models\Ajuste;

/**
 * Instrucciones para que el cliente mejore su diseño con su propia
 * herramienta de IA cuando la calidad no llega (D143). La tienda no envía
 * nada a ninguna IA: solo le da el texto, con su tamaño de impresión, para
 * copiarlo. Todo se configura en Ajustes → General.
 */
final class MejoraIa
{
    /** Niveles de calidad, de mejor a peor, desde los que se puede ofrecer. */
    public const NIVELES = ['buena', 'justa', 'baja'];

    public static function activa(): bool
    {
        return (bool) Ajuste::obtener('disenos.ia_activa');
    }

    /** Se ofrece desde este nivel de calidad hacia abajo. */
    public static function desde(): string
    {
        $desde = Ajuste::obtener('disenos.ia_desde');

        return in_array($desde, self::NIVELES, true) ? $desde : 'justa';
    }

    /** @return list<string> niveles en los que se ofrece */
    public static function niveles(): array
    {
        return self::activa() ? array_slice(self::NIVELES, (int) array_search(self::desde(), self::NIVELES, true)) : [];
    }

    /** El texto del backend en ese idioma o, si está vacío, el de por defecto. «:medida» es el tamaño de impresión. */
    public static function texto(?string $idioma = null): string
    {
        $idioma ??= app()->getLocale();
        $propios = Ajuste::obtener('disenos.ia_texto');
        $propio = is_array($propios) ? trim((string) ($propios[$idioma] ?? '')) : '';

        return $propio !== '' ? $propio : self::porDefecto($idioma);
    }

    public static function porDefecto(string $idioma): string
    {
        return trans('disenos.ia_texto', [], $idioma);
    }

    /** @return array{niveles: list<string>, texto: string} para el visor */
    public static function paraVisor(): array
    {
        return ['niveles' => self::niveles(), 'texto' => self::texto()];
    }
}
