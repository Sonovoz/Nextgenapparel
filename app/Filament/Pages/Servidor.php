<?php

namespace App\Filament\Pages;

use App\Dominio\Sistema\Servidor\InformeServidor;
use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Herramientas → Servidor (D207): PHP, extensiones, base de datos y Redis del servidor, en solo lectura. */
class Servidor extends Page
{
    protected string $view = 'filament.pages.servidor';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Herramientas;

    protected static ?string $navigationLabel = 'Servidor';

    protected static ?string $title = 'Servidor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'servidor';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Herramientas->permiso());
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['grupos' => collect(InformeServidor::filas())->groupBy('grupo')];
    }
}
