<?php

namespace App\Jobs;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\TipoFoto;
use App\Dominio\Catalogo\ZonasPorDefecto;
use App\Models\Foto;
use App\Models\Prenda;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * D254: descarga las fotos de TopTex de una prenda (maniquí invisible, delantera y trasera de cada color) y las
 * deja como maquetas del configurador, en la cola y con reintentos (son muchas y pesadas). Al guardarlas se pasan
 * a WebP y se igualan al encuadre de la blanca, como las subidas a mano. Nunca pisa una foto real: solo rellena
 * los huecos y sustituye las teñidas desde la blanca (D251), que conservan sus zonas.
 */
class ImportarFotosTopTex implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 600];

    public int $timeout = 600;

    /** @param  list<array{color_id: int, cara: string, url: string}>  $fotos */
    public function __construct(public readonly int $prendaId, public readonly array $fotos) {}

    public function handle(): void
    {
        $prenda = Prenda::find($this->prendaId);
        if ($prenda === null) {
            return;
        }
        $fallos = 0;
        foreach ($this->fotos as $f) {
            $actual = Foto::where('prenda_id', $prenda->getKey())->where('tipo', TipoFoto::Maqueta->value)
                ->where('color_id', $f['color_id'])->where('cara', $f['cara'])->first();
            if ($actual !== null && $actual->getAttribute('tenida_desde_id') === null) {
                continue; // ya tiene foto real
            }
            $ruta = $this->descargar($prenda, $f);
            if ($ruta === null) {
                $fallos++;

                continue;
            }
            if ($actual !== null) {
                $actual->update(['ruta' => $ruta, 'tenida_desde_id' => null, 'origen' => 'api']);
            } else {
                $foto = Foto::create([
                    'prenda_id' => $prenda->getKey(), 'color_id' => $f['color_id'], 'tipo' => TipoFoto::Maqueta->value,
                    'cara' => Cara::from($f['cara'])->value, 'ruta' => $ruta, 'origen' => 'api', 'orden' => $f['cara'] === Cara::Delantera->value ? 0 : 1,
                ]);
                app(ZonasPorDefecto::class)($foto);
            }
        }
        if ($fallos > 0) {
            throw new RuntimeException("TopTex: {$fallos} fotos de la prenda {$prenda->referencia_proveedor} no se han podido descargar."); // se reintenta; las hechas no se repiten
        }
    }

    /** @param  array{color_id: int, cara: string, url: string}  $f */
    private function descargar(Prenda $prenda, array $f): ?string
    {
        $respuesta = rescue(fn () => Http::timeout(60)->get($f['url']), null, report: false);
        $tipo = (string) $respuesta?->header('Content-Type');
        $extension = match (true) {
            str_contains($tipo, 'png') => 'png',
            str_contains($tipo, 'webp') => 'webp',
            str_contains($tipo, 'jpeg'), str_contains($tipo, 'jpg') => 'jpg',
            default => null,
        };
        if ($respuesta === null || ! $respuesta->successful() || $extension === null || strlen($respuesta->body()) > 30 * 1024 * 1024) {
            return null;
        }
        $ruta = 'catalogo/'.$prenda->getKey().'/toptex-'.$f['color_id'].'-'.$f['cara'].'-'.substr(sha1($f['url']), 0, 8).'.'.$extension;
        Storage::disk(Foto::DISCO)->put($ruta, $respuesta->body(), 'public');

        return $ruta;
    }
}
