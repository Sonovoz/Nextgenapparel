<?php

namespace App\Dominio\Sistema\Salud\Comprobaciones;

use App\Dominio\Sistema\Copias\Copias;
use App\Dominio\Sistema\Salud\Resultado;

/**
 * La última copia debe tener menos de 26 horas y la última prueba de
 * restauración debe haber ido bien. En el servidor, las copias deben ir
 * cifradas.
 */
class CopiasSeguridad implements Comprobacion
{
    public const HORAS_MAXIMAS = 26;

    public function clave(): string
    {
        return 'copias-seguridad';
    }

    public function nombre(): string
    {
        return 'Copias de seguridad';
    }

    public function comprobar(): array
    {
        $ultima = collect(Copias::listar())->firstWhere('disco', Copias::DISCO);

        if ($ultima === null) {
            return app()->isProduction()
                ? Resultado::fallo('No hay ninguna copia de seguridad.')
                : Resultado::aviso('Todavía no hay copias. Haz una desde Herramientas → Copias de seguridad.');
        }

        if ($ultima['fecha']->lt(now()->subHours(self::HORAS_MAXIMAS))) {
            $texto = 'La última copia es del '.$ultima['fecha']->format('d/m/Y H:i').' ('.$ultima['fecha']->diffForHumans().').';

            return app()->isProduction() ? Resultado::fallo($texto) : Resultado::aviso($texto.' En local se hacen a la 01:30 si «schedule:work» está abierto.');
        }

        $prueba = Copias::ultimaPrueba();

        if ($prueba !== null && ! $prueba['correcto']) {
            return Resultado::fallo('La última prueba de restauración falló: '.$prueba['mensaje']);
        }

        if (app()->isProduction() && blank(config('backup.backup.password'))) {
            return Resultado::aviso('Las copias no están cifradas (falta BACKUP_ARCHIVE_PASSWORD).');
        }

        return Resultado::correcto('Última copia: '.$ultima['fecha']->format('d/m/Y H:i').'.');
    }
}
