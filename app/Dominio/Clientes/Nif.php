<?php

namespace App\Dominio\Clientes;

/**
 * Número de identificación fiscal español (fase 5): DNI, NIE o CIF, con su
 * control. Se normaliza a mayúsculas sin espacios, guiones ni puntos.
 *
 * - DNI: 8 cifras y letra (resto entre 23).
 * - NIE: X, Y o Z (que valen 0, 1 y 2), 7 cifras y letra, igual que el DNI.
 * - CIF: letra de tipo de entidad, 7 cifras y control. El control es una cifra
 *   para A, B, E y H; una letra para K, P, Q, S, N y W; y cualquiera de los dos
 *   para el resto (C, D, F, G, J, R, U, V).
 */
final class Nif
{
    private const LETRAS_DNI = 'TRWAGMYFPDXBNJZSQVHLCKE';

    private const LETRAS_CIF = 'JABCDEFGHI';

    public static function normalizar(string $valor): string
    {
        return strtoupper((string) preg_replace('/[\s.\-]/', '', $valor));
    }

    public static function valido(string $valor): bool
    {
        $nif = self::normalizar($valor);

        return match (true) {
            (bool) preg_match('/^\d{8}[A-Z]$/', $nif) => self::letraDni((int) substr($nif, 0, 8)) === $nif[8],
            (bool) preg_match('/^[XYZ]\d{7}[A-Z]$/', $nif) => self::letraDni((int) (strpos('XYZ', $nif[0]).substr($nif, 1, 7))) === $nif[8],
            (bool) preg_match('/^[ABCDEFGHJKLMNPQRSUVW]\d{7}[0-9A-J]$/', $nif) => self::cifValido($nif),
            default => false,
        };
    }

    /** dni | nie | cif, o null si no es válido. */
    public static function tipo(string $valor): ?string
    {
        if (! self::valido($valor)) {
            return null;
        }
        $nif = self::normalizar($valor);

        return ctype_digit($nif[0]) ? 'dni' : (str_contains('XYZ', $nif[0]) ? 'nie' : 'cif');
    }

    private static function letraDni(int $numero): string
    {
        return self::LETRAS_DNI[$numero % 23];
    }

    private static function cifValido(string $cif): bool
    {
        $suma = 0;
        foreach (str_split(substr($cif, 1, 7)) as $i => $cifra) {
            $valor = (int) $cifra;
            if ($i % 2 === 0) { // posiciones impares: se dobla y se suman sus cifras
                $valor *= 2;
                $valor = intdiv($valor, 10) + $valor % 10;
            }
            $suma += $valor;
        }
        $control = (10 - $suma % 10) % 10;
        $final = $cif[8];

        return match (true) {
            str_contains('ABEH', $cif[0]) => $final === (string) $control,
            str_contains('KPQSNW', $cif[0]) => $final === self::LETRAS_CIF[$control],
            default => $final === (string) $control || $final === self::LETRAS_CIF[$control],
        };
    }
}
