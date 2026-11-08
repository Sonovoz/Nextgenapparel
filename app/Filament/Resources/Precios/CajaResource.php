<?php

namespace App\Filament\Resources\Precios;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Models\Caja;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Ajustes → Cajas. */
class CajaResource extends RecursoPrecios
{
    protected static ?string $model = Caja::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Ajustes;

    protected static ?string $navigationLabel = 'Cajas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'caja';

    protected static ?string $pluralModelLabel = 'cajas';

    protected static ?string $slug = 'cajas';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(100),
            TextInput::make('capacidad_prendas')->label('Prendas que caben')->integer()->minValue(1)->required(),
            TextInput::make('peso_max_gramos')->label('Peso máximo')->integer()->minValue(1)->suffix('g')->required(),
            Toggle::make('activa')->label('Activa')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->description('Para las zonas que cobran por cajas: se usa la caja activa más grande.')
            ->columns([
                TextColumn::make('nombre')->label('Caja'),
                TextColumn::make('capacidad_prendas')->label('Prendas'),
                TextColumn::make('peso_max_gramos')->label('Peso máximo')->suffix(' g'),
                IconColumn::make('activa')->label('Activa')->boolean(),
            ])
            ->recordActions([static::accionEditar(), static::accionBorrar()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCaja::route('/')];
    }
}
