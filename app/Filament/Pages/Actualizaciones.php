<?php

namespace App\Filament\Pages;

use App\Dominio\Sistema\Actualizaciones as Lista;
use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Herramientas → Actualizaciones (D236): cada actualización numerada, la instalada arriba. */
class Actualizaciones extends Page
{
    protected string $view = 'filament.pages.actualizaciones';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Herramientas;

    protected static ?string $navigationLabel = 'Actualizaciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpCircle;

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'actualizaciones';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Herramientas->permiso());
    }

    public function getTitle(): string
    {
        $actual = Lista::actual();

        return $actual ? 'Actualizaciones · instalada la nº '.$actual['numero'] : 'Actualizaciones';
    }

    protected function getViewData(): array
    {
        return ['actualizaciones' => Lista::todas()];
    }
}
