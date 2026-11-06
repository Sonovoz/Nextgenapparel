<?php

namespace App\Filament\Pages;

use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Qué puede ver cada rol. Los roles son fijos (D47): esta página los
 * muestra, no los edita.
 */
class RolesPermisos extends Page
{
    protected string $view = 'filament.pages.roles-permisos';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::UsuariosYRoles;

    protected static ?string $navigationLabel = 'Roles y permisos';

    protected static ?string $title = 'Roles y permisos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'roles';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::UsuariosYRoles->permiso());
    }

    protected function getViewData(): array
    {
        return [
            'roles' => collect(Rol::cases())->map(fn (Rol $rol) => [
                'nombre' => $rol->getLabel(),
                'descripcion' => $rol->descripcion(),
                'usuarios' => User::role($rol->value)->where('activo', true)->count(),
                'rol' => $rol,
            ]),
            'grupos' => GrupoNavegacion::cases(),
        ];
    }
}
