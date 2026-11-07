<?php

namespace App\Filament\Resources\Catalogo;

use App\Dominio\Catalogo\Origen;
use App\Models\Proveedor;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Productos → Proveedores de prendas lisas. La conexión por API llegará con su documentación (D13). */
class ProveedorResource extends RecursoCatalogo
{
    protected static ?string $model = Proveedor::class;

    protected static ?string $navigationLabel = 'Proveedores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'proveedor';

    protected static ?string $pluralModelLabel = 'proveedores';

    protected static ?string $slug = 'proveedores';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(255),
            Select::make('tipo_conector')->label('Catálogo')->options(Origen::class)->default(Origen::Manual)->required()->native(false)
                ->helperText('La conexión por API se configurará cuando tengamos la documentación del proveedor.'),
            Toggle::make('activo')->label('Activo')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->label('Nombre')->searchable(),
                TextColumn::make('tipo_conector')->label('Catálogo')->badge(),
                TextColumn::make('prendas_count')->label('Prendas')->counts('prendas'),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->recordActions([static::accionEditar(), static::accionBorrar()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageProveedores::route('/')];
    }
}
