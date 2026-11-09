<?php

namespace App\Filament\Resources\Pedidos\Pages;

use App\Dominio\Pedidos\CambiarEstado;
use App\Dominio\Pedidos\CambioNoValido;
use App\Dominio\Pedidos\CorreoPedido;
use App\Dominio\Pedidos\EstadoPedido;
use App\Filament\Resources\Pedidos\PedidoResource;
use App\Models\Pedido;
use App\Notifications\Pedidos\CorreoDelPedido;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/** Ficha del pedido con las «Acciones del pedido» de WooCommerce (paso 6.3). */
class ViewPedido extends ViewRecord
{
    protected static string $resource = PedidoResource::class;

    public function getTitle(): string
    {
        return 'Pedido '.$this->getRecord()->numero;
    }

    protected function getHeaderActions(): array
    {
        /** @var Pedido $pedido */
        $pedido = $this->getRecord();

        return [
            Action::make('estado')->label('Cambiar estado')->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn () => $pedido->estado->siguientes() !== [])
                ->schema([
                    Select::make('estado')->label('Nuevo estado')->required()->native(false)->live()
                        ->options(collect($pedido->estado->siguientes())->mapWithKeys(fn (EstadoPedido $e) => [$e->value => $e->etiqueta()])->all()),
                    TextInput::make('transportista')->label('Transportista')->maxLength(80)
                        ->visible(fn (Get $get) => $get('estado') === EstadoPedido::Enviado->value)->required(fn (Get $get) => $get('estado') === EstadoPedido::Enviado->value),
                    TextInput::make('numero_seguimiento')->label('Número de seguimiento')->maxLength(80)
                        ->visible(fn (Get $get) => $get('estado') === EstadoPedido::Enviado->value)->required(fn (Get $get) => $get('estado') === EstadoPedido::Enviado->value),
                    Textarea::make('nota')->label('Nota para el historial')->maxLength(500),
                ])
                ->action(function (array $data) use ($pedido): void {
                    try {
                        app(CambiarEstado::class)($pedido, EstadoPedido::from($data['estado']), auth()->user(), $data['nota'] ?? null, $data);
                        Notification::make()->title('Estado cambiado')->success()->send();
                    } catch (CambioNoValido $e) {
                        Notification::make()->title('No se ha cambiado')->body($e->getMessage())->danger()->send();
                    }
                    $this->refreshFormData(['estado']);
                }),
            Action::make('nota')->label('Añadir nota')->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)->color('gray')
                ->schema([
                    Radio::make('tipo')->label('Tipo')->required()->default('privada')->options([
                        'privada' => 'Nota privada (solo el equipo)',
                        'cliente' => 'Nota para el cliente (la ve en su pedido)',
                    ]),
                    Textarea::make('texto')->label('Nota')->required()->maxLength(500)->rows(4),
                ])
                ->action(function (array $data) use ($pedido): void {
                    $pedido->notas()->create(['tipo' => $data['tipo'], 'texto' => trim($data['texto']), 'user_id' => auth()->id()]);
                    // La nota para el cliente le llega también por correo (paso 6.4).
                    $avisado = $data['tipo'] === 'cliente' && CorreoPedido::enviar($pedido, new CorreoDelPedido($pedido, 'nota', nota: $data['texto']));
                    Notification::make()->title('Nota añadida')->body($avisado ? 'Se le ha enviado por correo.' : null)->success()->send();
                }),
            Action::make('fecha')->label('Fecha estimada')->icon(Heroicon::OutlinedCalendarDays)->color('gray')
                ->fillForm(['fecha_estimada_entrega' => $pedido->fecha_estimada_entrega])
                ->schema([
                    DatePicker::make('fecha_estimada_entrega')->label('Entrega estimada')->required()->native(false)->displayFormat('d/m/Y'),
                    Textarea::make('motivo')->label('Motivo (queda en el historial)')->maxLength(300),
                ])
                ->action(function (array $data) use ($pedido): void {
                    $pedido->update(['fecha_estimada_entrega' => $data['fecha_estimada_entrega']]);
                    $pedido->historial()->create(['estado' => $pedido->estado, 'user_id' => auth()->id(),
                        'nota' => 'Entrega estimada: '.$pedido->fresh()->fecha_estimada_entrega->format('d/m/Y').(blank($data['motivo'] ?? null) ? '' : ' · '.trim($data['motivo']))]);
                    Notification::make()->title('Fecha cambiada')->success()->send();
                }),
        ];
    }
}
