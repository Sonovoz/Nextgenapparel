<?php

namespace App\Dominio\Catalogo;

use App\Models\Foto;
use App\Models\FotoZona;

/**
 * Las zonas de impresión colocadas en la maqueta de un color, en las maquetas de los demás colores
 * de la misma prenda y cara (D211): las fotos de una prenda se hacen con el mismo encuadre, así que
 * el equipo las coloca una vez. Se puede no copiar si un color tiene otro encuadre.
 */
final class CopiarZonas
{
    /**
     * Maquetas de los demás colores de la misma prenda y cara.
     *
     * @return list<Foto>
     */
    public static function hermanas(Foto $foto): array
    {
        return array_values(Foto::where('prenda_id', $foto->prenda_id)->where('tipo', TipoFoto::Maqueta->value)
            ->where('cara', self::cara($foto))->whereKeyNot($foto->getKey())->get()->all());
    }

    /** ¿Es una maqueta con su cara? (las únicas con zonas) */
    public static function esMaqueta(?Foto $foto): bool
    {
        if ($foto === null) {
            return false;
        }
        $tipo = $foto->getAttribute('tipo');

        return ($tipo instanceof TipoFoto ? $tipo : TipoFoto::tryFrom((string) $tipo)) === TipoFoto::Maqueta && self::cara($foto) !== null;
    }

    private static function cara(Foto $foto): ?string
    {
        $cara = $foto->getAttribute('cara');

        return $cara instanceof Cara ? $cara->value : (is_string($cara) && $cara !== '' ? $cara : null);
    }

    /** Copia las zonas de esta maqueta a sus hermanas. Devuelve cuántas fotos se han actualizado. */
    public static function aOtrosColores(Foto $foto): int
    {
        if (! self::esMaqueta($foto)) {
            return 0;
        }
        $hermanas = self::hermanas($foto);
        foreach ($hermanas as $hermana) {
            self::copiar($foto, $hermana);
        }

        return count($hermanas);
    }

    /** Para una maqueta nueva: toma las zonas de una hermana ya colocada, si la hay. */
    public static function desdeOtroColor(Foto $foto): bool
    {
        if (! self::esMaqueta($foto) || $foto->zonas()->exists()) {
            return false; // ya tiene sus zonas: no se pisan
        }
        foreach (self::hermanas($foto) as $modelo) {
            if ($modelo->zonas()->exists()) {
                self::copiar($modelo, $foto);

                return true;
            }
        }

        return false;
    }

    private static function copiar(Foto $origen, Foto $destino): void
    {
        foreach (FotoZona::where('foto_id', $origen->getKey())->get() as $zona) {
            FotoZona::updateOrCreate(
                ['foto_id' => $destino->getKey(), 'zona_impresion_id' => $zona->zona_impresion_id],
                ['x' => $zona->x, 'y' => $zona->y, 'ancho' => $zona->ancho, 'alto' => $zona->alto],
            );
        }
    }
}
