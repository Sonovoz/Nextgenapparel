<?php

namespace App\Filament\Resources\RegistroErrors\Pages;

use App\Dominio\Sistema\Errores\ErrorDePrueba;
use App\Filament\Resources\RegistroErrors\RegistroErrorResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListRegistroErrors extends ListRecords
{
    protected static string $resource = RegistroErrorResource::class;

    public function getTitle(): string
    {
        return 'Log de errores';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('errorDePrueba')
                ->label('Provocar error de prueba')
                ->icon(Heroicon::OutlinedBeaker)
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()?->esSuperadministrador())
                ->requiresConfirmation()
                ->modalHeading('Provocar un error de prueba')
                ->modalDescription('Se registrará un error crítico de prueba y se enviará la alerta por correo, como si fuera real. Sirve para comprobar que el sistema avisa.')
                ->modalSubmitActionLabel('Provocar error')
                ->action(function (): void {
                    // report() lo envía por el mismo camino que un error real.
                    report(new ErrorDePrueba('Error de prueba provocado por '.auth()->user()->name.' desde el backend.'));

                    Notification::make()
                        ->title('Error de prueba registrado')
                        ->body(filled(config('errores.correo_alertas'))
                            ? 'Se ha enviado la alerta a '.config('errores.correo_alertas').'.'
                            : 'No hay correo de alertas configurado (ALERTAS_EMAIL en el .env).')
                        ->success()
                        ->send();
                }),
        ];
    }
}
