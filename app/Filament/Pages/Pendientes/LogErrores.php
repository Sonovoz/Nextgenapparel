<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class LogErrores extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Herramientas;

    protected static ?string $navigationLabel = 'Log de errores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBugAnt;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'log-errores';

    protected static string $disponibleEn = 'fase 1 · paso 1.4';

    protected static string $descripcion = 'Errores agrupados con gravedad, contador, primera y última vez, y estado pendiente o resuelto.';

    protected static ?string $equivalenteWooCommerce = 'Estado → Registros';
}
