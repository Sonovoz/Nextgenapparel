<?php

namespace App\Filament\Concerns;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Campos en español e inglés para los textos traducibles del catálogo.
 * El español es obligatorio cuando el campo lo es; el inglés, opcional
 * (si falta se muestra el español).
 */
class CamposTraducibles
{
    public static function texto(string $campo, string $etiqueta, bool $obligatorio = false, bool $largo = false, ?string $slugDe = null): Grid
    {
        $crear = fn (string $idioma, string $nombre) => ($largo ? Textarea::make("{$campo}.{$idioma}")->rows(3) : TextInput::make("{$campo}.{$idioma}")->maxLength(255))
            ->label("{$etiqueta} ({$nombre})");

        $es = $crear('es', 'español')->required($obligatorio);
        $en = $crear('en', 'inglés');

        // Al escribir el nombre, propone el slug del mismo idioma si está vacío.
        if ($slugDe !== null) {
            foreach (['es' => $es, 'en' => $en] as $idioma => $componente) {
                $componente->live(onBlur: true)->afterStateUpdated(function ($set, $get, ?string $state) use ($slugDe, $idioma): void {
                    if (blank($get("{$slugDe}.{$idioma}"))) {
                        $set("{$slugDe}.{$idioma}", Str::slug((string) $state));
                    }
                });
            }
        }

        return Grid::make(2)->schema([$es, $en]);
    }

    /**
     * Datos del formulario de edición: los campos traducibles con todos sus idiomas.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function rellenar(array $datos, Model $registro): array
    {
        foreach ($registro->translatable ?? [] as $campo) {
            $datos[$campo] = $registro->getTranslations($campo);
        }

        return $datos;
    }

    /**
     * Quita idiomas vacíos para que se use el español de respaldo.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<string>  $campos
     * @return array<string, mixed>
     */
    public static function limpiar(array $datos, array $campos): array
    {
        foreach ($campos as $campo) {
            if (is_array($datos[$campo] ?? null)) {
                $datos[$campo] = array_filter($datos[$campo], fn ($valor) => filled($valor));
            }
        }

        return $datos;
    }
}
