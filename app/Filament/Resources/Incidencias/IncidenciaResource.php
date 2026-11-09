<?php

namespace App\Filament\Resources\Incidencias;

use App\Dominio\Incidencias\MotivoIncidencia;
use App\Dominio\Incidencias\ResolucionIncidencia;
use App\Dominio\Precios\Formato;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Filament\Resources\Incidencias\Pages\ListIncidencias;
use App\Filament\Resources\Incidencias\Pages\ViewIncidencia;
use App\Filament\Resources\Pedidos\PedidoResource;
use App\Models\Incidencia;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Pedidos → Incidencias (paso 6.6b, D178): las que abren los clientes, con fotos, y su resolución. */
class IncidenciaResource extends Resource
{
    protected static ?string $model = Incidencia::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Pedidos;

    protected static ?string $navigationLabel = 'Incidencias';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'incidencia';

    protected static ?string $pluralModelLabel = 'incidencias';

    protected static ?string $slug = 'incidencias';

    protected static ?string $recordRouteKeyName = 'uuid';

    public static function getNavigationBadge(): ?string
    {
        $n = rescue(fn () => Incidencia::where('estado', 'abierta')->count(), 0, report: false);

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Abiertas';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No hay incidencias')
            ->emptyStateDescription('Las abren los clientes desde su pedido, una vez enviado.')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('pedido'))
            ->columns([
                TextColumn::make('codigo')->label('Incidencia')->searchable()->weight('bold'),
                TextColumn::make('pedido.numero')->label('Pedido')->searchable()
                    ->url(fn (Incidencia $r) => PedidoResource::getUrl('view', ['record' => $r->pedido])),
                TextColumn::make('motivo')->label('Motivo')->formatStateUsing(fn (MotivoIncidencia $state) => $state->etiqueta('es')),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->state(fn (Incidencia $r) => $r->abierta() ? 'Abierta' : 'Resuelta · '.$r->resolucion?->etiqueta('es'))
                    ->color(fn (Incidencia $r) => $r->abierta() ? 'warning' : ($r->resolucion === ResolucionIncidencia::Rechazo ? 'gray' : 'success')),
                TextColumn::make('importe_reembolso')->label('Reembolso')->alignEnd()->placeholder('—')->formatStateUsing(fn (?int $state) => $state === null ? null : Formato::euros($state, 'es')),
                TextColumn::make('created_at')->label('Abierta el')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('estado')->label('Estado')->options(['abierta' => 'Abiertas', 'resuelta' => 'Resueltas']),
            ])
            ->recordActions([ViewAction::make()->label('Ver')]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Lo que cuenta el cliente')->columnSpan(2)->schema([
                TextEntry::make('motivo')->label('Motivo')->formatStateUsing(fn (MotivoIncidencia $state) => $state->etiqueta('es')),
                TextEntry::make('descripcion')->label('Descripción'),
                ViewEntry::make('fotos')->label('Fotos')->view('filament.incidencias.fotos'),
            ]),
            Section::make('Incidencia')->columnSpan(1)->schema([
                TextEntry::make('pedido.numero')->label('Pedido')->url(fn (Incidencia $r) => PedidoResource::getUrl('view', ['record' => $r->pedido]))
                    ->helperText(fn (Incidencia $r) => $r->pedido->estado->etiqueta().' · total '.Formato::euros($r->pedido->total, 'es')),
                TextEntry::make('created_at')->label('Abierta el')->dateTime('d/m/Y H:i'),
                TextEntry::make('estado')->label('Estado')->badge()
                    ->state(fn (Incidencia $r) => $r->abierta() ? 'Abierta' : 'Resuelta · '.$r->resolucion?->etiqueta('es'))
                    ->color(fn (Incidencia $r) => $r->abierta() ? 'warning' : 'success'),
                TextEntry::make('importe_reembolso')->label('Reembolso')->visible(fn (Incidencia $r) => $r->importe_reembolso !== null)
                    ->formatStateUsing(fn (?int $state) => Formato::euros((int) $state, 'es'))
                    ->helperText(fn (Incidencia $r) => $r->factura === null ? null : 'Rectificativa '.$r->factura->codigo),
                TextEntry::make('respuesta')->label('Respuesta al cliente')->visible(fn (Incidencia $r) => ! $r->abierta()),
                TextEntry::make('resuelta_en')->label('Resuelta')->visible(fn (Incidencia $r) => ! $r->abierta())
                    ->formatStateUsing(fn (Incidencia $r) => $r->resuelta_en?->timezone('Europe/Madrid')->format('d/m/Y H:i').' · '.($r->usuario->name ?? '—')),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListIncidencias::route('/'), 'view' => ViewIncidencia::route('/{record}')];
    }
}
