<?php

namespace App\Dominio\Asistente;

use App\Dominio\Precios\MetodoEnvio;
use App\Models\ConsumoIa;
use App\Models\TarifaEnvio;
use App\Models\ZonaEnvio;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Asistente de envíos (D228): el equipo pega la tarifa del transportista (texto o foto) y la IA la **pasa a limpio**
 * como zonas, tramos en kg, kilo extra y precio por caja. No calcula ni inventa precios (D08): copia los del
 * documento y pregunta lo que falte. PHP comprueba cada dato y lo convierte a gramos y céntimos; el equipo ve
 * la vista previa con el cálculo real del envío y solo entonces se crean las zonas.
 */
final class AsistenteEnvios
{
    public const MAX_ZONAS = 20;

    public const MAX_TRAMOS = 30;

    public function __construct(private readonly ClienteOpenAi $openAi) {}

    /**
     * @param  ?string  $imagen  data URI de una foto o captura de la tarifa (image/png, image/jpeg o image/webp)
     * @return array{zonas: list<array<string, mixed>>, preguntas: list<string>, descartes: list<string>}
     */
    public function leer(string $texto, ?string $imagen = null, string $indicaciones = ''): array
    {
        if (! RedactorSeo::disponible()) {
            throw new IaNoDisponible('La IA no está disponible (clave, límite del mes o caída).');
        }
        $sistema = 'Pasas a limpio tarifas de transportistas para una tienda española. Devuelve SOLO un JSON con: '
            .'"zonas": [{"nombre": texto, "paises": [códigos ISO de 2 letras], "prefijos_cp": [prefijos de código postal, vacío = todo el país], '
            .'"metodo": "por_kilo" o "por_caja", "plazo_dias": entero o null, "tramos": [{"hasta_kg": número, "precio": número}], '
            .'"precio_kg_extra": número o null, "precio_caja": número o null}], "preguntas": [lo que falte o no esté claro]. '
            .'Reglas: copia los importes tal cual aparecen en el documento, en euros y sin IVA; si el documento los da con IVA, dilo en "preguntas" y no los cambies. '
            .'No inventes ni calcules ningún importe, tramo, plazo ni código postal: si un dato no está, déjalo en null y pregúntalo. '
            .'Para la península española usa ES sin prefijos; Baleares es el prefijo 07; Canarias 35 y 38; Ceuta 51; Melilla 52. Nombres en castellano de España.';
        $contenido = [['type' => 'text', 'text' => trim($texto) === '' ? '(La tarifa va en la imagen.)' : mb_substr($texto, 0, 20000)]];
        if (trim($indicaciones) !== '') {
            $contenido[] = ['type' => 'text', 'text' => 'Indicaciones del equipo: '.mb_substr($indicaciones, 0, 1000)];
        }
        if ($imagen !== null) {
            $contenido[] = ['type' => 'image_url', 'image_url' => ['url' => $imagen]];
        }
        try {
            $r = $this->openAi->chat([['role' => 'system', 'content' => $sistema], ['role' => 'user', 'content' => $contenido]], json: true);
        } catch (IaNoDisponible $e) {
            Cache::put('ia.caida', true, now()->addMinutes(10));

            throw $e;
        }
        ConsumoIa::create(['uso' => 'envios', 'tokens_entrada' => $r['entrada'], 'tokens_salida' => $r['salida'], 'coste' => AsistenteVenta::coste($r['entrada'], $r['salida'])]);
        $json = json_decode((string) ($r['mensaje']['content'] ?? ''), true);
        if (! is_array($json)) {
            throw new IaNoDisponible('La IA no ha devuelto una propuesta válida.');
        }

        return self::normalizar($json);
    }

