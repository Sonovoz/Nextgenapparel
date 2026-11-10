<?php

namespace App\Dominio\Pedidos;

use App\Dominio\Carrito\Maqueta;
use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\TipoFoto;
use App\Models\Foto;
use App\Models\ImpresionPedido;
use App\Models\LineaPack;
use App\Models\LineaPedido;
use App\Models\MaquetaPedido;
use App\Models\Pedido;
use App\Models\ZonaImpresion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickDraw;
use ImagickPixel;

/**
 * Maquetas del pedido (paso 6.2, D98): por cada línea y cara, la foto de maqueta de la
 * prenda en el color pedido con todos sus diseños en su posición y tamaño (los mm
 * guardados, D99), con el mismo fundido con la tela que el visor: la tinta toma las luces
 * y sombras de los pliegues. La genera el servidor, así es la misma en cualquier
 * navegador y se puede repetir. Es la referencia de lo que el cliente aprobó.
 *
 * Una cara sin foto de maqueta o una zona que ya no está en la foto se salta con un
 * aviso en el registro: el pedido no depende de su maqueta.
 */
final class GenerarMaquetas
{
    /** Límites de ImageMagick, como en el análisis de los diseños (D147). */
    private const RECURSOS = [
        Imagick::RESOURCETYPE_MEMORY => 256 * 1024 * 1024,
        Imagick::RESOURCETYPE_MAP => 512 * 1024 * 1024,
    ];

    /** Las 4 letras de «Escribir mi marca», como en el visor: archivo, mayúsculas y espaciado. */
    private const FUENTES = [
        ['Jost_700Bold.ttf', true, 0.08],
        ['Lora_400Regular_Italic.ttf', false, 0],
        ['Anton_400Regular.ttf', true, 0.02],
        ['DancingScript_700Bold.ttf', false, 0],
    ];

    private const TINTAS = ['negro' => '#16161A', 'blanco' => '#FFFFFF', 'rojo' => '#C8102E', 'dorado' => '#C9A227'];

    /** @return int maquetas generadas */
    public function __invoke(Pedido $pedido): int
    {
        $pedido->load('lineas.impresiones');
        $hechas = 0;
        foreach ($pedido->lineas as $linea) {
            foreach ($linea->impresiones->groupBy('cara') as $cara => $impresiones) {
                if ($this->cara($pedido, $linea, Cara::tryFrom((string) $cara), $impresiones->all())) {
                    $hechas++;
                }
            }
        }

        return $hechas;
    }

    /** @param  list<ImpresionPedido>  $impresiones */
    private function cara(Pedido $pedido, LineaPedido $linea, ?Cara $cara, array $impresiones): bool
    {
        [$prendaId, $colorId] = $this->prendaYColor($linea);
        $disco = Storage::disk(Pedido::DISCO);
        $capas = array_map(fn (ImpresionPedido $i) => new CapaMaqueta(
            (int) $i->zona_impresion_id, $i->ancho_mm, $i->alto_mm, $i->desde_cuello_mm, $i->desde_centro_mm,
            $i->archivo !== null && $disco->exists($i->archivo) ? $disco->get($i->archivo) : null,
            $i->util_ancho_px && $i->util_alto_px ? [(int) $i->util_x, (int) $i->util_y, $i->util_ancho_px, $i->util_alto_px] : null,
            $i->archivo === null ? $i->texto : null, $i->fuente, $i->tinta,
        ), $impresiones);
        $base = $this->componer($prendaId, $colorId, (string) $linea->color_hex, $cara, $capas, "Pedido {$pedido->numero}, línea {$linea->uuid}");
        if ($base === null || $cara === null) {
            return false;
        }

        $ruta = "{$pedido->uuid}/maquetas/{$linea->uuid}-{$cara->value}.webp";
        $base->setImageFormat('webp');
        $base->setImageCompressionQuality(86);
        Storage::disk(Pedido::DISCO)->put($ruta, $base->getImageBlob());
        MaquetaPedido::updateOrCreate(
            ['linea_pedido_id' => $linea->id, 'cara' => $cara->value],
            ['ruta' => $ruta, 'ancho_px' => $base->getImageWidth(), 'alto_px' => $base->getImageHeight(), 'generada_en' => now()],
        );

        return true;
    }

