<?php

namespace App\Filament\Resources\Catalogo\Prendas\RelationManagers;

use App\Filament\Concerns\CampoDinero;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Zonas en las que se puede imprimir esta prenda y su precio por prenda. */
class ZonasRelationManager extends RelationManager
{
    protected static string $relationship = 'zonas';

    protected static ?string $title = 'Zonas de impresión';

    protected static ?string $modelLabel = 'zona';

    protected static ?string $pluralModelLabel = 'zonas';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            CampoDinero::make('precio', 'Precio de impresión sin IVA')->required(),
            CampoDinero::make('coste', 'Coste de impresión')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Sin zonas')
            ->emptyStateDescription('Añade las zonas en las que se puede imprimir esta prenda.')
            ->columns([
                TextColumn::make('nombre')->label('Zona'),
                TextColumn::make('cara')->label('Cara')->badge(),
                TextColumn::make('precio')->label('Precio sin IVA')->formatStateUsing(fn ($state) => CampoDinero::formatear((int) $state)),
                TextColumn::make('coste')->label('Coste')->formatStateUsing(fn ($state) => CampoDinero::formatear((int) $state)),
            ])
            ->headerActions([
                AttachAction::make()->label('Añadir zona')->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('activa', true)->orderBy('orden'))
                    ->recordTitle(fn ($record) => $record->nombre)
                    ->schema(fn (AttachAction $action) => [
                        $action->getRecordSelect()->label('Zona'),
                        CampoDinero::make('precio', 'Precio de impresión sin IVA')->required(),
                        CampoDinero::make('coste', 'Coste de impresión')->required(),
                    ]),
            ])
            ->recordActions([EditAction::make(), DetachAction::make()->label('Quitar')]);
    }
}
