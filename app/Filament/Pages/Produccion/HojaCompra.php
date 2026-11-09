<?php

namespace App\Filament\Pages\Produccion;

use App\Dominio\Produccion\HojaCompra as Hoja;
use App\Filament\Navegacion\GrupoNavegacion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Producción → Hoja de compra (paso 6.5b): prendas lisas que faltan para los pedidos
 * pagados, por proveedor y variante, descontando el stock. Se descarga en CSV.
 */
class HojaCompra extends Page
{
    protected string $view = 'filament.pages.hoja-compra';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Produccion;

    protected static ?string $navigationLabel = 'Hoja de compra';

    protected static ?string $title = 'Hoja de compra';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'hoja-compra';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Produccion->permiso());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('csv')->label('Descargar CSV')->icon(Heroicon::OutlinedArrowDownTray)
                ->url(route('backend.produccion.hoja')),
        ];
    }

    protected function getViewData(): array
    {
        return ['filas' => Hoja::filas()];
    }
}
