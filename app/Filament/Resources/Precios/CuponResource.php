<?php

namespace App\Filament\Resources\Precios;

use App\Dominio\Precios\Efecto;
use App\Filament\Concerns\CampoDinero;
use App\Filament\Concerns\CampoPorcentaje;
use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Cupon;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Marketing → Cupones. */
class CuponResource extends RecursoPrecios
{
    protected static ?string $model = Cupon::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Marketing;

    protected static ?string $navigationLabel = 'Cupones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'cupón';

    protected static ?string $pluralModelLabel = 'cupones';

    protected static ?string $slug = 'cupones';

    public static function form(Schema $schema): Schema
    {
        $esPorcentaje = fn (Get $get) => in_array($get('tipo'), [Efecto::Porcentaje, Efecto::Porcentaje->value, null], true);

        return $schema->columns(3)->components([
            TextInput::make('codigo')->label('Código')->required()->maxLength(40)->rule('regex:/^[A-Za-z0-9_-]+$/')
                ->unique(ignoreRecord: true)->dehydrateStateUsing(fn ($state) => Cupon::normalizar((string) $state))
                ->helperText('Sin espacios. No distingue mayúsculas.'),
            Select::make('tipo')->label('Descuento')->options(Efecto::class)->required()->native(false)->live()->default(Efecto::Porcentaje),
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
            CampoDinero::make('minimo_importe', 'Pedido mínimo (sin IVA)')->nullable()->dehydrateStateUsing(fn ($state) => blank($state) ? null : CampoDinero::aCentimos($state)),
            TextInput::make('usos_maximos')->label('Usos máximos')->integer()->minValue(1)->nullable()->helperText('Vacío: sin límite.'),
            Toggle::make('un_uso_por_cliente')->label('Un uso por cliente')->default(true)->inline(false),
            DateTimePicker::make('desde')->label('Desde')->seconds(false),
            DateTimePicker::make('hasta')->label('Hasta')->seconds(false)->after('desde'),
            Toggle::make('activo')->label('Activo')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->description('El cupón se aplica después de las ofertas y solo a prendas sueltas: los packs ya tienen su precio especial.')
            ->columns([
                TextColumn::make('codigo')->label('Código')->fontFamily('mono')->searchable()->copyable(),
                TextColumn::make('valor')->label('Descuento')->formatStateUsing(fn ($state, Cupon $record) => $record->tipo === Efecto::Porcentaje ? CampoPorcentaje::formatear((int) $state) : CampoDinero::formatear((int) $state)),
                TextColumn::make('usos')->label('Usos')->formatStateUsing(fn ($state, Cupon $record) => $state.($record->usos_maximos ? ' / '.$record->usos_maximos : '')),
                TextColumn::make('hasta')->label('Caduca')->dateTime('d/m/Y H:i')->placeholder('Nunca'),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->recordActions([static::accionEditar(), static::accionBorrar()->modalDescription('Un cupón ya usado no se puede borrar: desactívalo.')]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCupon::route('/')];
    }
}
