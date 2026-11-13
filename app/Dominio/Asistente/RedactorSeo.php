<?php

namespace App\Dominio\Asistente;

use App\Models\Ajuste;
use App\Models\ConsumoIa;
use Illuminate\Support\Facades\Cache;

/**
 * Asistente SEO (v2, D195), como Yoast: a partir de los datos reales (nombre, descripción, contenido), OpenAI
 * **propone** título SEO y metadescripción en es y en, y si se pide, la traducción al inglés. Solo rellena el
 * formulario: nada se guarda sin que alguien lo revise y pulse «Guardar» (aprobación obligatoria).
 */
final class RedactorSeo
{
    public function __construct(private readonly ClienteOpenAi $openAi) {}

    public static function disponible(): bool
    {
        return ClaveIa::configurada() && ! Cache::has('ia.caida') && AsistenteVenta::gastoDelMes() < (int) Ajuste::obtener('ia.limite_mensual') * 100 * 1000;
    }

    /**
     * @param  array<string, string>  $datos  campo => texto en español (nombre, descripcion, contenido…)
     * @param  list<string>  $traducir  campos de $datos que hay que traducir al inglés
     * @return array{seo_titulo: array{es: string, en: string}, seo_descripcion: array{es: string, en: string}, traducciones: array<string, string>}
     */
    public function proponer(string $que, array $datos, array $traducir = []): array
    {
        if (! self::disponible()) {
            throw new IaNoDisponible('La IA no está disponible (clave, límite del mes o caída).');
        }
        $pedido = 'Eres el redactor SEO de una tienda española de prendas personalizadas con impresión DTI. Con estos datos reales de '.$que
            .' (no inventes características, precios ni promesas), devuelve un JSON con: "seo_titulo": {"es", "en"} de 60 caracteres como mucho, "seo_descripcion": {"es", "en"} de 155 como mucho, '
            .'y "traducciones": {campo: texto en inglés} para estos campos: '.implode(', ', $traducir).'. Castellano de España e inglés natural.';
        try {
            $r = $this->openAi->chat([['role' => 'system', 'content' => $pedido], ['role' => 'user', 'content' => (string) json_encode($datos, JSON_UNESCAPED_UNICODE)]], json: true);
        } catch (IaNoDisponible $e) {
            Cache::put('ia.caida', true, now()->addMinutes(10));

            throw $e;
        }
        ConsumoIa::create(['uso' => 'seo', 'tokens_entrada' => $r['entrada'], 'tokens_salida' => $r['salida'], 'coste' => AsistenteVenta::coste($r['entrada'], $r['salida'])]);
        $json = json_decode((string) ($r['mensaje']['content'] ?? ''), true);
        if (! is_array($json)) {
            throw new IaNoDisponible('La IA no ha devuelto una propuesta válida.');
        }
        $texto = fn ($v, int $max) => mb_substr(trim((string) $v), 0, $max);

        return [
            'seo_titulo' => ['es' => $texto($json['seo_titulo']['es'] ?? '', 60), 'en' => $texto($json['seo_titulo']['en'] ?? '', 60)],
            'seo_descripcion' => ['es' => $texto($json['seo_descripcion']['es'] ?? '', 155), 'en' => $texto($json['seo_descripcion']['en'] ?? '', 155)],
            'traducciones' => array_map(fn ($v) => trim((string) $v), array_intersect_key((array) ($json['traducciones'] ?? []), array_flip($traducir))),
        ];
    }
}
