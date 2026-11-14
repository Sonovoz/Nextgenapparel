<?php

namespace App\Dominio\Sistema;

use Illuminate\Support\Facades\Cache;

/**
 * D236: cada actualización numerada. El número sale de docs/HISTORIAL.md, que lleva una línea por entrega (de la
 * más reciente a la más antigua): la más antigua es la 1. Así no hay que subir ningún número a mano y el backend
 * dice qué actualización tiene instalada el servidor.
 */
final class Actualizaciones
{
    public static function archivo(): string
    {
        return base_path('docs/HISTORIAL.md');
    }

    /** @return list<array{numero: int, fecha: string, texto: string, rama: string, decision: string}> */
    public static function todas(?string $archivo = null): array
    {
        $archivo ??= self::archivo();
        if (! is_file($archivo)) {
            return [];
        }

        return Cache::remember('actualizaciones.'.md5($archivo).'.'.filemtime($archivo), now()->addDay(), fn () => self::leer((string) file_get_contents($archivo)));
    }

    public static function actual(): ?array
    {
        return self::todas()[0] ?? null;
    }

    /** @return list<array{numero: int, fecha: string, texto: string, rama: string, decision: string}> */
    public static function leer(string $markdown): array
    {
        $filas = [];
        $anio = (int) date('Y');
        foreach (preg_split('/\R/', $markdown) as $linea) {
            if (preg_match('/^##\s+\pL+\s+de\s+(\d{4})/u', $linea, $m)) {
                $anio = (int) $m[1];
            }
            if (! preg_match('/^\|\s*(\d{2})\/(\d{2})\s*\|(.*)$/u', $linea, $m)) {
                continue;
            }
            $partes = array_map('trim', explode('|', rtrim(trim($m[3]), '|')));
            $filas[] = [
                'fecha' => sprintf('%s/%s/%d', $m[1], $m[2], $anio),
                'texto' => trim(str_replace(['**', '`'], '', $partes[0])),
                'rama' => trim(str_replace('`', '', $partes[1] ?? '')),
                'decision' => trim($partes[2] ?? ''),
            ];
        }
        $total = count($filas);

        return array_map(fn (array $f, int $i) => ['numero' => $total - $i, ...$f], $filas, array_keys($filas));
    }
}
