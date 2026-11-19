<?php

// D253: API de TopTex (proveedor de prendas). Las credenciales, solo en el .env: nunca en el código ni en el panel.
return [
    'url' => env('TOPTEX_URL', 'https://api.toptex.io'),
    'api_key' => env('TOPTEX_API_KEY'),
    'usuario' => env('TOPTEX_USUARIO'),   // formato empresa_cliente, en minúsculas (p. ej. toes_xxxx)
    'clave' => env('TOPTEX_CLAVE'),
    'timeout' => (int) env('TOPTEX_TIMEOUT', 30),
];
