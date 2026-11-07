<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ReglasPrecio extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Marketing;

    protected static ?string $navigationLabel = 'Reglas de precio y mínimos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'reglas-precio';

    protected static string $disponibleEn = 'fase 3';

    protected static string $descripcion = 'El calibrador: mínimos por pedido, talla o importe, tramos por cantidad y ofertas combinadas.';
}
