<?php

namespace App\Filament\Acciones;

use App\Dominio\Catalogo\OptimizarFoto;
use App\Dominio\Sistema\Actividad\Actividad;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

/**
 * «Optimizar todas las fotos del catálogo» (D210): en el momento, sin esperar a la tarea programada.
 * Para a los 20 segundos (tiempo máximo de una petición) y dice cuántas quedan.
 */
final class OptimizarFotosAction
{
    public static function make(): Action
    {
        return Action::make('optimizar_fotos')->label('Optimizar todas las fotos del catálogo')->color('gray')
            ->icon(Heroicon::OutlinedPhoto)
            ->requiresConfirmation()
            ->modalDescription('Pasa a WebP y al lado mayor de Ajustes → General todas las fotos de producto que aún no lo están. Las zonas de impresión no se mueven.')
            ->action(fn () => self::ejecutar());
    }

    public static function ejecutar(): void
    {
        if (! extension_loaded('imagick')) {
            Notification::make()->title('Falta Imagick en el servidor: no se pueden optimizar las fotos')->danger()->send();

            return;
        }
        ['fotos' => $fotos, 'ahorro' => $ahorro, 'quedan' => $quedan] = OptimizarFoto::todas(segundos: 20);
        if ($fotos > 0) {
            Actividad::apuntar('ajustes', "{$fotos} fotos del catálogo optimizadas", propiedades: ['ahorro_bytes' => $ahorro]);
        }
        Notification::make()->success()
            ->title($fotos === 0 && $quedan === 0 ? 'Todas las fotos ya estaban optimizadas' : "{$fotos} fotos optimizadas: ".Number::fileSize($ahorro, 1).' menos')
            ->body($quedan > 0 ? "Quedan {$quedan}: vuelve a pulsar el botón (o se harán solas en la próxima hora)." : null)
            ->send();
    }
}
