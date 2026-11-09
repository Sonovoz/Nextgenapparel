<?php

// Facturación (D175). Veri*Factu queda desactivado hasta que sea obligatorio (como en Clave de Alma,
// ADR 0018): la huella encadenada se guarda igualmente y al activarlo solo cambian las facturas nuevas.
return [
    'verifactu' => [
        'activo' => (bool) env('VERIFACTU_ACTIVO', false),
        'produccion' => (bool) env('VERIFACTU_PRODUCCION', false),
    ],
];
