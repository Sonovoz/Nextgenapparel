<?php

namespace App\Filament\Resources\Catalogo\Prendas\RelationManagers;

use App\Dominio\Catalogo\TipoFoto;
use App\Dominio\Catalogo\ZonasPorDefecto;
use App\Filament\Concerns\CampoDinero;
use App\Models\Foto;
use App\Models\FotoZona;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Zonas en las que se puede imprimir esta prenda. El precio es el de la zona, igual para todas las prendas (D256). */
class ZonasRelationManager extends RelationManager
{
    protected static string $relationship = 'zonas';

    protected static ?string $title = 'Zonas de impresión';

    protected static ?string $modelLabel = 'zona';

    protected static ?string $pluralModelLabel = 'zonas';

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Sin zonas')
            ->emptyStateDescription('Añade las zonas en las que se puede imprimir esta prenda. Su precio se pone en Productos → Zonas de impresión.')
            ->columns([
                TextColumn::make('nombre')->label('Zona'),
                TextColumn::make('cara')->label('Cara')->badge(),
                TextColumn::make('precio')->label('Precio sin IVA')->formatStateUsing(fn ($state) => CampoDinero::formatear((int) $state)),
                TextColumn::make('coste')->label('Coste')->formatStateUsing(fn ($state) => CampoDinero::formatear((int) $state)),
                TextColumn::make('medida')->label('Medida máxima')
                    ->state(fn ($record) => str_replace('.', ',', ($record->ancho_max_mm / 10).' × '.($record->alto_max_mm / 10)).' cm'),
            ])
            ->headerActions([
                AttachAction::make()->label('Añadir zona')->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('activa', true)->orderBy('orden'))
                    ->recordTitle(fn ($record) => $record->nombre.' · '.CampoDinero::formatear((int) $record->precio))
                    // Las maquetas ya subidas reciben la zona en su posición de partida.
                    ->after(fn () => $this->getOwnerRecord()->fotos()->where('tipo', TipoFoto::Maqueta->value)->get()
                        ->each(fn (Foto $foto) => app(ZonasPorDefecto::class)($foto))),
            ])
            ->recordActions([DetachAction::make()->label('Quitar')
                // Fuera también de las maquetas: el visor no debe ofrecer una zona sin precio.
                ->after(fn ($record) => FotoZona::where('zona_impresion_id', $record->id)
                    ->whereIn('foto_id', $this->getOwnerRecord()->fotos()->select('id'))->delete())]);
    }
}
