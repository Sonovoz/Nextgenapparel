<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Roles extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::UsuariosYRoles;

    protected static ?string $navigationLabel = 'Roles y permisos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'roles';

    protected static string $disponibleEn = 'fase 1 · paso 1.3';

    protected static string $descripcion = 'Superadministrador, Administrador, Gestor de la tienda y Producción, con lo que puede ver y hacer cada uno.';
}
