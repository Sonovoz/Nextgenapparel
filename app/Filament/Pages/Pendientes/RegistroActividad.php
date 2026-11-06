<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class RegistroActividad extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Herramientas;

    protected static ?string $navigationLabel = 'Registro de actividad';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'registro-actividad';

    protected static string $disponibleEn = 'fase 1 · paso 1.4';

    protected static string $descripcion = 'Qué administrador cambió qué y cuándo.';
}
