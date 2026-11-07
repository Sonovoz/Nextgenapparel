<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Familias extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Productos;

    protected static ?string $navigationLabel = 'Familias';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'familias';

    protected static string $disponibleEn = 'fase 2';

    protected static string $descripcion = 'Camisetas, polos y sudaderas, con su plazo base de producción.';

    protected static ?string $equivalenteWooCommerce = 'Productos → Categorías';
}
