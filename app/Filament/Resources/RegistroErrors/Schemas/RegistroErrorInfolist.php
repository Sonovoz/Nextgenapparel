<?php

namespace App\Filament\Resources\RegistroErrors\Schemas;

use App\Models\RegistroError;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RegistroErrorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Error')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('mensaje')
                            ->label('Mensaje')
                            ->columnSpanFull(),
                        TextEntry::make('nivel')->label('Nivel')->badge(),
                        TextEntry::make('estado')->label('Estado')->badge(),
                        TextEntry::make('ocurrencias')->label('Veces')->numeric(decimalPlaces: 0, thousandsSeparator: '.'),
                        TextEntry::make('clase')->label('Tipo')->fontFamily('mono')->columnSpan(2),
                        TextEntry::make('archivo')
                            ->label('Dónde')
                            ->fontFamily('mono')
                            ->formatStateUsing(fn (RegistroError $record): string => $record->archivo.':'.$record->linea),
                    ]),

                Section::make('Cuándo y dónde')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('primera_vez')->label('Primera vez')->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('ultima_vez')->label('Última vez')->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('alertado_en')->label('Último aviso por correo')->dateTime('d/m/Y H:i:s')->placeholder('Sin aviso'),
                        TextEntry::make('url')->label('Página')->placeholder('—')->columnSpan(2),
                        TextEntry::make('usuario.name')->label('Usuario')->placeholder('Visitante o sistema'),
                        TextEntry::make('resueltoPor.name')
                            ->label('Resuelto por')
                            ->formatStateUsing(fn (RegistroError $record, ?string $state): ?string => $state ? $state.' · '.$record->resuelto_en?->format('d/m/Y H:i') : null)
                            ->placeholder('—'),
                    ]),

                Section::make('Traza')
                    ->description('Los primeros pasos del código hasta el error, para quien lo corrija.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('traza')
                            ->hiddenLabel()
                            ->fontFamily('mono')
                            ->listWithLineBreaks()
                            ->placeholder('Sin traza'),
                    ]),
            ]);
    }
}
