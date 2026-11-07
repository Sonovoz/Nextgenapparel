<?php

namespace App\Filament\Resources\Catalogo\Prendas\RelationManagers;

use App\Models\Talla;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/** Guía de tallas: medidas en cm en pantalla, en mm en la base de datos. */
class MedidasRelationManager extends RelationManager
{
    protected static string $relationship = 'medidas';

    protected static ?string $title = 'Guía de tallas';

    protected static ?string $modelLabel = 'medida';

    protected static ?string $pluralModelLabel = 'medidas';

    public function form(Schema $schema): Schema
    {
        $cm = fn (string $campo, string $etiqueta) => TextInput::make($campo)->label($etiqueta)
            ->numeric()->minValue(1)->maxValue(200)->step(0.1)->suffix('cm')
            ->formatStateUsing(fn ($state) => $state === null ? null : $state / 10)
            ->dehydrateStateUsing(fn ($state) => blank($state) ? null : (int) round(((float) $state) * 10));

        return $schema->columns(4)->components([
            Select::make('talla_id')->label('Talla')->required()->native(false)
                ->options(fn () => Talla::orderBy('orden')->pluck('nombre', 'id'))
                ->unique(table: 'medidas_talla', column: 'talla_id', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('prenda_id', $this->getOwnerRecord()->getKey())),
            $cm('largo_mm', 'Largo'),
            $cm('ancho_mm', 'Ancho (axila a axila)'),
            $cm('manga_mm', 'Manga'),
        ]);
    }

    public function table(Table $table): Table
    {
        $cm = fn ($state) => $state === null ? '—' : str_replace('.', ',', (string) ($state / 10)).' cm';

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('talla')->join('tallas', 'tallas.id', '=', 'medidas_talla.talla_id')->orderBy('tallas.orden')->select('medidas_talla.*'))
            ->columns([
                TextColumn::make('talla.nombre')->label('Talla'),
                TextColumn::make('largo_mm')->label('Largo')->formatStateUsing($cm),
                TextColumn::make('ancho_mm')->label('Ancho')->formatStateUsing($cm),
                TextColumn::make('manga_mm')->label('Manga')->formatStateUsing($cm),
            ])
            ->headerActions([CreateAction::make()->label('Añadir talla')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
