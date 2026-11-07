<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Cupones extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Marketing;

    protected static ?string $navigationLabel = 'Cupones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'cupones';

    protected static string $disponibleEn = 'fase 3';

    protected static string $descripcion = 'Cupones por porcentaje o importe, con caducidad y límite de usos.';

    protected static ?string $equivalenteWooCommerce = 'Marketing → Cupones';
}
