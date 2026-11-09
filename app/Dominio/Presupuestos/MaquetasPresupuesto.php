<?php

namespace App\Dominio\Presupuestos;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Disenos\GuardarDiseno;
use App\Dominio\Pedidos\CapaMaqueta;
use App\Dominio\Pedidos\GenerarMaquetas;
use App\Models\Color;
use App\Models\Diseno;
use App\Models\Presupuesto;
use App\Models\ZonaImpresion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Throwable;

/**
 * La foto de lo que el cliente ha creado en el PDF del presupuesto (D200): por cada línea y cara, la
 * maqueta con sus diseños, compuesta igual que la del pedido (GenerarMaquetas). Si falta algo (Imagick,
 * la foto de maqueta o un diseño ya borrado), esa imagen no sale y el presupuesto sigue saliendo.
 */
final class MaquetasPresupuesto
{
    public function __construct(private readonly GenerarMaquetas $generar) {}

    /** @return array<int, list<string>> por índice de línea, sus maquetas como JPEG embebido (data URI) */
    public function __invoke(Presupuesto $presupuesto): array
    {
        if (! extension_loaded('imagick')) {
            return [];
        }

        // El presupuesto no cambia: sus maquetas se componen una vez al día como mucho.
        return Cache::remember("presupuesto.{$presupuesto->uuid}.maquetas", now()->addDay(), fn () => $this->componer($presupuesto));
    }

    /** @return array<int, list<string>> */
    private function componer(Presupuesto $presupuesto): array
    {
        $resultado = [];
        foreach ($presupuesto->origen as $i => $linea) {
            $impresiones = (array) ($linea['impresiones'] ?? []);
            [$prendaId, $colorId] = GenerarMaquetas::prendaYColorDe((string) ($linea['tipo'] ?? ''), $linea['prenda_id'] ?? null, $linea['color_id'] ?? null, $linea['pack_id'] ?? null, $linea['elecciones'] ?? null);
            if ($impresiones === [] || $prendaId === null) {
                continue;
            }
            $hex = (string) Color::find($colorId)?->codigo_hex;
            $zonas = ZonaImpresion::whereIn('id', array_keys($impresiones))->get()->keyBy('id');
            $porCara = [];
            foreach ($impresiones as $zonaId => $imp) {
                $cara = $zonas->get((int) $zonaId)?->cara;
                if ($cara === null) {
                    continue;
                }
                $diseno = isset($imp['diseno']) ? Diseno::where('uuid', $imp['diseno'])->first() : null;
                $disco = Storage::disk(GuardarDiseno::DISCO);
                $logo = $diseno !== null && $disco->exists($diseno->ruta) ? $disco->get($diseno->ruta) : null;
                $porCara[$cara->value][] = new CapaMaqueta(
                    (int) $zonaId, (int) $imp['ancho_mm'], (int) $imp['alto_mm'], (int) ($imp['desde_cuello_mm'] ?? 0), (int) ($imp['desde_centro_mm'] ?? 0),
                    $logo,
                    $diseno !== null && $diseno->util_ancho_px && $diseno->util_alto_px ? [(int) $diseno->util_x, (int) $diseno->util_y, (int) $diseno->util_ancho_px, (int) $diseno->util_alto_px] : null,
                    isset($imp['diseno']) ? null : ($imp['texto'] ?? null), $imp['fuente'] ?? null, $imp['tinta'] ?? null,
                );
            }
            foreach (Cara::cases() as $cara) {
                if (empty($porCara[$cara->value])) {
                    continue;
                }
                try {
                    $imagen = $this->generar->componer($prendaId, $colorId, $hex, $cara, $porCara[$cara->value], "Presupuesto {$presupuesto->numero}");
                    if ($imagen !== null) {
                        $resultado[$i][] = self::jpeg($imagen);
                    }
                } catch (Throwable $e) {
                    Log::warning("Presupuesto {$presupuesto->numero}: no se ha podido componer su maqueta ({$e->getMessage()}).");
                }
            }
        }

        return $resultado;
    }

    /** JPEG embebido: dompdf no lee WebP en todos los servidores ni archivos remotos. */
    private static function jpeg(Imagick $imagen): string
    {
        $imagen->setImageBackgroundColor('white');
        $plana = $imagen->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $plana->thumbnailImage(500, 0);
        $plana->setImageFormat('jpeg');
        $plana->setImageCompressionQuality(82);

        return 'data:image/jpeg;base64,'.base64_encode($plana->getImageBlob());
    }
}
