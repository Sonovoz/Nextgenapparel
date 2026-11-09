<?php

return [
    // Verificación en dos pasos del equipo en el panel (D186). Solo se quita en local y en los tests.
    'dos_pasos_obligatorios' => (bool) env('DOS_PASOS_OBLIGATORIOS', true),
];
