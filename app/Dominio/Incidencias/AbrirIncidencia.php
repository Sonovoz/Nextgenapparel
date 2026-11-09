<?php

namespace App\Dominio\Incidencias;

use App\Dominio\Pedidos\EstadoPedido;
use App\Dominio\Pedidos\Numeracion;
use App\Models\Ajuste;
use App\Models\Incidencia;
use App\Models\Pedido;
use App\Notifications\Pedidos\IncidenciaAbierta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use Throwable;

/**
 * El cliente abre una incidencia desde su pedido (paso 6.6b, D178): una vez enviado y durante los
 * días de Ajustes (14 por defecto) desde la entrega (o desde el envío si no se marcó), con su
 * motivo, lo que pasa y hasta 5 fotos. Una abierta a la vez por pedido.
 *
 * Las fotos se guardan recomprimidas en WebP y **sin datos de la cámara** (ubicación incluida).
 */
final class AbrirIncidencia
{
    public const MAX_FOTOS = 5;

    private const LADO_MAXIMO = 2000;

    /** Hasta cuándo se puede abrir (null si aún no se ha enviado). */
    public static function plazoHasta(Pedido $pedido): ?Carbon
    {
        $desde = $pedido->historial()->where('estado', EstadoPedido::Entregado->value)->latest('id')->value('created_at')
            ?? $pedido->historial()->where('estado', EstadoPedido::Enviado->value)->latest('id')->value('created_at');

        return $desde === null ? null : Carbon::parse($desde)->addDays(max(1, (int) Ajuste::obtener('incidencias.dias')))->endOfDay();
    }

    public static function puede(Pedido $pedido): bool
    {
        $hasta = self::plazoHasta($pedido);

        return in_array($pedido->estado, [EstadoPedido::Enviado, EstadoPedido::Entregado], true)
            && $hasta !== null && now()->lessThanOrEqualTo($hasta)
            && ! Incidencia::where('pedido_id', $pedido->id)->where('estado', 'abierta')->exists();
    }

    /** @param  list<UploadedFile>  $fotos */
    public function __invoke(Pedido $pedido, MotivoIncidencia $motivo, string $descripcion, array $fotos): Incidencia
    {
        $descripcion = trim($descripcion);
        if (mb_strlen($descripcion) < 10) {
            throw new IncidenciaNoValida('descripcion_corta');
        }
        if (count($fotos) > self::MAX_FOTOS || ($motivo->pideFotos() && $fotos === [])) {
            throw new IncidenciaNoValida($fotos === [] ? 'faltan_fotos' : 'demasiadas_fotos');
        }

        $uuid = (string) Str::uuid();
        $disco = Storage::disk(Pedido::DISCO);
        $guardadas = [];
        try {
            // Primero las fotos (lo lento), fuera del bloqueo; si algo falla, se borran.
            foreach ($fotos as $n => $foto) {
                $guardadas[] = $this->guardarFoto($foto, "{$pedido->uuid}/incidencias/{$uuid}/".($n + 1).'.webp');
            }

            $incidencia = DB::transaction(function () use ($pedido, $motivo, $descripcion, $uuid, $guardadas): Incidencia {
                $pedido = Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
                if (! self::puede($pedido)) {
                    throw new IncidenciaNoValida('no_puede');
                }
                $incidencia = Incidencia::create([
                    'uuid' => $uuid, 'codigo' => 'INC-'.str_pad((string) Numeracion::siguiente('incidencia'), 4, '0', STR_PAD_LEFT),
                    'pedido_id' => $pedido->id, 'motivo' => $motivo, 'descripcion' => mb_substr($descripcion, 0, 2000), 'estado' => 'abierta',
                ]);
                $incidencia->fotos()->createMany($guardadas);
                $pedido->notas()->create(['tipo' => 'privada', 'texto' => "Incidencia {$incidencia->codigo} abierta por el cliente: {$motivo->etiqueta('es')}."]);

                return $incidencia;
            });
        } catch (Throwable $error) {
            $disco->delete(array_column($guardadas, 'ruta'));

            throw $error;
        }

        if (filled($correo = Ajuste::obtener('tienda.correo_contacto'))) {
            Notification::route('mail', $correo)->notify(new IncidenciaAbierta($incidencia));
        }

        return $incidencia;
    }

    /** @return array{ruta: string, ancho_px: int, alto_px: int, bytes: int} */
    private function guardarFoto(UploadedFile $foto, string $ruta): array
    {
        try {
            $imagen = new Imagick((string) $foto->getRealPath());
            $imagen->setIteratorIndex(0);
            $imagen->autoOrient(); // la del móvil, antes de quitar los datos de la cámara
            if ($imagen->getImageWidth() > self::LADO_MAXIMO || $imagen->getImageHeight() > self::LADO_MAXIMO) {
                $imagen->thumbnailImage(self::LADO_MAXIMO, self::LADO_MAXIMO, true);
            }
            $imagen->stripImage(); // EXIF, GPS y perfiles fuera
            $imagen->setImageFormat('webp');
            $imagen->setImageCompressionQuality(82);
            $datos = $imagen->getImageBlob();
            $medidas = ['ancho_px' => $imagen->getImageWidth(), 'alto_px' => $imagen->getImageHeight()];
            $imagen->clear();
        } catch (Throwable) {
            throw new IncidenciaNoValida('foto_no_valida');
        }
        Storage::disk(Pedido::DISCO)->put($ruta, $datos);

        return ['ruta' => $ruta, ...$medidas, 'bytes' => strlen($datos)];
    }
}
