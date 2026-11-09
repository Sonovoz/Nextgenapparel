<?php

namespace App\Filament\Pages\Produccion;

use App\Dominio\Produccion\PliegosDti;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Ajuste;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Producción → Pliegos DTI (v2, D193): resumen de los pliegos de los pedidos en producción y su PDF. */
class PliegosDtiPagina extends Page
{
    protected string $view = 'filament.pages.pliegos-dti';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Produccion;

    protected static ?string $navigationLabel = 'Pliegos DTI';

    protected static ?string $title = 'Pliegos DTI';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'pliegos-dti';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Produccion->permiso());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')->label('Descargar pliegos (PDF)')->icon(Heroicon::OutlinedArrowDownTray)->url(route('backend.produccion.pliegos')),
            Action::make('medidas')->label('Medidas del film')->icon(Heroicon::OutlinedAdjustmentsHorizontal)->color('gray')
                ->fillForm(fn () => ['ancho' => Ajuste::obtener('produccion.pliego_ancho_mm'), 'largo' => Ajuste::obtener('produccion.pliego_largo_mm'), 'separacion' => Ajuste::obtener('produccion.pliego_separacion_mm')])
                ->schema([
                    TextInput::make('ancho')->label('Ancho del film')->suffix('mm')->integer()->minValue(200)->maxValue(2000)->required(),
                    TextInput::make('largo')->label('Largo máximo de cada pliego')->suffix('mm')->integer()->minValue(300)->maxValue(10000)->required(),
                    TextInput::make('separacion')->label('Separación entre diseños')->suffix('mm')->integer()->minValue(0)->maxValue(50)->required(),
                ])
                ->action(function (array $data): void {
                    foreach (['ancho' => 'pliego_ancho_mm', 'largo' => 'pliego_largo_mm', 'separacion' => 'pliego_separacion_mm'] as $campo => $clave) {
                        Ajuste::guardar('produccion.'.$clave, (int) $data[$campo]);
                    }
                    Actividad::apuntar('ajustes', 'Medidas de los pliegos DTI cambiadas', propiedades: $data);
                    Notification::make()->title('Medidas guardadas')->success()->send();
                }),
        ];
    }

    protected function getViewData(): array
    {
        $plan = PliegosDti::plan();

        return ['plan' => $plan, 'metros' => array_sum(array_column($plan['pliegos'], 'largo')) / 1000, 'piezas' => array_sum(array_map(fn ($p) => count($p['piezas']), $plan['pliegos']))];
    }
}
