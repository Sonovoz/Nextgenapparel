<?php

namespace App\Filament\Pages\Pendientes;

use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Usuarios extends PaginaPendiente
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::UsuariosYRoles;

    protected static ?string $navigationLabel = 'Usuarios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'usuarios';

    protected static string $disponibleEn = 'fase 1 · paso 1.3';

    protected static string $descripcion = 'Usuarios del backend y su rol.';

    protected static ?string $equivalenteWooCommerce = 'Usuarios';
}
