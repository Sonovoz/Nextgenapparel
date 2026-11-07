<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Productos extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Productos;

    protected static ?string $navigationLabel = 'Todos los productos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'productos';

    protected static string $disponibleEn = 'fase 2';

    protected static string $descripcion = 'Prendas como productos variables: atributos color × talla, variaciones con SKU, precio, coste, stock y peso.';

    protected static ?string $equivalenteWooCommerce = 'Productos → Todos los productos';
}
