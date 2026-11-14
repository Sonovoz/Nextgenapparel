<?php

return [
    // Verificación en dos pasos del equipo en el panel (D186). Desactivada por ahora por decisión del cliente
    // (D197): se vuelve a exigir con DOS_PASOS_OBLIGATORIOS=true en el .env del servidor.
    'dos_pasos_obligatorios' => (bool) env('DOS_PASOS_OBLIGATORIOS', false),
];
