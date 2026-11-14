<?php

namespace App\Dominio\Asistente;

use App\Dominio\Catalogo\TipoFoto;
use App\Dominio\Disenos\Calidad;
use App\Dominio\Disenos\GuardarDiseno;
use App\Models\Ajuste;
use App\Models\ConsumoIa;
use App\Models\Diseno;
use App\Models\Foto;
use App\Models\FotoZona;
use App\Models\Prenda;
use App\Models\ZonaImpresion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * D246: consejo de la IA al subir un diseño. La IA mira la miniatura del diseño (nunca datos del cliente) y, con las
 * medidas que calcula la tienda para cada zona de la prenda, dice en qué zona queda mejor y redacta, para cada zona,
 * un aviso de calidad con el caso concreto. La IA no calcula nada: solo elige entre las zonas que se le dan y repite
 * las cifras que se le dan; un texto con una cifra que no venga de la tienda se descarta.
 * Un diseño se analiza una vez por prenda e idioma (se guarda 30 días); hay un máximo por visitante y día y el gasto
 * cuenta en el límite del mes de Ajustes → IA. Si la IA falla, el configurador sigue igual, sin consejo.
 */
final class ConsejoDiseno
{
    private const CAIDA = 'ia.consejo.caida';

    public function __construct(private readonly ClienteOpenAi $openAi) {}

    public static function disponible(): bool
    {
        return (bool) Ajuste::obtener('ia.consejo_disenos') && ClaveIa::configurada() && ! Cache::has(self::CAIDA)
            && AsistenteVenta::gastoDelMes() < (int) Ajuste::obtener('ia.limite_mensual') * 100 * 1000;
    }

    /**
     * Lo que calcula la tienda para cada zona de la prenda: su máximo, el tamaño al que el diseño la llena (con su
     * proporción), hasta dónde sale nítido y la calidad si se imprime llenando la zona. Todo en cm.
     *
     * @return list<array{id: int, nombre: string, maximo: string, llena: string, nitido: string, calidad: string}>
     */
    public static function medidas(Diseno $diseno, Prenda $prenda, string $idioma): array
    {
        $ids = FotoZona::whereIn('foto_id', Foto::where('prenda_id', $prenda->getKey())->where('tipo', TipoFoto::Maqueta->value)->pluck('id'))
            ->pluck('zona_impresion_id')->unique();
        $w = max(1, (int) $diseno->util_ancho_px);
        $h = max(1, (int) $diseno->util_alto_px);
        $proporcion = $w / $h;
        $ppp = max(1, Calidad::umbrales()['correcto']);

        return ZonaImpresion::whereIn('id', $ids)->orderBy('id')->get()->map(function (ZonaImpresion $zona) use ($w, $h, $proporcion, $ppp, $idioma): array {
            $anchoMax = (int) $zona->ancho_max_mm;
            $altoMax = (int) $zona->alto_max_mm;
            [$llenaW, $llenaH] = $proporcion > $anchoMax / max(1, $altoMax) ? [$anchoMax, $anchoMax / $proporcion] : [$altoMax * $proporcion, $altoMax];
            $escala = min(1, ($w / $ppp * 25.4) / max(1, $llenaW));

            return [
                'id' => (int) $zona->getKey(),
                'nombre' => (string) $zona->getTranslation('nombre', $idioma),
                'maximo' => self::cm($anchoMax).' × '.self::cm($altoMax).' cm',
                'llena' => self::cm($llenaW).' × '.self::cm($llenaH).' cm',
                'nitido' => self::cm($llenaW * $escala).' × '.self::cm($llenaH * $escala).' cm',
                'calidad' => Calidad::evaluar($w, $h, (int) round($llenaW), (int) round($llenaH))['nivel']->value,
            ];
        })->values()->all();
    }

