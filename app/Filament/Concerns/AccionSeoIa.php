<?php

namespace App\Filament\Concerns;

use App\Dominio\Asistente\IaNoDisponible;
use App\Dominio\Asistente\RedactorSeo;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * «Proponer SEO con IA» (v2, D195) para las fichas con campos SEO: rellena el formulario con la propuesta (y la
 * traducción al inglés de lo que falte); nada se guarda hasta que alguien lo revisa y pulsa «Guardar».
 */
final class AccionSeoIa
{
    /**
     * @param  list<string>  $campos  campos traducibles que se envían (en español)
     * @param  list<string>  $traducir  de esos, los que se traducen al inglés si están vacíos en inglés
     */
    public static function make(string $que, array $campos, array $traducir = []): Action
    {
        return Action::make('seo_ia')->label('Proponer SEO con IA')->icon(Heroicon::OutlinedSparkles)->color('gray')
            ->visible(fn () => RedactorSeo::disponible())
            ->action(function ($livewire) use ($que, $campos, $traducir): void {
                $datos = [];
                foreach ($campos as $campo) {
                    $datos[$campo] = trim(strip_tags((string) data_get($livewire->data, "{$campo}.es")));
                }
                $faltan = array_values(array_filter($traducir, fn ($c) => blank(data_get($livewire->data, "{$c}.en")) && filled($datos[$c] ?? null)));
                try {
                    $p = app(RedactorSeo::class)->proponer($que, $datos, $faltan);
                } catch (IaNoDisponible $e) {
                    Notification::make()->title('La IA no está disponible')->body($e->getMessage())->warning()->send();

                    return;
                }
                data_set($livewire->data, 'seo_titulo', $p['seo_titulo']);
                data_set($livewire->data, 'seo_descripcion', $p['seo_descripcion']);
                foreach ($p['traducciones'] as $campo => $texto) {
                    data_set($livewire->data, "{$campo}.en", $texto);
                }
                Notification::make()->title('Propuesta lista')->body('Revísala y pulsa «Guardar» para aprobarla.')->success()->send();
            });
    }
}
