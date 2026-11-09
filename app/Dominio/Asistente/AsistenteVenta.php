<?php

namespace App\Dominio\Asistente;

use App\Models\Ajuste;
use App\Models\Cliente;
use App\Models\ConsumoIa;
use Illuminate\Support\Facades\Cache;

/**
 * Asistente IA de venta (v2, D194): solo clientes con cuenta, guía en pasos y consulta el motor con herramientas.
 * Límites de mensajes por cliente y día y de gasto del mes (Ajustes → IA); si la IA falla, se oculta 10 minutos.
 */
final class AsistenteVenta
{
    private const CAIDA = 'ia.caida';

    private const MAX_RONDAS = 4;

    public function __construct(private readonly ClienteOpenAi $openAi, private readonly HerramientasVenta $herramientas) {}

    public static function disponible(?Cliente $cliente): bool
    {
        return $cliente !== null && $cliente->activo
            && (bool) Ajuste::obtener('ia.activa') && filled(config('ia.openai_key')) && ! Cache::has(self::CAIDA)
            && self::gastoDelMes() < (int) Ajuste::obtener('ia.limite_mensual') * 100 * 1000; // euros → milésimas de céntimo
    }

    /** Milésimas de céntimo gastadas este mes (venta y SEO). */
    public static function gastoDelMes(): int
    {
        return (int) ConsumoIa::where('created_at', '>=', now()->startOfMonth())->sum('coste');
    }

    /**
     * @param  list<array{rol: string, texto: string}>  $historial  lo que se ha dicho, el último del cliente
     */
    public function __invoke(Cliente $cliente, array $historial, string $idioma): string
    {
        if (! self::disponible($cliente)) {
            throw new IaNoDisponible('Asistente no disponible.');
        }
        $hoy = ConsumoIa::where('cliente_id', $cliente->id)->where('uso', 'venta')->where('created_at', '>=', now()->startOfDay())->count();
        if ($hoy >= (int) Ajuste::obtener('ia.mensajes_dia')) {
            return __('asistente.limite_dia', [], $idioma);
        }

        $mensajes = [['role' => 'system', 'content' => self::instrucciones($idioma)]];
        foreach (array_slice($historial, -12) as $m) {
            $mensajes[] = ['role' => $m['rol'] === 'asistente' ? 'assistant' : 'user', 'content' => mb_substr($m['texto'], 0, 1000)];
        }
        $entrada = 0;
        $salida = 0;
        try {
            for ($ronda = 0; $ronda < self::MAX_RONDAS; $ronda++) {
                $r = $this->openAi->chat($mensajes, HerramientasVenta::definiciones());
                $entrada += $r['entrada'];
                $salida += $r['salida'];
                $llamadas = (array) ($r['mensaje']['tool_calls'] ?? []);
                if ($llamadas === []) {
                    return trim((string) ($r['mensaje']['content'] ?? ''));
                }
                $mensajes[] = ['role' => 'assistant', 'content' => $r['mensaje']['content'] ?? null, 'tool_calls' => $llamadas];
                foreach ($llamadas as $llamada) {
                    $argumentos = json_decode((string) ($llamada['function']['arguments'] ?? '{}'), true);
                    $resultado = $this->herramientas->ejecutar((string) ($llamada['function']['name'] ?? ''), is_array($argumentos) ? $argumentos : [], $idioma);
                    $mensajes[] = ['role' => 'tool', 'tool_call_id' => (string) ($llamada['id'] ?? ''), 'content' => (string) json_encode($resultado, JSON_UNESCAPED_UNICODE)];
                }
            }

            return __('asistente.no_se', [], $idioma);
        } catch (IaNoDisponible $e) {
            Cache::put(self::CAIDA, true, now()->addMinutes(10)); // se oculta un rato: la tienda sigue vendiendo
            report($e);

            throw $e;
        } finally {
            if ($entrada + $salida > 0) {
                ConsumoIa::create(['cliente_id' => $cliente->id, 'uso' => 'venta', 'tokens_entrada' => $entrada, 'tokens_salida' => $salida, 'coste' => self::coste($entrada, $salida)]);
            }
        }
    }

    /** Coste en milésimas de céntimo con los precios por millón de tokens de Ajustes → IA (en céntimos). */
    public static function coste(int $entrada, int $salida): int
    {
        return intdiv($entrada * (int) Ajuste::obtener('ia.precio_entrada') + $salida * (int) Ajuste::obtener('ia.precio_salida') + 999, 1000);
    }

    private static function instrucciones(string $idioma): string
    {
        $tienda = Ajuste::obtener('tienda.nombre');

        return "Eres el asistente de venta de {$tienda}, una tienda de prendas personalizadas con impresión DTI (camisetas, polos, sudaderas) para pequeños diseñadores. "
            .'Responde en '.($idioma === 'en' ? 'inglés' : 'castellano de España').', breve y amable. Guía en pasos: qué prenda, qué color, dónde va el diseño, cuántas por talla. '
            .'NUNCA digas un precio, un mínimo, un descuento ni un impuesto que no venga de las herramientas: usa calcular_precio o packs y repite sus cifras tal cual. '
            .'Si falta un dato para calcular, pregúntalo. No pidas datos personales ni de pago. Para comprar, el cliente usa el configurador de la prenda y su carrito.';
    }
}
