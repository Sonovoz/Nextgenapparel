<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AjustesPagos extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Ajustes;

    protected static ?string $navigationLabel = 'Pagos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'ajustes-pagos';

    protected static string $disponibleEn = 'fase 7';

    protected static string $descripcion = 'Redsys (tarjeta y Bizum), con modo de pruebas o real.';

    protected static ?string $equivalenteWooCommerce = 'Ajustes → Pagos';
}
