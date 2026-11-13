<?php

namespace App\Filament\Resources\Precios;

use App\Dominio\Precios\MetodoEnvio;
use App\Dominio\Precios\Motor\CalculoEnvio;
use App\Filament\Concerns\CampoDinero;
use App\Filament\Concerns\CamposTraducibles;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\ZonaEnvio;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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

/** Ajustes → Envío. */
class ZonaEnvioResource extends RecursoPrecios
{
    protected static ?string $model = ZonaEnvio::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Ajustes;

    protected static ?string $navigationLabel = 'Envío';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'zona de envío';

    protected static ?string $pluralModelLabel = 'zonas de envío';

    protected static ?string $slug = 'envio';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            CamposTraducibles::texto('nombre', 'Nombre', obligatorio: true),
            Grid::make(3)->schema([
                TagsInput::make('paises')->label('Países (código ISO)')->required()->placeholder('ES')
                    ->dehydrateStateUsing(fn ($state) => array_values(array_map(fn ($p) => strtoupper(trim($p)), $state ?? []))),
                TagsInput::make('prefijos_cp')->label('Prefijos de código postal')->placeholder('07')
                    ->helperText('Vacío: todo el país. La zona con prefijos gana a la general.'),
                TextInput::make('plazo_dias')->label('Plazo del transportista')->integer()->minValue(0)->maxValue(60)->suffix('días')->required()->default(2),
            ]),
            Grid::make(3)->schema([
                Select::make('metodo')->label('Método')->options(MetodoEnvio::class)->required()->native(false)->live()->default(MetodoEnvio::PorKilo),
                CampoDinero::make('gratis_desde', 'Gratis desde (sin IVA)')->nullable()->dehydrateStateUsing(fn ($state) => blank($state) ? null : CampoDinero::aCentimos($state)),
                TextInput::make('prioridad')->label('Prioridad')->integer()->default(0),
            ]),
            CampoDinero::make('precio_caja', 'Precio por caja')->visible(fn (Get $get) => static::metodo($get) === MetodoEnvio::PorCaja)
                ->required(fn (Get $get) => static::metodo($get) === MetodoEnvio::PorCaja),
            Repeater::make('tarifas')->label('Tarifas por peso')->relationship('tarifas')
                ->visible(fn (Get $get) => static::metodo($get) === MetodoEnvio::PorKilo)
                ->orderColumn(false)->columns(2)->defaultItems(1)
                ->schema([
                    TextInput::make('hasta_gramos')->label('Hasta')->integer()->minValue(1)->suffix('g')->required()->distinct(),
                    CampoDinero::make('precio', 'Precio')->required(),
                ]),
            CampoDinero::make('precio_kg_extra', 'Por cada kilo más del último tramo')->nullable()
                ->visible(fn (Get $get) => static::metodo($get) === MetodoEnvio::PorKilo)
                ->dehydrateStateUsing(fn ($state) => blank($state) ? null : CampoDinero::aCentimos($state)),
            Toggle::make('activa')->label('Activa')->default(true),
        ]);
    }

    private static function metodo(Get $get): ?MetodoEnvio
    {
        $valor = $get('metodo');

        return $valor instanceof MetodoEnvio ? $valor : MetodoEnvio::tryFrom((string) $valor);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('prioridad', 'desc')
            ->description('El envío lleva el IVA de la clase por defecto. Solo se envía a los destinos que tengan zona.')
            ->columns([
                TextColumn::make('nombre')->label('Zona'),
                TextColumn::make('paises')->label('Países')->badge(),
                TextColumn::make('prefijos_cp')->label('Prefijos CP')->badge()->placeholder('Todo el país'),
                TextColumn::make('metodo')->label('Método')->badge(),
                TextColumn::make('gratis_desde')->label('Gratis desde')->formatStateUsing(fn ($state) => CampoDinero::formatear($state === null ? null : (int) $state))->placeholder('—'),
                TextColumn::make('plazo_dias')->label('Plazo')->suffix(' días'),
                IconColumn::make('activa')->label('Activa')->boolean(),
                // D225: lo que le falta a la zona para poder cobrar envíos.
                TextColumn::make('estado')->label('Estado')->badge()->wrap()
                    ->state(fn (ZonaEnvio $record) => CalculoEnvio::problemas($record) === [] ? 'Lista' : implode(' ', CalculoEnvio::problemas($record)))
                    ->color(fn (ZonaEnvio $record) => CalculoEnvio::problemas($record) === [] ? 'success' : 'danger'),
            ])
            ->recordActions([static::accionEditar(), static::accionBorrar()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageZonaEnvio::route('/')];
    }
}
