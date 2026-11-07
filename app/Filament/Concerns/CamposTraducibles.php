<?php

namespace App\Filament\Concerns;

use App\Dominio\Catalogo\Escaparate;
use Closure;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Campos en español e inglés para los textos traducibles del catálogo.
 * El español es obligatorio cuando el campo lo es; el inglés, opcional
 * (si falta se muestra el español).
 */
class CamposTraducibles
{
    /**
     * URL en español e inglés. Comprueba la URL «efectiva» de cada idioma (la
     * inglesa o, si falta, la española), que es la que usa la tienda para
     * encontrar la página: dos registros nunca pueden responder a la misma.
     *
     * @param  class-string<Model>  $modelo
     */
    public static function urls(string $modelo, int $largo, bool $reservadas = false): Grid
    {
        $campo = fn (string $idioma, string $nombre) => TextInput::make("slug.{$idioma}")->label("URL ({$nombre})")
            ->rule('regex:/^[a-z0-9]+(-[a-z0-9]+)*$/')->maxLength($largo)
            ->validationMessages(['regex' => 'Solo minúsculas, números y guiones, por ejemplo camiseta-basica.'])
            ->rule(fn (Get $get, ?Model $record) => function (string $atributo, $valor, Closure $fallo) use ($modelo, $idioma, $get, $record, $reservadas): void {
                if (blank($valor)) {
                    return;
                }
                if ($reservadas && in_array($valor, config('tienda.urls_reservadas'), true)) {
                    $fallo('Esta URL está reservada para otra página de la tienda.');

                    return;
                }

                // Idiomas en los que este valor es la URL efectiva del registro.
                $idiomas = $idioma === 'es' && blank($get('slug.en')) ? ['es', 'en'] : [$idioma];
                foreach ($idiomas as $i) {
                    $consulta = method_exists($modelo, 'bootSoftDeletes') ? $modelo::withTrashed() : $modelo::query();
                    $otro = Escaparate::porSlug($consulta, $valor, $i)->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->exists();
                    if ($otro) {
                        $fallo($i === $idioma ? 'Esta URL ya la usa otro registro.' : 'Esta URL ya la usa otro registro en inglés: escribe una URL en inglés para este.');

                        return;
                    }
                }
            });

        return Grid::make(2)->schema([
            $campo('es', 'español')->required(),
            $campo('en', 'inglés'),
        ]);
    }

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
     * Los idiomas vacíos pasan a null: el modelo (Traducible) los borra al
     * guardar y se usa el español de respaldo. Si solo se quitaran del array,
     * Spatie conservaría el valor anterior y no se podrían borrar.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<string>  $campos
     * @return array<string, mixed>
     */
    public static function limpiar(array $datos, array $campos): array
    {
        foreach ($campos as $campo) {
            if (is_array($datos[$campo] ?? null)) {
                $datos[$campo] = array_map(fn ($valor) => filled($valor) ? $valor : null, $datos[$campo]);
            }
        }

        return $datos;
    }
}
