<?php

namespace App\Filament\Pages\Produccion;

use App\Dominio\Pedidos\EstadoPedido;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Pedido;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Producción → Órdenes de trabajo (paso 6.5b). Los pedidos que hay que producir, con su
 * orden de trabajo en PDF y el paquete ZIP. Sin datos del cliente: el rol Producción no ve
 * la sección de Pedidos.
 */
class OrdenesTrabajo extends Page implements HasTable
{
    use InteractsWithTable;

    /** Pagados y aún sin enviar. */
    public const ESTADOS = [EstadoPedido::Pagado, EstadoPedido::DisenoEnRevision, EstadoPedido::RequiereCambios, EstadoPedido::EnProduccion];

    protected string $view = 'filament.pages.produccion-tabla';

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Produccion;

    protected static ?string $navigationLabel = 'Órdenes de trabajo';

    protected static ?string $title = 'Órdenes de trabajo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'ordenes-trabajo';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(GrupoNavegacion::Produccion->permiso());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Pedido::query()
                ->whereIn('estado', array_map(fn (EstadoPedido $e) => $e->value, self::ESTADOS))
                ->withSum('lineas', 'unidades'))
            ->defaultSort('fecha_estimada_entrega')
            ->emptyStateHeading('No hay pedidos que producir')
            ->columns([
                TextColumn::make('numero')->label('Pedido')->searchable()->weight('bold'),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y')->sortable(),
                TextColumn::make('lineas_sum_unidades')->label('Prendas')->alignEnd(),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->formatStateUsing(fn (EstadoPedido $state) => $state->etiqueta())->color(fn (EstadoPedido $state) => $state->color()),
                TextColumn::make('fecha_estimada_entrega')->label('Entrega estimada')->date('d/m/Y')->sortable()->placeholder('—'),
                IconColumn::make('maquetas')->label('Maquetas')->boolean()
                    ->state(fn (Pedido $r) => $r->lineas()->whereHas('maquetas')->exists()),
            ])
            ->filters([
                SelectFilter::make('estado')->label('Estado')->multiple()
                    ->options(collect(self::ESTADOS)->mapWithKeys(fn (EstadoPedido $e) => [$e->value => $e->etiqueta()])->all()),
            ])
            ->recordActions([
                Action::make('orden')->label('Orden de trabajo')->icon(Heroicon::OutlinedDocumentText)
                    ->url(fn (Pedido $r) => route('backend.produccion.orden', $r), shouldOpenInNewTab: true),
                Action::make('paquete')->label('Paquete ZIP')->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (Pedido $r) => route('backend.produccion.paquete', $r)),
            ]);
    }
}