    /**
     * @return ?array{zona: ?int, motivo: string, consejos: array<int, string>} null si no hay consejo (IA apagada, sin zonas o fallo)
     */
    public function __invoke(Diseno $diseno, Prenda $prenda, string $idioma): ?array
    {
        $clave = 'consejo-diseno:'.$diseno->sha256.':'.$prenda->getKey().':'.$idioma.':'.Calidad::umbrales()['correcto'];
        if (($guardado = Cache::get($clave)) !== null) {
            return $guardado;
        }
        if (! self::disponible()) {
            return null;
        }
        $zonas = self::medidas($diseno, $prenda, $idioma);
        if ($zonas === [] || $diseno->miniatura === null || ! Storage::disk(GuardarDiseno::DISCO)->exists($diseno->miniatura)) {
            return null;
        }

        $datos = ['diseno' => ['proporcion' => round(max(1, (int) $diseno->util_ancho_px) / max(1, (int) $diseno->util_alto_px), 2), 'fondo' => $diseno->fondo],
            'ppp_optimo' => Calidad::umbrales()['correcto'], 'zonas' => $zonas];
        $imagen = 'data:image/webp;base64,'.base64_encode((string) Storage::disk(GuardarDiseno::DISCO)->get($diseno->miniatura));
        try {
            $r = $this->openAi->chat([
                ['role' => 'system', 'content' => self::instrucciones($idioma)],
                ['role' => 'user', 'content' => [
                    ['type' => 'text', 'text' => (string) json_encode($datos, JSON_UNESCAPED_UNICODE)],
                    ['type' => 'image_url', 'image_url' => ['url' => $imagen, 'detail' => 'low']],
                ]],
            ], json: true);
        } catch (IaNoDisponible) {
            Cache::put(self::CAIDA, true, now()->addMinutes(10)); // se apaga un rato; el configurador sigue igual

            return null;
        }
        ConsumoIa::create(['uso' => 'consejo_diseno', 'tokens_entrada' => $r['entrada'], 'tokens_salida' => $r['salida'], 'coste' => AsistenteVenta::coste($r['entrada'], $r['salida'])]);

        $consejo = self::validar((array) json_decode((string) ($r['mensaje']['content'] ?? ''), true), $zonas);
        Cache::put($clave, $consejo, now()->addDays(30));

        return $consejo;
    }

    /**
     * Solo zonas de la lista y textos cortos cuyas cifras salgan de la tienda.
     *
     * @param  array<string, mixed>  $respuesta
     * @param  list<array{id: int, nombre: string, maximo: string, llena: string, nitido: string, calidad: string}>  $zonas
     * @return array{zona: ?int, motivo: string, consejos: array<int, string>}
     */
    public static function validar(array $respuesta, array $zonas): array
    {
        $ids = array_column($zonas, 'id');
        $permitidas = [];
        foreach ($zonas as $z) {
            foreach ([$z['maximo'], $z['llena'], $z['nitido']] as $medida) {
                preg_match_all('/\d+(?:,\d+)?/', $medida, $m);
                array_push($permitidas, ...$m[0]);
            }
        }
        $permitidas[] = (string) Calidad::umbrales()['correcto'];
        $limpio = function (mixed $texto, int $max) use ($permitidas): string {
            $texto = trim(strip_tags((string) $texto));
            if ($texto === '' || mb_strlen($texto) > $max) {
                return '';
            }
            preg_match_all('/\d+(?:[.,]\d+)?/', $texto, $m);
            foreach ($m[0] as $cifra) {
                if (! in_array(str_replace('.', ',', $cifra), $permitidas, true)) {
                    return ''; // una cifra que no ha dado la tienda: fuera
                }
            }

            return $texto;
        };

        $zona = (int) ($respuesta['zona'] ?? 0);
        $consejos = [];
        foreach ((array) ($respuesta['consejos'] ?? []) as $id => $texto) {
            if (in_array((int) $id, $ids, true) && ($t = $limpio($texto, 320)) !== '') {
                $consejos[(int) $id] = $t;
            }
        }

        return [
            'zona' => in_array($zona, $ids, true) ? $zona : null,
            'motivo' => in_array($zona, $ids, true) ? $limpio($respuesta['motivo'] ?? '', 220) : '',
            'consejos' => $consejos,
        ];
    }

    private static function instrucciones(string $idioma): string
    {
        $lengua = $idioma === 'en' ? 'English' : 'castellano de España';

        return 'Eres el asesor de impresión de una tienda de ropa personalizada (impresión DTF/DTI). Te paso la imagen de un diseño '
            .'y, en JSON, las zonas de impresión de la prenda con lo que ha calculado la tienda: «maximo» (tamaño máximo de la zona), '
            .'«llena» (tamaño al que el diseño llena la zona con su proporción), «nitido» (hasta dónde sale nítido con su resolución) '
            .'y «calidad» si se imprime llenando la zona (correcta, buena, justa o baja). '
            .'1) Elige la zona donde mejor queda el diseño por su forma, su detalle y su resolución (un logo pequeño o apaisado suele ir '
            .'mejor en el pecho; una ilustración grande y con detalle, en la frontal o la espalda si su calidad lo permite). '
            .'2) Para cada zona, escribe un consejo breve (una o dos frases) que hable de este diseño en concreto (qué se ve en él) y de '
            .'su calidad en esa zona: si sale nítido, dilo; si no, di hasta qué tamaño sale nítido y que lo haga más grande en su '
            .'programa de edición. '
            .'Habla siempre en centímetros, nunca en píxeles. Usa SOLO las cifras del JSON, copiadas tal cual; no calcules ni inventes '
            .'otras. No hables de precios. Escribe en '.$lengua.', de tú, claro y amable. '
            .'Responde solo con JSON: {"zona": <id>, "motivo": "<una frase: por qué esa zona>", "consejos": {"<id>": "<consejo>", ...}}.';
    }

    private static function cm(float $mm): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format($mm / 10, 1, '.', ''), '0'), '.'));
    }
}
