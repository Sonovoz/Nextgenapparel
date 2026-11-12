<?php

namespace App\Dominio\Catalogo;

use App\Models\Ajuste;
use App\Models\Foto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Imagick;

/**
 * Fotos del catálogo más ligeras (D210): al subirlas se giran según su EXIF, se quitan los metadatos,
 * se reducen al lado mayor de Ajustes → General y se guardan en WebP (con transparencia).
 * Las zonas de impresión van en proporción a la foto (FotoZona::ESCALA), así que no se mueven.
 */
final class OptimizarFoto
{
    /** @return array{max_px: int, calidad: int} */
    public static function limites(): array
    {
        return [
            'max_px' => max(400, (int) Ajuste::obtener('catalogo.foto_max_px')),
            'calidad' => min(100, max(40, (int) Ajuste::obtener('catalogo.foto_calidad'))),
        ];
    }

    /**
     * Optimiza todas las fotos que lo necesiten. Con un límite de segundos (desde el backend, para no pasar
     * del tiempo máximo de una petición) para a tiempo y dice cuántas quedan: se siguen en la tarea programada.
     *
     * @return array{fotos: int, ahorro: int, quedan: int}
     */
    public static function todas(?int $segundos = null): array
    {
        $fin = $segundos === null ? null : microtime(true) + $segundos;
        $fotos = $ahorro = $quedan = 0;
        foreach (Foto::query()->orderBy('id')->lazy() as $foto) {
            if (! self::pendiente($foto)) {
                continue;
            }
            if ($fin !== null && microtime(true) > $fin) {
                $quedan++;

                continue;
            }
            $bytes = rescue(fn () => self::aplicar($foto));
            if ($bytes !== null) {
                $fotos++;
                $ahorro += $bytes;
            }
        }

        return ['fotos' => $fotos, 'ahorro' => $ahorro, 'quedan' => $quedan];
    }

    /** ¿Hay que optimizarla? Si no es WebP o es más grande que el máximo. */
    public static function pendiente(Foto $foto): bool
    {
        if (! str_ends_with(strtolower($foto->ruta), '.webp')) {
            return true;
        }
        $ruta = Storage::disk(Foto::DISCO)->path($foto->ruta);
        [$ancho, $alto] = @getimagesize($ruta) ?: [0, 0];

        return max($ancho, $alto) > self::limites()['max_px'];
    }

    /**
     * Optimiza la foto y cambia su archivo. Devuelve los bytes ahorrados, o null si no se ha tocado
     * (sin Imagick, sin archivo, ya optimizada o el resultado no pesa menos).
     */
    public static function aplicar(Foto $foto): ?int
    {
        $disco = Storage::disk(Foto::DISCO);
        if (! extension_loaded('imagick') || blank($foto->ruta) || ! $disco->exists($foto->ruta) || ! self::pendiente($foto)) {
            return null;
        }
        ['max_px' => $max, 'calidad' => $calidad] = self::limites();
        $original = (string) $disco->get($foto->ruta);

        $imagen = new Imagick;
        $imagen->readImageBlob($original);
        $imagen->autoOrient();
        $imagen->stripImage();
        if (max($imagen->getImageWidth(), $imagen->getImageHeight()) > $max) {
            $imagen->resizeImage($max, $max, Imagick::FILTER_LANCZOS, 1, true);
        }
        $imagen->setImageFormat('webp');
        $imagen->setImageCompressionQuality($calidad);
        $imagen->setOption('webp:method', '6');
        $imagen->setOption('webp:alpha-quality', '100');
        $webp = $imagen->getImageBlob();
        $imagen->clear();

        $yaEraWebp = str_ends_with(strtolower($foto->ruta), '.webp');
        if ($yaEraWebp && strlen($webp) >= strlen($original)) {
            return null;
        }

        $anterior = $foto->ruta;
        $nueva = preg_replace('/\.[A-Za-z0-9]+$/', '', $anterior).'.webp';
        if ($nueva === $anterior) {
            $nueva = preg_replace('/\.webp$/i', '', $anterior).'-'.substr(md5($webp), 0, 8).'.webp';
        }
        $disco->put($nueva, $webp, 'public');
        $foto->forceFill(['ruta' => $nueva])->saveQuietly();
        DB::afterCommit(fn () => $disco->delete($anterior));

        return strlen($original) - strlen($webp);
    }
}
