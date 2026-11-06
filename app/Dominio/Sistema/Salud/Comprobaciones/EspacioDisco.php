<?php

namespace App\Dominio\Sistema\Salud\Comprobaciones;

use App\Dominio\Sistema\Salud\EstadoSalud;
use App\Dominio\Sistema\Salud\Resultado;

/**
 * Espacio libre del disco donde se guardan los archivos (diseños, PDF,
 * copias). Funciona igual en Linux y en Windows.
 */
class EspacioDisco implements Comprobacion
{
    public const AVISO_PORCENTAJE_LIBRE = 15;

    public const FALLO_PORCENTAJE_LIBRE = 5;

    public function __construct(private ?string $ruta = null) {}

    public function clave(): string
    {
        return 'espacio-disco';
    }

    public function nombre(): string
    {
        return 'Espacio en disco';
    }

    public function comprobar(): array
    {
        $ruta = $this->ruta ?? storage_path();
        $libre = @disk_free_space($ruta);
        $total = @disk_total_space($ruta);

        if ($libre === false || $total === false || $total <= 0) {
            return Resultado::aviso('No se ha podido medir el espacio libre.');
        }

        return self::evaluar($libre, $total);
    }

    /** @return array{0: EstadoSalud, 1: string} */
    public static function evaluar(float $libre, float $total): array
    {
        $porcentaje = (int) floor($libre / $total * 100);
        $texto = self::gb($libre).' libres de '.self::gb($total)." ({$porcentaje} %).";

        return match (true) {
            $porcentaje < self::FALLO_PORCENTAJE_LIBRE => Resultado::fallo('Casi lleno: '.$texto),
            $porcentaje < self::AVISO_PORCENTAJE_LIBRE => Resultado::aviso('Queda poco espacio: '.$texto),
            default => Resultado::correcto($texto),
        };
    }

    private static function gb(float $bytes): string
    {
        return number_format($bytes / 1024 ** 3, 1, ',', '.').' GB';
    }
}
