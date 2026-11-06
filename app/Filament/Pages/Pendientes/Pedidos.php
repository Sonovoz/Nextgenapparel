<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Pedidos extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Pedidos;

    protected static ?string $navigationLabel = 'Pedidos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'pedidos';

    protected static string $disponibleEn = 'fase 6';

    protected static string $descripcion = 'Lista de pedidos con sus estados, notas privadas y para el cliente, acciones en bloque, plazos estimados, cancelaciones y reembolsos.';

    protected static ?string $equivalenteWooCommerce = 'Pedidos';
}
