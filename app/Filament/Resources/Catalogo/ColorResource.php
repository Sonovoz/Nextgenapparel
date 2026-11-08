<?php

namespace App\Filament\Resources\Catalogo;

use App\Filament\Concerns\BusquedaTraducible;
use App\Filament\Concerns\CamposTraducibles;
use App\Models\Color;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Productos → Colores (atributo «color» de WooCommerce). */
class ColorResource extends RecursoCatalogo
{
    protected static ?string $model = Color::class;

    protected static ?string $navigationLabel = 'Colores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'color';

    protected static ?string $pluralModelLabel = 'colores';

    protected static ?string $slug = 'colores';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            CamposTraducibles::texto('nombre', 'Nombre', obligatorio: true),
            Grid::make(3)->schema([
                ColorPicker::make('codigo_hex')->label('Color')->required()->regex('/^#[0-9a-fA-F]{6}$/'),
                TextInput::make('orden')->label('Orden')->numeric()->integer()->minValue(0)->default(0),
                Toggle::make('activo')->label('Activo')->default(true)->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->reorderable('orden')
            ->columns([
                ColorColumn::make('codigo_hex')->label(''),
                TextColumn::make('nombre')->label('Nombre')->searchable(query: BusquedaTraducible::en('nombre')),
                TextColumn::make('codigo_hex')->label('Código')->fontFamily('mono'),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->recordActions([static::accionEditar(), static::accionBorrar()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageColores::route('/')];
    }
}
