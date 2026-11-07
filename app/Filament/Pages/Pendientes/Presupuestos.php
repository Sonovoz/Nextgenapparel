<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Presupuestos extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Pedidos;

    protected static ?string $navigationLabel = 'Presupuestos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'presupuestos';

    protected static string $disponibleEn = 'fase 6';

    protected static string $descripcion = 'Presupuestos en PDF con su validez, convertibles en pedido con un clic.';
}