    /**
     * Comprueba y convierte lo que devuelve la IA. Lo que no cumple se descarta y se dice por qué.
     *
     * @param  array<mixed>  $json
     * @return array{zonas: list<array<string, mixed>>, preguntas: list<string>, descartes: list<string>}
     */
    public static function normalizar(array $json): array
    {
        $zonas = [];
        $descartes = [];
        $numero = fn ($v) => is_numeric(str_replace(',', '.', (string) $v)) ? (float) str_replace(',', '.', (string) $v) : null;
        $centimos = fn ($v) => ($n = $numero($v)) === null || $n < 0 || $n > 10000 ? null : (int) round($n * 100);
        foreach (array_slice((array) ($json['zonas'] ?? []), 0, self::MAX_ZONAS) as $i => $z) {
            if (! is_array($z)) {
                continue;
            }
            $nombre = mb_substr(trim((string) ($z['nombre'] ?? '')), 0, 100) ?: 'Zona '.($i + 1);
            $paises = array_values(array_unique(array_filter(array_map(fn ($p) => strtoupper(trim((string) $p)), (array) ($z['paises'] ?? [])), fn ($p) => preg_match('/^[A-Z]{2}$/', $p) === 1)));
            if ($paises === []) {
                $descartes[] = "«{$nombre}»: sin un país válido (código ISO de 2 letras).";

                continue;
            }
            $prefijos = array_values(array_unique(array_filter(array_map(fn ($p) => preg_replace('/\D/', '', (string) $p), (array) ($z['prefijos_cp'] ?? [])), fn ($p) => $p !== '' && strlen($p) <= 5)));
            $metodo = ($z['metodo'] ?? '') === MetodoEnvio::PorCaja->value ? MetodoEnvio::PorCaja : MetodoEnvio::PorKilo;
            $tramos = [];
            foreach (array_slice((array) ($z['tramos'] ?? []), 0, self::MAX_TRAMOS) as $t) {
                $kg = is_array($t) ? $numero($t['hasta_kg'] ?? null) : null;
                $precio = is_array($t) ? $centimos($t['precio'] ?? null) : null;
                if ($kg === null || $kg <= 0 || $kg > 1000 || $precio === null) {
                    $descartes[] = "«{$nombre}»: un tramo sin kilos o sin precio válido.";

                    continue;
                }
                $tramos[(int) round($kg * 1000)] = $precio;
            }
            ksort($tramos);
            $plazo = $numero($z['plazo_dias'] ?? null);
            $zonas[] = [
                'nombre' => $nombre, 'paises' => $paises, 'prefijos_cp' => $prefijos, 'metodo' => $metodo->value,
                'plazo_dias' => $plazo === null ? null : max(0, min(60, (int) round($plazo))),
                'tramos' => array_map(fn ($g, $p) => ['hasta_gramos' => $g, 'precio' => $p], array_keys($tramos), $tramos),
                'precio_kg_extra' => $metodo === MetodoEnvio::PorKilo ? $centimos($z['precio_kg_extra'] ?? null) : null,
                'precio_caja' => $metodo === MetodoEnvio::PorCaja ? $centimos($z['precio_caja'] ?? null) : null,
            ];
        }
        $preguntas = array_values(array_filter(array_map(fn ($p) => mb_substr(trim((string) $p), 0, 300), array_slice((array) ($json['preguntas'] ?? []), 0, 10))));

        return ['zonas' => $zonas, 'preguntas' => $preguntas, 'descartes' => $descartes];
    }

    /** Una zona propuesta como modelo sin guardar (con sus tramos), para la vista previa y el cálculo del envío. */
    public static function modelo(array $zona, ?int $transportistaId = null): ZonaEnvio
    {
        $modelo = new ZonaEnvio([
            'nombre' => ['es' => $zona['nombre']], 'transportista_id' => $transportistaId, 'paises' => $zona['paises'], 'prefijos_cp' => $zona['prefijos_cp'],
            'metodo' => $zona['metodo'], 'plazo_dias' => $zona['plazo_dias'] ?? 2, 'precio_kg_extra' => $zona['precio_kg_extra'], 'precio_caja' => $zona['precio_caja'],
            'activa' => true, 'prioridad' => $zona['prefijos_cp'] === [] ? 0 : 10,
        ]);
        $modelo->setRelation('tarifas', new Collection(array_map(fn (array $t) => new TarifaEnvio($t), $zona['tramos'])));

        return $modelo;
    }

    /**
     * Crea las zonas aprobadas (comprobadas otra vez). Devuelve cuántas.
     *
     * @param  array<mixed>  $propuesta
     */
    public static function crear(array $propuesta, ?int $transportistaId, bool $activar): int
    {
        $zonas = self::normalizar($propuesta)['zonas'];

        return DB::transaction(function () use ($zonas, $transportistaId, $activar): int {
            foreach ($zonas as $z) {
                $modelo = self::modelo($z, $transportistaId);
                $tarifas = $modelo->tarifas;
                $modelo->unsetRelation('tarifas');
                $modelo->activa = $activar;
                $modelo->save();
                $modelo->tarifas()->createMany($tarifas->map(fn (TarifaEnvio $t) => ['hasta_gramos' => $t->hasta_gramos, 'precio' => $t->precio])->all());
            }

            return count($zonas);
        });
    }
}
