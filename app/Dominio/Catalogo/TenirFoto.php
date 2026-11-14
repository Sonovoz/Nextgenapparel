<?php

namespace App\Dominio\Catalogo;

use App\Models\Color;
use App\Models\Foto;
use App\Models\Prenda;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;

/**
 * D251: maquetas de otros colores a partir de la del blanco, sin subir fotos. La foto blanca se pasa a gris, su tono
 * medio se lleva a gris medio y se funde con «luz fuerte» sobre el color de la ficha (su código hex): los pliegues y las
 * sombras se conservan y el color es el real. La prenda queda idéntica (forma, encuadre y tamaño), así que las zonas de
 * impresión del blanco valen tal cual. Nunca pisa una foto real: solo crea las que faltan o rehace las ya teñidas.
 */
final class TenirFoto
{
    /** Cuánto se conservan los pliegues (1 = como en la foto blanca). */
    private const RELIEVE = 0.9;

    /** La maqueta blanca (o la más clara) de la prenda para esa cara. */
    public static function base(Prenda $prenda, Cara $cara): ?Foto
    {
        $foto = Foto::where('prenda_id', $prenda->getKey())->where('tipo', TipoFoto::Maqueta->value)->where('cara', $cara->value)
            ->whereNull('tenida_desde_id')->first();

        return $foto === null ? null : IgualarEncuadre::referencia($foto);
    }

    /** La imagen teñida (WebP). $lado: lado mayor en px para una vista previa; null, a tamaño completo. */
    public static function imagen(Foto $base, string $hex, ?int $lado = null): string
    {
        $original = new Imagick;
        $original->readImageBlob((string) Storage::disk(Foto::DISCO)->get($base->ruta));
        if ($lado !== null) {
            $original->thumbnailImage($lado, $lado, true);
        }
        $w = $original->getImageWidth();
        $h = $original->getImageHeight();
        $conAlfa = (bool) $original->getImageAlphaChannel();

        // Relieve: la luz de la foto blanca en gris, con su tono medio (el de la prenda) en gris medio.
        $tela = clone $original;
        $tela->setImageBackgroundColor('white');
        $tela = $tela->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $tela->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        $q = Imagick::getQuantum();
        $caja = IgualarEncuadre::caja($original);
        $muestra = clone $tela;
        if ($caja !== null) {
            $muestra->cropImage($caja[2], $caja[3], $caja[0], $caja[1]);
        }
        $media = max($muestra->getImageChannelMean(Imagick::CHANNEL_GRAY)['mean'], 0.05 * $q);
        $muestra->clear();
        $tela->evaluateImage(Imagick::EVALUATE_MULTIPLY, self::RELIEVE * 0.5 * $q / $media);
        $tela->evaluateImage(Imagick::EVALUATE_ADD, (1 - self::RELIEVE) * 0.5 * $q);
        $tela->transformImageColorspace(Imagick::COLORSPACE_SRGB);

        $color = new Imagick;
        $color->newImage($w, $h, new ImagickPixel(self::hex($hex)));
        $color->compositeImage($tela, Imagick::COMPOSITE_HARDLIGHT, 0, 0);
        if ($conAlfa) {
            $alfa = clone $original;
            $alfa->setImageAlphaChannel(Imagick::ALPHACHANNEL_EXTRACT);
            $color->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
            $color->compositeImage($alfa, Imagick::COMPOSITE_COPYOPACITY, 0, 0);
            $alfa->clear();
        }
        $color->setImageFormat('webp');
        $color->setImageCompressionQuality(OptimizarFoto::limites()['calidad']);
        $color->setOption('webp:alpha-quality', '100');
        $blob = $color->getImageBlob();
        $original->clear();
        $tela->clear();
        $color->clear();

        return $blob;
    }

    /**
     * Crea (o rehace) las maquetas teñidas de esos colores en las dos caras. No toca las fotos reales.
     *
     * @param  list<int>  $colorIds
     * @return array{creadas: int, rehechas: int, con_foto_real: list<string>, sin_base: list<string>, sin_transparencia: list<string>}
     */
    public static function colores(Prenda $prenda, array $colorIds): array
    {
        $r = ['creadas' => 0, 'rehechas' => 0, 'con_foto_real' => [], 'sin_base' => [], 'sin_transparencia' => []];
        $disco = Storage::disk(Foto::DISCO);
        foreach (Cara::cases() as $cara) {
            $base = self::base($prenda, $cara);
            if ($base === null) {
                $r['sin_base'][] = $cara->value;

                continue;
            }
            // Con fondo liso no se distingue la tela blanca del fondo blanco: se teñiría también el fondo.
            if (! self::transparente($base)) {
                $r['sin_transparencia'][] = $cara->value;

                continue;
            }
            foreach (Color::whereIn('id', $colorIds)->get() as $color) {
                if ($color->getKey() === $base->color_id) {
                    continue;
                }
                $existente = Foto::where('prenda_id', $prenda->getKey())->where('tipo', TipoFoto::Maqueta->value)
                    ->where('cara', $cara->value)->where('color_id', $color->getKey())->first();
                if ($existente !== null && $existente->tenida_desde_id === null) {
                    $r['con_foto_real'][] = $color->getTranslation('nombre', 'es').' ('.$cara->value.')';

                    continue;
                }
                $blob = self::imagen($base, (string) $color->codigo_hex);
                $ruta = 'catalogo/'.$prenda->getKey().'/tenida-'.$color->getKey().'-'.$cara->value.'-'.substr(md5($blob), 0, 8).'.webp';
                $disco->put($ruta, $blob, 'public');
                if ($existente !== null) {
                    $existente->update(['ruta' => $ruta, 'tenida_desde_id' => $base->getKey()]); // el archivo anterior se borra solo
                    $r['rehechas']++;
                    $foto = $existente;
                } else {
                    $foto = Foto::create(['prenda_id' => $prenda->getKey(), 'color_id' => $color->getKey(), 'tipo' => TipoFoto::Maqueta,
                        'cara' => $cara, 'ruta' => $ruta, 'tenida_desde_id' => $base->getKey(), 'orden' => (int) $base->orden,
                        'texto_alternativo' => ['es' => trim($prenda->getTranslation('nombre', 'es').' '.$color->getTranslation('nombre', 'es'))]]);
                    $r['creadas']++;
                }
                CopiarZonas::copiar($base, $foto); // las zonas del blanco valen tal cual: la prenda es idéntica
            }
        }

        return $r;
    }

    public static function transparente(Foto $foto): bool
    {
        $disco = Storage::disk(Foto::DISCO);
        if (! $disco->exists($foto->ruta)) {
            return false;
        }
        $imagen = new Imagick;
        $imagen->readImageBlob((string) $disco->get($foto->ruta));
        $alfa = (bool) $imagen->getImageAlphaChannel() && $imagen->getImagePixelColor(0, 0)->getColorValue(Imagick::COLOR_ALPHA) < 0.5;
        $imagen->clear();

        return $alfa;
    }

    private static function hex(string $hex): string
    {
        return preg_match('/^#?[0-9A-Fa-f]{6}$/', $hex) ? '#'.ltrim($hex, '#') : '#808080';
    }
}
