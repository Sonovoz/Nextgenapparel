<?php

namespace App\Filament\Resources\GruposCliente;

use App\Filament\Navegacion\GrupoNavegacion;
use App\Filament\Resources\GruposCliente\Pages\ManageGruposCliente;
use App\Models\Cliente;
use App\Models\GrupoCliente;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Clientes → Grupos (v2, D192): tarifas por grupo con un descuento %; se asignan en la ficha del cliente. */
class GrupoClienteResource extends Resource
{
    protected static ?string $model = GrupoCliente::class;

    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Clientes;

    protected static ?string $navigationLabel = 'Grupos y tarifas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'grupo';

    protected static ?string $pluralModelLabel = 'grupos';

    protected static ?string $slug = 'grupos-cliente';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre.es')->label('Nombre (es)')->required()->maxLength(60),
            TextInput::make('nombre.en')->label('Nombre (en)')->required()->maxLength(60),
            TextInput::make('descuento')->label('Descuento')->suffix('%')->required()->numeric()->minValue(0.01)->maxValue(90)->step(0.01)
                ->formatStateUsing(fn (?int $state) => $state === null ? null : $state / 100)
                ->dehydrateStateUsing(fn ($state) => (int) round((float) str_replace(',', '.', (string) $state) * 100))
                ->helperText('Sobre prendas e impresión (no packs). Compite con las ofertas: se aplica el que más ahorra.'),
            Toggle::make('activo')->label('Activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->label('Grupo')->weight('bold'),
                TextColumn::make('descuento')->label('Descuento')->formatStateUsing(fn (int $state) => '−'.rtrim(rtrim(number_format($state / 100, 2, ',', ''), '0'), ',').' %'),
                TextColumn::make('clientes')->label('Clientes')->state(fn (GrupoCliente $r) => Cliente::where('grupo_cliente_id', $r->id)->count()),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGruposCliente::route('/')];
    }
}
