<?php

namespace App\Filament\Resources\Facturas;

use App\Dominio\Facturacion\TipoFactura;
use App\Dominio\Precios\Formato;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Filament\Resources\Facturas\Pages\ListFacturas;
use App\Filament\Resources\Pedidos\PedidoResource;
use App\Models\Factura;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Facturas emitidas (paso 6.5a, D175): solo lectura, con su PDF y su pedido. */
class FacturaResource extends Resource
{
    protected static ?string $model = Factura::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Pedidos;

    protected static ?string $navigationLabel = 'Facturas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'factura';

    protected static ?string $pluralModelLabel = 'facturas';

    protected static ?string $slug = 'facturas';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Todavía no hay facturas')
            ->emptyStateDescription('Se emiten solas al dar un pedido por pagado, con los datos de Ajustes → Facturación.')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('pedido'))
            ->columns([
                TextColumn::make('codigo')->label('Factura')->searchable()->weight('bold'),
                TextColumn::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn (TipoFactura $state) => $state->etiqueta('es'))
                    ->color(fn (TipoFactura $state) => $state->esRectificativa() ? 'danger' : 'gray'),
                TextColumn::make('emitida_en')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('pedido.numero')->label('Pedido')->searchable()
                    ->url(fn (Factura $r) => PedidoResource::getUrl('view', ['record' => $r->pedido])),
                TextColumn::make('destinatario.nombre')->label('Cliente')->description(fn (Factura $r) => $r->destinatario['nif'] ?? null),
                TextColumn::make('total')->label('Total')->alignEnd()->formatStateUsing(fn (int $state) => Formato::euros($state, 'es')),
                TextColumn::make('aeat_estado')->label('Veri*Factu')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tipo')->label('Tipo')->options(collect(TipoFactura::cases())->mapWithKeys(fn ($t) => [$t->value => $t->etiqueta('es')])->all()),
            ])
            ->recordActions([
                Action::make('pdf')->label('PDF')->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (Factura $r) => route('backend.pedidos.factura', ['pedido' => $r->pedido->uuid, 'factura' => $r->uuid]), shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListFacturas::route('/')];
    }
}
