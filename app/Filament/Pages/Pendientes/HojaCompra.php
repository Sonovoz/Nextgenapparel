<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class HojaCompra extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Produccion;

    protected static ?string $navigationLabel = 'Hoja de compra';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'hoja-compra';

    protected static string $disponibleEn = 'fase 6';

    protected static string $descripcion = 'Prendas lisas necesarias para los pedidos pagados, agrupadas por variante y descontando el stock.';
}
