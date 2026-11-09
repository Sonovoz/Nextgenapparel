<?php

namespace App\Filament\Resources\Presupuestos;

use App\Dominio\Precios\Formato;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Filament\Resources\Presupuestos\Pages\ListPresupuestos;
use App\Http\Controllers\PresupuestoController;
use App\Models\Presupuesto;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Pedidos → Presupuestos (D187): los que piden los clientes desde el carrito, con su PDF. Solo lectura. */
class PresupuestoResource extends Resource
{
    protected static ?string $model = Presupuesto::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Pedidos;

    protected static ?string $navigationLabel = 'Presupuestos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'presupuesto';

    protected static ?string $pluralModelLabel = 'presupuestos';

    protected static ?string $slug = 'presupuestos';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Todavía no hay presupuestos')
            ->emptyStateDescription('Los piden los clientes desde su carrito.')
            ->columns([
                TextColumn::make('numero')->label('Presupuesto')->searchable()->weight('bold'),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('cliente.nombre')->label('Cliente')->placeholder('Sin cuenta'),
                TextColumn::make('total')->label('Total')->alignEnd()->formatStateUsing(fn (int $state) => Formato::euros($state, 'es')),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->state(fn (Presupuesto $r) => $r->convertido_en !== null ? 'Pasado al carrito' : ($r->vigente() ? 'Vigente hasta '.$r->valido_hasta->format('d/m/Y') : 'Caducado'))
                    ->color(fn (Presupuesto $r) => $r->convertido_en !== null ? 'success' : ($r->vigente() ? 'info' : 'gray')),
            ])
            ->recordActions([
                Action::make('pdf')->label('PDF')->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (Presupuesto $r) => PresupuestoController::url('pdf', $r, 'es'), shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPresupuestos::route('/')];
    }
}
