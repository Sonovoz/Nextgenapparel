<?php

/*
| Pagos (fase 7, D181), portado de Clave de Alma (ADR 0026).
| - "redsys": TPV virtual del banco, tarjeta y (si el banco lo activa) Bizum. Entorno "test" o "live".
| - "simulada": pago simulado para probar sin TPV. Prohibido en producción.
| Los datos del TPV solo en el .env del servidor (regla 10): nunca en el código, en GitHub ni en el chat.
*/
return [
    'pasarela' => env('PAGOS_PASARELA', 'simulada'),

    'redsys' => [
        'comercio' => env('REDSYS_MERCHANT_CODE', ''),
        'terminal' => env('REDSYS_TERMINAL', '1'),
        'clave' => env('REDSYS_SECRET_KEY', ''),
        'entorno' => env('REDSYS_ENVIRONMENT', 'test'),
        'tarjeta' => (bool) env('REDSYS_CARD', true),
        'bizum' => (bool) env('REDSYS_BIZUM', false),
        'nombre' => env('REDSYS_MERCHANT_NAME', 'Nextgenapparel'),
    ],
];
