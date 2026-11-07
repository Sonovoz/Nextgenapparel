<?php

namespace App\Filament\Resources\Catalogo;

use App\Filament\Concerns\CamposTraducibles;
use App\Models\Familia;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Productos → Familias (Categorías de WooCommerce). */
class FamiliaResource extends RecursoCatalogo
{
    protected static ?string $model = Familia::class;

    protected static ?string $navigationLabel = 'Familias';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'familia';

    protected static ?string $pluralModelLabel = 'familias';

    protected static ?string $slug = 'familias';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            CamposTraducibles::texto('nombre', 'Nombre', obligatorio: true, slugDe: 'slug'),
            CamposTraducibles::urls(Familia::class, 120, reservadas: true),
            CamposTraducibles::texto('descripcion', 'Descripción', largo: true),
            Grid::make(3)->schema([
                TextInput::make('plazo_base_dias')->label('Plazo de producción (días laborables)')->numeric()->integer()->minValue(0)->maxValue(60)->default(2)->required(),
                TextInput::make('orden')->label('Orden')->numeric()->integer()->minValue(0)->default(0),
                Toggle::make('activa')->label('Activa')->default(true)->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->reorderable('orden')
            ->columns([
                TextColumn::make('nombre')->label('Nombre')->searchable(),
                TextColumn::make('prendas_count')->label('Prendas')->counts('prendas'),
                TextColumn::make('plazo_base_dias')->label('Plazo')->suffix(' días'),
                IconColumn::make('activa')->label('Activa')->boolean(),
            ])
            ->recordActions([static::accionEditar(), static::accionBorrar()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageFamilias::route('/')];
    }
}
