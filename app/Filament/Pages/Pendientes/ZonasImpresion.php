<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ZonasImpresion extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Productos;

    protected static ?string $navigationLabel = 'Zonas de impresión';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewfinderCircle;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'zonas-impresion';

    protected static string $disponibleEn = 'fase 2';

    protected static string $descripcion = 'Frontal, pecho y espalda grande, con sus medidas máximas y su posición sobre las fotos de cada prenda.';
}
