<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Clientes extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Clientes;

    protected static ?string $navigationLabel = 'Clientes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'clientes';

    protected static string $disponibleEn = 'fase 5';

    protected static string $descripcion = 'Clientes con su ficha fiscal (incluido el recargo de equivalencia), direcciones, pedidos y biblioteca de diseños.';

    protected static ?string $equivalenteWooCommerce = 'Clientes';
}
