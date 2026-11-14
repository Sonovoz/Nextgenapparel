<?php

namespace App\Dominio\Catalogo;

use App\Models\Color;
use App\Models\Foto;
use App\Models\Prenda;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickException;
use ImagickPixel;

/**
 * D239: todas las maquetas de una prenda con la prenda al mismo tamaño y en el mismo sitio, tomando como
 * referencia la del color blanco (el más claro de la prenda) de la misma cara. Se detecta la silueta de la prenda
 * (recortando el fondo transparente o liso), se escala para que mida como la del blanco y se coloca centrada y a la
 * misma altura del cuello, en un lienzo del tamaño de la foto del blanco. Así las zonas del blanco valen para todos.
 */
final class IgualarEncuadre
{
    /** Tolerancia del recorte del fondo (fracción del rango de color) y margen para dar por igualada una foto. */
    private const FUZZ = 0.08;

    private const MARGEN = 0.01;

    /** La maqueta de referencia de la misma prenda y cara: la del color más claro (el blanco). */
    public static function referencia(Foto $foto): ?Foto
    {
        if (! CopiarZonas::esMaqueta($foto)) {
            return null;
        }
        $candidatas = array_merge([$foto], CopiarZonas::hermanas($foto));
        $colores = Color::whereIn('id', array_map(fn (Foto $f) => $f->color_id, $candidatas))->pluck('codigo_hex', 'id');
        usort($candidatas, fn (Foto $a, Foto $b) => [self::luminancia($colores[$b->color_id] ?? null), -$b->getKey()] <=> [self::luminancia($colores[$a->color_id] ?? null), -$a->getKey()]);

        return $candidatas[0];
    }

    /** Al subir o cambiar una maqueta que no es la del blanco: se iguala a ella. */
    public static function alSubir(Foto $foto): bool
    {
        $referencia = self::referencia($foto);

        return $referencia !== null && ! $referencia->is($foto) && self::igualar($foto, $referencia) === true;
    }

    /**
     * Iguala todas las maquetas de la prenda a la del blanco de su cara y les copia sus zonas.
     *
     * @return array{igualadas: int, iguales: int, fallidas: list<string>}
     */
    public static function prenda(Prenda $prenda): array
    {
        $resultado = ['igualadas' => 0, 'iguales' => 0, 'fallidas' => []];
        $maquetas = Foto::where('prenda_id', $prenda->getKey())->where('tipo', TipoFoto::Maqueta->value)->get();
        $nombres = Color::whereIn('id', $maquetas->pluck('color_id'))->get()->mapWithKeys(fn ($c) => [$c->getKey() => (string) $c->nombre]);
        foreach ($maquetas->groupBy(fn (Foto $f) => (string) $f->getRawOriginal('cara')) as $deUnaCara) {
            $referencia = self::referencia($deUnaCara->first());
            if ($referencia === null) {
                continue;
            }
            foreach ($deUnaCara as $foto) {
                if ($foto->is($referencia)) {
                    continue;
                }
                $r = self::igualar($foto, $referencia);
                match ($r) {
                    true => $resultado['igualadas']++,
                    false => $resultado['iguales']++,
                    default => $resultado['fallidas'][] = ($nombres[$foto->color_id] ?? 'Color').' ('.$foto->getRawOriginal('cara').'): '.$r,
                };
            }
            if ($referencia->zonas()->exists()) {
                CopiarZonas::aOtrosColores($referencia);
            }
        }

        return $resultado;
    }

