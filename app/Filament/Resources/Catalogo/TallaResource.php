<?php

namespace App\Filament\Resources\Catalogo;

use App\Models\Talla;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Productos → Tallas (atributo «talla» de WooCommerce). */
class TallaResource extends RecursoCatalogo
{
    protected static ?string $model = Talla::class;

    protected static ?string $navigationLabel = 'Tallas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'talla';

    protected static ?string $pluralModelLabel = 'tallas';

    protected static ?string $slug = 'tallas';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('nombre')->label('Talla')->required()->maxLength(10)->unique(ignoreRecord: true)->helperText('Por ejemplo: XS, S, M, L, XL, XXL'),
            TextInput::make('orden')->label('Orden')->numeric()->integer()->minValue(0)->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->reorderable('orden')
            ->columns([TextColumn::make('nombre')->label('Talla')])
            ->recordActions([static::accionEditar(), static::accionBorrar()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageTallas::route('/')];
    }
}