    /**
     * Una cara compuesta: la foto de maqueta de la prenda en ese color con sus diseños fundidos con la
     * tela. Null si no hay foto de maqueta (se avisa en el registro). La usan el pedido, que la guarda,
     * y el presupuesto, que la pone en su PDF (D200).
     *
     * @param  list<CapaMaqueta>  $capas
     */
    public function componer(?int $prendaId, ?int $colorId, string $colorHex, ?Cara $cara, array $capas, string $contexto = ''): ?Imagick
    {
        foreach (self::RECURSOS as $tipo => $bytes) {
            Imagick::setResourceLimit($tipo, $bytes);
        }
        $foto = $cara === null || $prendaId === null ? null : Foto::with('zonas')->where('prenda_id', $prendaId)->where('color_id', $colorId)
            ->where('tipo', TipoFoto::Maqueta)->where('cara', $cara->value)->first();
        if ($foto === null || ! Storage::disk(Foto::DISCO)->exists($foto->ruta)) {
            Log::warning("{$contexto}: sin foto de maqueta, cara {$cara?->value}.");

            return null;
        }

        $base = new Imagick;
        $base->readImageBlob(Storage::disk(Foto::DISCO)->get($foto->ruta));
        $base->setImageColorspace(Imagick::COLORSPACE_SRGB);
        $ancho = $base->getImageWidth();
        $alto = $base->getImageHeight();
        $oscura = $this->luminancia($colorHex) < 0.45;

        foreach ($capas as $capa) {
            $zona = ZonaImpresion::find($capa->zonaId);
            $rect = $foto->zonas->firstWhere('zona_impresion_id', $capa->zonaId);
            if ($zona === null || $rect === null) {
                Log::warning("{$contexto}: la zona {$capa->zonaId} ya no está en la foto de maqueta.");

                continue;
            }
            $r = Maqueta::rectangulo($zona, $rect, $cara, $capa->anchoMm, $capa->altoMm, $capa->desdeCuelloMm, $capa->desdeCentroMm);
            $x = (int) round($r['left'] / 100 * $ancho);
            $y = (int) round($r['top'] / 100 * $alto);
            $w = max(1, (int) round($r['width'] / 100 * $ancho));
            $h = max(1, (int) round($r['height'] / 100 * $alto));

            $imagen = $capa->logo !== null ? $this->logo($capa, $w, $h) : $this->texto($capa, $oscura, $w, $h);
            if ($imagen !== null) {
                $base->compositeImage($this->fundir($imagen, $base, $x, $y), Imagick::COMPOSITE_OVER, $x, $y);
            }
        }

        return $base;
    }

    /** El logo (su original), recortado a lo que se imprime (D114) y al tamaño de su caja. */
    private function logo(CapaMaqueta $capa, int $w, int $h): Imagick
    {
        $logo = new Imagick;
        $logo->readImageBlob((string) $capa->logo);
        if ($capa->util !== null) {
            [$ux, $uy, $uw, $uh] = $capa->util;
            $logo->cropImage($uw, $uh, $ux, $uy);
            $logo->setImagePage(0, 0, 0, 0);
        }
        $logo->setImageColorspace(Imagick::COLORSPACE_SRGB);
        $logo->resizeImage($w, $h, Imagick::FILTER_LANCZOS, 1);

        return $logo;
    }

    /** El texto de «Escribir mi marca» con su letra y su tinta, encajado en su caja y centrado. */
    private function texto(CapaMaqueta $impresion, bool $prendaOscura, int $w, int $h): ?Imagick
    {
        [$archivo, $mayusculas, $espacio] = self::FUENTES[$impresion->fuente ?? 0] ?? self::FUENTES[0];
        $cadena = trim((string) $impresion->texto);
        if ($cadena === '') {
            return null;
        }
        $cadena = $mayusculas ? mb_strtoupper($cadena) : $cadena;
        $tinta = self::TINTAS[$impresion->tinta] ?? ($prendaOscura ? self::TINTAS['blanco'] : self::TINTAS['negro']);

        $tamano = 200;
        $dibujo = new ImagickDraw;
        $dibujo->setFont(resource_path('fuentes/maqueta/'.$archivo));
        $dibujo->setFontSize($tamano);
        $dibujo->setFillColor(new ImagickPixel($tinta));
        $dibujo->setTextKerning($espacio * $tamano);
        $dibujo->setGravity(Imagick::GRAVITY_CENTER);
        $metricas = (new Imagick)->queryFontMetrics($dibujo, $cadena);
        $lienzo = new Imagick;
        $lienzo->newImage((int) ceil($metricas['textWidth'] + $tamano * 0.6), (int) ceil($tamano * 1.6), new ImagickPixel('transparent'));
        $lienzo->setImageFormat('png');
        $lienzo->annotateImage($dibujo, 0, 0, 0, $cadena);
        $lienzo->trimImage(0);
        $lienzo->setImagePage(0, 0, 0, 0);

        // Encajado sin deformar y centrado en su caja.
        $escala = min($w / $lienzo->getImageWidth(), $h / $lienzo->getImageHeight());
        $lienzo->resizeImage(max(1, (int) round($lienzo->getImageWidth() * $escala)), max(1, (int) round($lienzo->getImageHeight() * $escala)), Imagick::FILTER_LANCZOS, 1);
        $caja = new Imagick;
        $caja->newImage($w, $h, new ImagickPixel('transparent'));
        $caja->setImageFormat('png');
        $caja->compositeImage($lienzo, Imagick::COMPOSITE_OVER, intdiv($w - $lienzo->getImageWidth(), 2), intdiv($h - $lienzo->getImageHeight(), 2));

        return $caja;
    }

