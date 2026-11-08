<?php

namespace App\Filament\Resources\Clientes\RelationManagers;

use App\Filament\Resources\Disenos\DisenoResource;
use App\Models\Diseno;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Diseños del cliente (paso 5.4): solo lectura; el detalle está en la Biblioteca de diseños. */
class DisenosRelationManager extends RelationManager
{
    protected static string $relationship = 'disenos';

    protected static ?string $title = 'Diseños';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('vista')->label('')->state(fn (Diseno $r) => route('backend.disenos.miniatura', $r))->imageHeight(48)
                    ->extraImgAttributes(['style' => 'object-fit: contain; background: repeating-conic-gradient(#e5e7eb 0 25%, #fff 0 50%) 0 0 / 10px 10px;']),
                TextColumn::make('nombre_original')->label('Archivo'),
                TextColumn::make('medidas')->label('Se imprime')->state(fn (Diseno $r) => $r->util_ancho_px.' × '.$r->util_alto_px.' px'),
                TextColumn::make('fondo')->label('Fondo')->badge()->formatStateUsing(fn (string $state) => DisenoResource::FONDOS[$state] ?? $state),
                TextColumn::make('created_at')->label('Subido')->dateTime('d/m/Y H:i'),
            ])
            ->recordActions([
                Action::make('ver')->label('Ver')->url(fn (Diseno $r) => DisenoResource::getUrl('view', ['record' => $r])),
            ]);
    }
}
