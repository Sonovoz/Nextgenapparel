<?php

namespace App\Dominio\Sistema\Actividad;

use App\Models\Prenda;
use App\Models\RegistroError;
use App\Models\User;
use App\Models\Variante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Textos del registro de actividad en español y apuntes que no salen solos
 * de los cambios de un modelo (cambios de rol, accesos, errores resueltos).
 */
class Actividad
{
    /** Nombre legible de cada campo que se registra. */
    public const CAMPOS = [
        'name' => 'Nombre',
        'email' => 'Correo electrónico',
        'activo' => 'Activo',
        'rol' => 'Rol',
        'precio_base' => 'Precio sin IVA',
        'coste' => 'Coste',
        'sku' => 'SKU',
        'familia_id' => 'Familia',
        'activa' => 'Activa',
        'iva' => 'IVA',
    ];

    /** Nombre legible de cada tipo de elemento. */
    public const ELEMENTOS = [
        User::class => 'Usuario',
        RegistroError::class => 'Error',
        Prenda::class => 'Prenda',
        Variante::class => 'Variación',
    ];

    public static function apuntar(string $registro, string $descripcion, ?Model $sobre = null, array $propiedades = [], ?User $quien = null): void
    {
        $actividad = activity($registro)->withProperties($propiedades);

        if ($sobre !== null) {
            $actividad->performedOn($sobre);
        }

        $quien ??= auth()->user();

        if ($quien !== null) {
            $actividad->causedBy($quien);
        }

        $actividad->log($descripcion);
    }

    public static function elemento(Activity $actividad): ?string
    {
        if ($actividad->subject_type === null) {
            return null;
        }

        $tipo = self::ELEMENTOS[$actividad->subject_type] ?? class_basename($actividad->subject_type);
        $nombre = $actividad->subject?->getAttribute('name')
            ?? ($actividad->subject instanceof RegistroError ? Str::limit($actividad->subject->mensaje, 60) : null)
            ?? ($actividad->subject instanceof Prenda ? $actividad->subject->nombre : null)
            ?? ($actividad->subject instanceof Variante ? $actividad->subject->sku : null)
            ?? '#'.$actividad->subject_id;

        return $tipo.': '.$nombre;
    }

    /**
     * Cambios en formato legible: [campo => [antes, después]].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function cambios(Activity $actividad): array
    {
        $nuevos = (array) ($actividad->properties['attributes'] ?? []);
        $antiguos = (array) ($actividad->properties['old'] ?? []);
        $cambios = [];

        foreach ($nuevos as $campo => $valor) {
            $cambios[self::CAMPOS[$campo] ?? $campo] = [self::valor($antiguos[$campo] ?? null, $campo), self::valor($valor, $campo)];
        }

        return $cambios;
    }

    public static function resumen(Activity $actividad): ?string
    {
        $cambios = self::cambios($actividad);

        if ($cambios === []) {
            return null;
        }

        return collect($cambios)
            ->map(fn (array $valores, string $campo) => $actividad->event === 'created'
                ? "{$campo}: {$valores[1]}"
                : "{$campo}: {$valores[0]} → {$valores[1]}")
            ->join(' · ');
    }

    private static function valor(mixed $valor, ?string $campo = null): string
    {
        return match (true) {
            in_array($campo, ['precio_base', 'coste'], true) && is_numeric($valor) => number_format($valor / 100, 2, ',', '.').' €',
            $valor === null => '—',
            is_bool($valor) => $valor ? 'Sí' : 'No',
            default => (string) $valor,
        };
    }
}