    /**
     * Como el visor: la tinta toma la luz de la tela (sus pliegues suavizados) y deja ver
     * un poco el tejido (96 % de opacidad). Con «luz fuerte» sobre una tela gris media.
     */
    private function fundir(Imagick $capa, Imagick $base, int $x, int $y): Imagick
    {
        $w = $capa->getImageWidth();
        $h = $capa->getImageHeight();
        $tela = clone $base;
        $tela->cropImage($w, $h, max(0, $x), max(0, $y));
        $tela->setImagePage(0, 0, 0, 0);
        if ($tela->getImageWidth() !== $w || $tela->getImageHeight() !== $h) {
            $tela->extentImage($w, $h, 0, 0); // la caja se sale de la foto: el borde, sin pliegues
        }
        $tela->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        $tela->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        $tela->blurImage(0, max(2, $w * 0.012));
        $q = Imagick::getQuantum();
        $media = max($tela->getImageChannelMean(Imagick::CHANNEL_GRAY)['mean'], 0.05 * $q);
        // La media de la tela pasa a gris medio (no cambia nada) y los pliegues se suavizan al 75 %.
        $tela->evaluateImage(Imagick::EVALUATE_MULTIPLY, 0.75 * 0.5 * $q / $media);
        $tela->evaluateImage(Imagick::EVALUATE_ADD, 0.125 * $q);
        $tela->transformImageColorspace(Imagick::COLORSPACE_SRGB);

        $alfa = clone $capa;
        $alfa->setImageAlphaChannel(Imagick::ALPHACHANNEL_EXTRACT);
        $color = clone $capa;
        $color->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        $color->compositeImage($tela, Imagick::COMPOSITE_HARDLIGHT, 0, 0);
        $color->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        $color->compositeImage($alfa, Imagick::COMPOSITE_COPYOPACITY, 0, 0);
        $color->evaluateImage(Imagick::EVALUATE_MULTIPLY, 0.96, Imagick::CHANNEL_ALPHA); // tinta, no pegatina

        return $color;
    }

    /** @return array{0: ?int, 1: ?int} la prenda y el color que se ven (en un pack, su primera prenda) */
    private function prendaYColor(LineaPedido $linea): array
    {
        return self::prendaYColorDe((string) $linea->tipo, $linea->prenda_id, $linea->color_id, $linea->pack_id, $linea->elecciones);
    }

    /**
     * Lo mismo para una línea del carrito o de un presupuesto.
     *
     * @param  array<int|string, mixed>|null  $elecciones
     * @return array{0: ?int, 1: ?int}
     */
    public static function prendaYColorDe(string $tipo, ?int $prendaId, ?int $colorId, ?int $packId, ?array $elecciones): array
    {
        if ($tipo === 'prenda') {
            return [$prendaId, $colorId];
        }
        $primera = $packId === null ? null : LineaPack::where('pack_id', $packId)->orderBy('id')->first();
        if ($primera === null) {
            return [null, null];
        }
        $color = $elecciones[$primera->id]['color'] ?? ($primera->colores[0] ?? null);

        return [$primera->prenda_id, $color === null ? null : (int) $color];
    }

    private function luminancia(string $hex): float
    {
        if (! preg_match('/^#?([0-9a-f]{6})$/i', $hex, $m)) {
            return 1.0;
        }
        [$r, $g, $b] = array_map(fn ($c) => hexdec($c) / 255, str_split($m[1], 2));

        return 0.299 * $r + 0.587 * $g + 0.114 * $b;
    }
}
