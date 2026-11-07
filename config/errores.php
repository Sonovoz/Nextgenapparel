<?php

use App\Dominio\Sistema\Errores\ErrorDeCopia;
use App\Dominio\Sistema\Errores\ErrorDePrueba;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\LostConnectionException;
use Illuminate\Database\QueryException;

return [

    /*
    |--------------------------------------------------------------------------
    | Log de errores (Herramientas → Log de errores)
    |--------------------------------------------------------------------------
    */

    // Correo que recibe las alertas de errores críticos. Se configura en el
    // .env de cada entorno (D36). Vacío: no se envían alertas.
    'correo_alertas' => env('ALERTAS_EMAIL'),

    // Un mismo error crítico no vuelve a avisar hasta pasados estos minutos.
    'alerta_cada_minutos' => (int) env('ALERTAS_CADA_MINUTOS', 60),

    // Errores que se consideran críticos (y avisan por correo), además de
    // los errores internos de PHP (\Error): base de datos, conexión, etc.
    'criticos' => [
        QueryException::class,
        PDOException::class,
        LostConnectionException::class,
        FileNotFoundException::class,
        ErrorDePrueba::class,
        ErrorDeCopia::class,
    ],

    // Los errores resueltos se eliminan pasados estos días.
    'conservar_resueltos_dias' => 90,

];
