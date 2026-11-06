<?php

namespace App\Filament\Resources\RegistroErrors\Tables;

use App\Dominio\Sistema\Errores\EstadoError;
use App\Dominio\Sistema\Errores\NivelError;
use App\Models\RegistroError;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class RegistroErrorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('ultima_vez', 'desc')
            ->emptyStateHeading('No hay errores registrados')
            ->emptyStateDescription('Cuando el portal tenga un error, aparecerá aquí agrupado con sus repeticiones.')
            ->columns([
                TextColumn::make('nivel')
                    ->label('Nivel')
                    ->badge(),
                TextColumn::make('mensaje')
                    ->label('Error')
                    ->limit(90)
                    ->tooltip(fn (RegistroError $record): string => $record->mensaje)
                    ->description(fn (RegistroError $record): string => class_basename($record->clase).' · '.$record->archivo.':'.$record->linea)
                    ->searchable(['mensaje', 'clase', 'archivo'])
                    ->wrap(),
                TextColumn::make('ocurrencias')
                    ->label('Veces')
                    ->numeric(decimalPlaces: 0, thousandsSeparator: '.')
                    ->sortable(),
                TextColumn::make('ultima_vez')
                    ->label('Última vez')
                    ->dateTime('d/m/Y H:i')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(EstadoError::class)
                    ->default(EstadoError::Pendiente->value),
                SelectFilter::make('nivel')
                    ->label('Nivel')
                    ->options(NivelError::class),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
            ])
            ->toolbarActions([
                BulkAction::make('marcarResueltos')
                    ->label('Marcar como resueltos')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->requiresConfirmation()
                    ->modalDescription('Si alguno vuelve a ocurrir, se reabrirá solo.')
                    ->action(function (Collection $records): void {
                        $records->each(fn (RegistroError $r) => $r->marcarResuelto(auth()->user()));

                        Notification::make()->title('Errores marcados como resueltos')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }
}
