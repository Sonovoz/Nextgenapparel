<?php

namespace App\Filament\Resources\Catalogo;

use App\Dominio\Catalogo\Cara;
use App\Filament\Concerns\BusquedaTraducible;
use App\Filament\Concerns\CamposTraducibles;
use App\Models\ZonaImpresion;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Productos → Zonas de impresión. Medidas en cm en pantalla, en mm en la base de datos. */
class ZonaImpresionResource extends RecursoCatalogo
{
    protected static ?string $model = ZonaImpresion::class;

    protected static ?string $navigationLabel = 'Zonas de impresión';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewfinderCircle;

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'zona de impresión';

    protected static ?string $pluralModelLabel = 'zonas de impresión';

    protected static ?string $slug = 'zonas-impresion';

    public static function form(Schema $schema): Schema
    {
        $cm = fn (string $campo, string $etiqueta) => TextInput::make($campo)->label($etiqueta)
            ->numeric()->minValue(1)->maxValue(100)->step(0.1)->suffix('cm')->required()
            ->formatStateUsing(fn ($state) => $state === null ? null : $state / 10)
            ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 10));

        return $schema->columns(1)->components([
            CamposTraducibles::texto('nombre', 'Nombre', obligatorio: true),
            Grid::make(3)->schema([
                TextInput::make('clave')->label('Clave interna')->required()->alphaDash()->maxLength(40)->unique(ignoreRecord: true)->helperText('Sin espacios, p. ej. «frontal».'),
                Select::make('cara')->label('Cara')->options(Cara::class)->required()->native(false),
                Toggle::make('activa')->label('Activa')->default(true)->inline(false),
            ]),
            Grid::make(3)->schema([
                $cm('ancho_max_mm', 'Ancho máximo'),
                $cm('alto_max_mm', 'Alto máximo'),
                TextInput::make('orden')->label('Orden')->numeric()->integer()->minValue(0)->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->reorderable('orden')
            ->columns([
                TextColumn::make('nombre')->label('Zona')->searchable(query: BusquedaTraducible::en('nombre')),
                TextColumn::make('cara')->label('Cara')->badge(),
                TextColumn::make('medida')->label('Medida máxima')
                    ->state(fn (ZonaImpresion $r) => str_replace('.', ',', ($r->ancho_max_mm / 10).' × '.($r->alto_max_mm / 10)).' cm'),
                IconColumn::make('activa')->label('Activa')->boolean(),
            ])
            ->recordActions([static::accionEditar(), static::accionBorrar()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageZonasImpresion::route('/')];
    }
}
