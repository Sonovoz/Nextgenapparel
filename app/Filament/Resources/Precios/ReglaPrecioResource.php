<?php

namespace App\Filament\Resources\Precios;

use App\Dominio\Precios\Ambito;
use App\Dominio\Precios\AplicaA;
use App\Dominio\Precios\Efecto;
use App\Dominio\Precios\TipoRegla;
use App\Filament\Concerns\CampoDinero;
use App\Filament\Concerns\CampoPorcentaje;
use App\Filament\Concerns\CamposTraducibles;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Familia;
use App\Models\Prenda;
use App\Models\ReglaPrecio;
use BackedEnum;
use Filament\Forms\Components\Repeater;
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

/** Marketing → Ofertas. */
class ReglaPrecioResource extends RecursoPrecios
{
    protected static ?string $model = ReglaPrecio::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Marketing;

    protected static ?string $navigationLabel = 'Ofertas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'oferta';

    protected static ?string $pluralModelLabel = 'ofertas';

    protected static ?string $slug = 'ofertas';

    public static function form(Schema $schema): Schema
    {
        $esCombinada = fn (Get $get) => in_array($get('tipo'), [TipoRegla::Combinada, TipoRegla::Combinada->value], true);
        $esPorcentaje = fn (Get $get) => in_array($get('efecto'), [Efecto::Porcentaje, Efecto::Porcentaje->value, null], true);

        return $schema->columns(1)->components([
            CamposTraducibles::texto('nombre', 'Nombre', obligatorio: true),
            Grid::make(3)->schema([
                Select::make('tipo')->label('Tipo')->options(TipoRegla::class)->required()->native(false)->live()->default(TipoRegla::TramoCantidad),
                Select::make('efecto')->label('Descuento')->options(Efecto::class)->required()->native(false)->live()->default(Efecto::Porcentaje),
                TextInput::make('valor')->label(fn (Get $get) => $esPorcentaje($get) ? 'Porcentaje' : 'Importe')
                    ->required()->inputMode('decimal')->rule('regex:/^\d{1,6}([.,]\d{1,2})?$/')
                    ->rule(fn (Get $get) => function (string $atributo, $valor, \Closure $fallo) use ($esPorcentaje, $get): void {
                        if ($esPorcentaje($get) && (float) str_replace(',', '.', (string) $valor) > 100) {
                            $fallo('Un porcentaje no puede pasar del 100 %.');
                        }
                    })
                    ->suffix(fn (Get $get) => $esPorcentaje($get) ? '%' : '€')
                    ->formatStateUsing(fn ($state) => $state === null ? null : str_replace('.', ',', (string) ($state / 100)))
                    ->dehydrateStateUsing(fn ($state) => (int) round(((float) str_replace(',', '.', (string) $state)) * 100)),
            ]),
            Grid::make(2)->schema([
                Select::make('aplica_a')->label('Se descuenta de')->options(AplicaA::class)->required()->native(false)->default(AplicaA::Todo),
                TextInput::make('prioridad')->label('Prioridad (si empatan)')->integer()->default(0),
            ]),
            TextInput::make('condiciones.minimo_unidades')->label('Desde cuántas prendas')->integer()->minValue(1)
                ->required(fn (Get $get) => ! $esCombinada($get))->visible(fn (Get $get) => ! $esCombinada($get)),
            Repeater::make('condiciones.grupos')->label('Condiciones (se deben cumplir todas)')
                ->visible($esCombinada)->required($esCombinada)->minItems(2)->defaultItems(2)->columns(3)->addActionLabel('Añadir condición')
                ->schema([
                    Select::make('ambito')->label('De')->options([Ambito::Familia->value => 'Familias', Ambito::Prenda->value => 'Prendas'])->required()->live()->native(false)->default(Ambito::Familia->value),
                    Select::make('ids')->label('Cuáles')->multiple()->required()->native(false)
                        ->options(fn (Get $get) => $get('ambito') === Ambito::Prenda->value
                            ? Prenda::orderBy('id')->get()->pluck('nombre', 'id')
                            : Familia::orderBy('orden')->get()->pluck('nombre', 'id'))
                        ->dehydrateStateUsing(fn ($state) => array_map('intval', $state ?? [])),
                    TextInput::make('minimo')->label('Al menos')->integer()->minValue(1)->required()->suffix('prendas')
                        ->dehydrateStateUsing(fn ($state) => (int) $state),
                ]),
            ...static::camposAmbito('La oferta se aplica a'),
            ...static::camposVigencia(),
            Toggle::make('activa')->label('Activa')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->description('Los tramos y las ofertas combinadas no se suman: a cada pedido se le aplica la que más ahorra. Los packs no entran en ofertas.')
            ->columns([
                TextColumn::make('nombre')->label('Oferta'),
                TextColumn::make('tipo')->label('Tipo')->badge(),
                TextColumn::make('valor')->label('Descuento')->formatStateUsing(fn ($state, ReglaPrecio $record) => $record->efecto === Efecto::Porcentaje ? CampoPorcentaje::formatear((int) $state) : CampoDinero::formatear((int) $state)),
                TextColumn::make('aplica_a')->label('Sobre')->badge(),
                TextColumn::make('hasta')->label('Hasta')->dateTime('d/m/Y')->placeholder('Sin fin'),
                IconColumn::make('activa')->label('Activa')->boolean(),
            ])
            ->recordActions([static::accionEditar(), static::accionBorrar()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageReglaPrecio::route('/')];
    }
}
