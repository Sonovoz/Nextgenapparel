<?php

namespace App\Filament\Resources\Precios\Pages;

use App\Dominio\Precios\Formato;
use App\Dominio\Precios\Motor\CalculoEnvio;
use App\Dominio\Precios\Motor\Destino;
use App\Filament\Resources\Precios\ZonaEnvioResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ManageZonaEnvio extends ManageRecords
{
    protected static string $resource = ZonaEnvioResource::class;

    public function getTitle(): string
    {
        return Str::ucfirst(static::getResource()::getNavigationLabel());
    }

    protected function getHeaderActions(): array
    {
        return [ZonaEnvioResource::accionCrear(), self::probar()];
    }

    /** D225: prueba un envío con el mismo cálculo que el carrito y dice el importe o el motivo exacto. */
    public static function probar(): Action
    {
        return Action::make('probar')->label('Probar envío')->icon(Heroicon::OutlinedTruck)->color('gray')
            ->modalDescription('El mismo cálculo que hace el carrito al cerrar el pedido.')
            ->modalSubmitActionLabel('Calcular')
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('pais')->label('País (código ISO)')->default('ES')->required()->maxLength(2),
                    TextInput::make('cp')->label('Código postal')->required()->maxLength(10),
                    TextInput::make('kilos')->label('Peso del pedido')->numeric()->minValue(0)->suffix('kg')->default(5)->required(),
                    TextInput::make('unidades')->label('Prendas')->integer()->minValue(1)->default(20)->required(),
                ]),
            ])
            ->action(function (array $data): void {
                $calculo = CalculoEnvio::para(new Destino(strtoupper(trim($data['pais'])), (string) $data['cp']), (int) round((float) $data['kilos'] * 1000), (int) $data['unidades'], 0);
                if ($calculo['envio'] === null) {
                    Notification::make()->danger()->title('No se puede enviar')->body(CalculoEnvio::texto((string) $calculo['motivo'], $calculo['zona']))->persistent()->send();

                    return;
                }
                $e = $calculo['envio'];
                Notification::make()->success()->title('Envío: '.Formato::euros($e['importe']).' sin IVA')
                    ->body("Zona «{$e['zona']}» · plazo {$e['plazo_dias']} días".($e['bultos'] ? " · {$e['bultos']} cajas" : ''))->persistent()->send();
            });
    }
}
