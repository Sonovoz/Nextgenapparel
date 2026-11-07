<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Atributos extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Productos;

    protected static ?string $navigationLabel = 'Atributos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'atributos';

    protected static string $disponibleEn = 'fase 2';

    protected static string $descripcion = 'Colores y tallas, con su código de color y la tabla de medidas por talla.';

    protected static ?string $equivalenteWooCommerce = 'Productos → Atributos';
}
