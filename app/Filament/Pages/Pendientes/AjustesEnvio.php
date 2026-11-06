<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AjustesEnvio extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Ajustes;

    protected static ?string $navigationLabel = 'Envío';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'ajustes-envio';

    protected static string $disponibleEn = 'fase 3';

    protected static string $descripcion = 'Zonas de envío y tarifas por kilo o por caja, con el plazo de cada transportista.';

    protected static ?string $equivalenteWooCommerce = 'Ajustes → Envío';
}
