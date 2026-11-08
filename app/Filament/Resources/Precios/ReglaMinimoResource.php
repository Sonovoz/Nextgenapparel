<?php

namespace App\Filament\Resources\Precios;

use App\Dominio\Precios\TipoMinimo;
use App\Filament\Concerns\CampoDinero;
use App\Filament\Concerns\CamposTraducibles;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\ReglaMinimo;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Marketing → Pedido mínimo. */
class ReglaMinimoResource extends RecursoPrecios
{
    protected static ?string $model = ReglaMinimo::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Marketing;

    protected static ?string $navigationLabel = 'Pedido mínimo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'regla de pedido mínimo';

    protected static ?string $pluralModelLabel = 'reglas de pedido mínimo';

    protected static ?string $slug = 'pedido-minimo';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            CamposTraducibles::texto('nombre', 'Nombre', obligatorio: true),
            Grid::make(3)->schema([
                Select::make('tipo')->label('Tipo')->options(TipoMinimo::class)->required()->native(false)->live(),
                // Unidades, o un importe en euros (se guarda en céntimos) si es importe mínimo.
                TextInput::make('valor')->label(fn (Get $get) => static::esImporte($get) ? 'Importe mínimo (sin IVA)' : 'Unidades')
                    ->required()->inputMode('decimal')
                    ->prefix(fn (Get $get) => static::esImporte($get) ? '€' : null)
                    ->rule(fn (Get $get) => static::esImporte($get) ? 'regex:/^\d{1,6}([.,]\d{1,2})?$/' : 'regex:/^[1-9]\d{0,4}$/')
                    ->validationMessages(['regex' => 'Escribe unidades enteras o un importe en euros, por ejemplo 200 o 199,90.'])
                    ->formatStateUsing(fn ($state, ?ReglaMinimo $record) => $state === null ? null
                        : ($record?->tipo === TipoMinimo::ImporteMinimo ? number_format($state / 100, 2, ',', '') : (string) $state))
                    ->dehydrateStateUsing(fn ($state, Get $get) => static::esImporte($get) ? CampoDinero::aCentimos($state) : (int) $state),
                TextInput::make('prioridad')->label('Prioridad')->integer()->default(0),
            ]),
            ...static::camposAmbito(),
            ...static::camposVigencia(),
            Toggle::make('activa')->label('Activa')->default(true),
        ]);
    }

    private static function esImporte(Get $get): bool
    {
        $tipo = $get('tipo');

        return ($tipo instanceof TipoMinimo ? $tipo : TipoMinimo::tryFrom((string) $tipo)) === TipoMinimo::ImporteMinimo;
    }

    public static function table(Table $table): Table
    {
        return $table->description('Si un pedido no cumple un mínimo, no se puede confirmar y se le explica qué le falta. Un pack cumple solo los mínimos generales.')
            ->columns([
                TextColumn::make('nombre')->label('Nombre'),
                TextColumn::make('tipo')->label('Tipo')->badge(),
                TextColumn::make('valor')->label('Valor')->formatStateUsing(fn ($state, ReglaMinimo $record) => $record->tipo === TipoMinimo::ImporteMinimo ? CampoDinero::formatear((int) $state) : $state.' uds.'),
                TextColumn::make('ambito')->label('Ámbito')->badge(),
                IconColumn::make('activa')->label('Activa')->boolean(),
            ])
            ->recordActions([static::accionEditar(), static::accionBorrar()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageReglaMinimo::route('/')];
    }
}