    /**
     * Rehace la foto con la prenda al tamaño y en el sitio de la de referencia.
     *
     * @return bool|string true si se ha cambiado, false si ya estaba igual, o el motivo por el que no se puede
     */
    public static function igualar(Foto $foto, Foto $referencia): bool|string
    {
        $disco = Storage::disk(Foto::DISCO);
        if (! extension_loaded('imagick') || ! $disco->exists($foto->ruta) || ! $disco->exists($referencia->ruta)) {
            return 'falta la foto';
        }
        $ref = new Imagick;
        $img = new Imagick;
        try {
            $ref->readImageBlob((string) $disco->get($referencia->ruta));
            $img->readImageBlob((string) $disco->get($foto->ruta));
            $cajaRef = self::caja($ref);
            $caja = self::caja($img);
            if ($cajaRef === null || $caja === null) {
                return 'no se distingue la prenda del fondo (usa fondo transparente o liso)';
            }
            [$anchoRef, $altoRef] = [$ref->getImageWidth(), $ref->getImageHeight()];
            if ($img->getImageWidth() === $anchoRef && $img->getImageHeight() === $altoRef && self::parecidas($caja, $cajaRef, $anchoRef, $altoRef)) {
                return false;
            }

            // Misma silueta: la escala media de ancho y alto; centrada en horizontal y con el cuello a la misma altura.
            $escala = ($cajaRef[2] / $caja[2] + $cajaRef[3] / $caja[3]) / 2;
            $fondo = $img->getImageAlphaChannel() ? new ImagickPixel('transparent') : $img->getImagePixelColor(0, 0);
            $img->resizeImage(max(1, (int) round($img->getImageWidth() * $escala)), max(1, (int) round($img->getImageHeight() * $escala)), Imagick::FILTER_LANCZOS, 1);
            $lienzo = new Imagick;
            $lienzo->newImage($anchoRef, $altoRef, $fondo);
            $x = (int) round($cajaRef[0] + $cajaRef[2] / 2 - ($caja[0] + $caja[2] / 2) * $escala);
            $y = (int) round($cajaRef[1] - $caja[1] * $escala);
            $lienzo->compositeImage($img, Imagick::COMPOSITE_OVER, $x, $y);
            $lienzo->setImageFormat('webp');
            $lienzo->setImageCompressionQuality(OptimizarFoto::limites()['calidad']);
            $lienzo->setOption('webp:alpha-quality', '100');
            $webp = $lienzo->getImageBlob();
            $lienzo->clear();
        } catch (ImagickException) {
            return 'no se ha podido leer la foto';
        } finally {
            $ref->clear();
            $img->clear();
        }

        $anterior = $foto->ruta;
        $nueva = preg_replace('/(-igual-[0-9a-f]{8})?\.[A-Za-z0-9]+$/', '', $anterior).'-igual-'.substr(md5($webp), 0, 8).'.webp';
        $disco->put($nueva, $webp, 'public');
        $foto->forceFill(['ruta' => $nueva])->saveQuietly();
        DB::afterCommit(fn () => $disco->delete($anterior));

        return true;
    }

    /**
     * La caja de la prenda [x, y, ancho, alto]: lo que queda al recortar el fondo (transparente o del color de la
     * esquina). Null si no se distingue (ocupa casi toda la foto o casi nada).
     *
     * @return ?array{0: int, 1: int, 2: int, 3: int}
     */
    public static function caja(Imagick $imagen): ?array
    {
        $copia = clone $imagen;
        try {
            $copia->trimImage(self::FUZZ * Imagick::getQuantum());
            $pagina = $copia->getImagePage();
            $caja = [(int) $pagina['x'], (int) $pagina['y'], $copia->getImageWidth(), $copia->getImageHeight()];
        } finally {
            $copia->clear();
        }
        $fraccion = ($caja[2] * $caja[3]) / max(1, $imagen->getImageWidth() * $imagen->getImageHeight());

        return $fraccion < 0.03 || $fraccion > 0.97 ? null : $caja;
    }

    /** @param  array{0: int, 1: int, 2: int, 3: int}  $a  @param  array{0: int, 1: int, 2: int, 3: int}  $b */
    private static function parecidas(array $a, array $b, int $ancho, int $alto): bool
    {
        foreach ([0, 2] as $i) {
            if (abs($a[$i] - $b[$i]) > max(2, $ancho * self::MARGEN) || abs($a[$i + 1] - $b[$i + 1]) > max(2, $alto * self::MARGEN)) {
                return false;
            }
        }

        return true;
    }

    private static function luminancia(?string $hex): float
    {
        if ($hex === null || ! preg_match('/^#?([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/', $hex, $m)) {
            return -1;
        }

        return 0.2126 * hexdec($m[1]) + 0.7152 * hexdec($m[2]) + 0.0722 * hexdec($m[3]);
    }
}
