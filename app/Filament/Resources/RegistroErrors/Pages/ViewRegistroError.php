<?php

namespace App\Filament\Resources\RegistroErrors\Pages;

use App\Dominio\Sistema\Errores\EstadoError;
use App\Filament\Resources\RegistroErrors\RegistroErrorResource;
use App\Models\RegistroError;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewRegistroError extends ViewRecord
{
    protected static string $resource = RegistroErrorResource::class;

    public function getTitle(): string
    {
        return 'Detalle del error';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('marcarResuelto')
                ->label('Marcar como resuelto')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (RegistroError $record): bool => $record->estado === EstadoError::Pendiente)
                ->requiresConfirmation()
                ->modalDescription('Si vuelve a ocurrir, se reabrirá solo y volverá a avisar.')
                ->action(function (RegistroError $record): void {
                    $record->marcarResuelto(auth()->user());
                    Notification::make()->title('Error marcado como resuelto')->success()->send();
                }),
            Action::make('reabrir')
                ->label('Reabrir')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (RegistroError $record): bool => $record->estado === EstadoError::Resuelto)
                ->action(function (RegistroError $record): void {
                    $record->reabrir();
                    Notification::make()->title('Error reabierto')->send();
                }),
        ];
    }
}
