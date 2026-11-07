<?php

namespace App\Models\Concerns;

use Spatie\Translatable\HasTranslations;

/**
 * Textos en español e inglés (spatie/laravel-translatable) que, además, al
 * guardar quitan los idiomas vacíos. Así, vaciar un texto en inglés en el
 * backend lo borra de verdad y vuelve a usarse el español de respaldo.
 */
trait Traducible
{
    use HasTranslations;

    public static function bootTraducible(): void
    {
        static::saving(function ($modelo): void {
            foreach ($modelo->translatable as $campo) {
                $crudo = $modelo->attributes[$campo] ?? null;
                if (! is_string($crudo)) {
                    continue;
                }

                $valores = json_decode($crudo, true);
                if (! is_array($valores)) {
                    continue;
                }

                $limpios = array_filter($valores, fn ($valor) => $valor !== null && $valor !== '');
                if ($limpios !== $valores) {
                    $modelo->attributes[$campo] = json_encode($limpios === [] ? new \stdClass : $limpios, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
        });
    }
}
