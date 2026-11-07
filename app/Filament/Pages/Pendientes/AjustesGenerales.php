<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AjustesGenerales extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Ajustes;

    protected static ?string $navigationLabel = 'General';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'ajustes-generales';

    protected static string $disponibleEn = 'fase 2';

    protected static string $descripcion = 'Datos de la empresa, idiomas y moneda.';

    protected static ?string $equivalenteWooCommerce = 'Ajustes → General';
}
