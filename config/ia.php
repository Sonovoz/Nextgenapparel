<?php

/*
| Asistente IA de venta (v2, D194): OpenAI (decisión del cliente). La clave solo en el .env (regla 10);
| el modelo, los límites y los precios por millón de tokens se ajustan en el backend (Ajustes → IA).
*/
return [
    'openai_key' => env('OPENAI_API_KEY'),
    'openai_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
];
