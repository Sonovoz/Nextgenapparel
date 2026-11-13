<?php

namespace App\Dominio\Sistema\Salud\Comprobaciones;

use App\Dominio\Sistema\Almacenamiento\Almacenamiento as Medida;
use App\Dominio\Sistema\Salud\Resultado;
use App\Models\Ajuste;

/** D231: lo que ocupa el proyecto frente al espacio máximo de Herramientas → Almacenamiento. */
class Almacenamiento implements Comprobacion
{
    public function clave(): string
    {
        return 'almacenamiento';
    }

    public function nombre(): string
    {
        return 'Espacio del proyecto';
    }

    public function comprobar(): array
    {
        $usado = Medida::total();
        $porcentaje = Medida::porcentaje();
        $texto = Medida::legible($usado).' de '.Medida::legible(Medida::limiteBytes())." ({$porcentaje} %).";

        return match (true) {
            $porcentaje >= (int) Ajuste::obtener('almacenamiento.fallo_pct') => Resultado::fallo('Casi lleno: '.$texto.' Borra archivos sin venta o amplía el espacio en Herramientas → Almacenamiento.'),
            $porcentaje >= (int) Ajuste::obtener('almacenamiento.aviso_pct') => Resultado::aviso('Se está llenando: '.$texto),
            default => Resultado::correcto($texto),
        };
    }
}
