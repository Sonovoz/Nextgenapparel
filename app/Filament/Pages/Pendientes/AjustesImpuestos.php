<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AjustesImpuestos extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Ajustes;

    protected static ?string $navigationLabel = 'Impuestos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'ajustes-impuestos';

    protected static string $disponibleEn = 'fase 3';

    protected static string $descripcion = 'Clases de impuesto por zona: IVA, recargo de equivalencia e IGIC.';

    protected static ?string $equivalenteWooCommerce = 'Ajustes → Impuestos';
}
