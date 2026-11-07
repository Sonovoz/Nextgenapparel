<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Informes extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Analiticas;

    protected static ?string $navigationLabel = 'Informes de ventas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'informes';

    protected static string $disponibleEn = 'fase 8';

    protected static string $descripcion = 'Ventas, pedidos, ticket medio, lo más vendido y ofertas aplicadas, con filtros por fecha y exportación a CSV o Excel.';

    protected static ?string $equivalenteWooCommerce = 'Analíticas';
}
