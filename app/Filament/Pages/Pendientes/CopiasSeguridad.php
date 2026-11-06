<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CopiasSeguridad extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Herramientas;

    protected static ?string $navigationLabel = 'Copias de seguridad';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'copias-seguridad';

    protected static string $disponibleEn = 'fase 1 · paso 1.6';

    protected static string $descripcion = 'Estado de cada copia de la base de datos y de los archivos, con la copia fuera del servidor.';
}
