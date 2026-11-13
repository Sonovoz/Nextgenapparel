<?php

namespace App\Dominio\Asistente;

use App\Dominio\Pedidos\EstadoPedido;
use App\Models\Ajuste;
use App\Models\Cliente;
use App\Models\ConsumoIa;
use App\Models\Pedido;
use Illuminate\Support\Facades\Cache;

/**
 * Asistente IA de venta (v2, D194): solo clientes con cuenta, guía en pasos y consulta el motor con herramientas.
 * Límites de mensajes por cliente y día y de gasto del mes (Ajustes → IA); si la IA falla, se oculta 10 minutos.
 * D218: solo con el correo confirmado; quien aún no ha comprado tiene una cuota moderada (Ajustes → IA) y a
 * quien ya ha comprado se le recuerdan sus pedidos (prendas y unidades, sin datos personales) y se le anima a
 * repetir o ampliar su colección.
 */
final class AsistenteVenta
{
    private const CAIDA = 'ia.caida';

    private const MAX_RONDAS = 4;

    public function __construct(private readonly ClienteOpenAi $openAi, private readonly HerramientasVenta $herramientas) {}

    public static function disponible(?Cliente $cliente): bool
    {
        return $cliente !== null && $cliente->activo
            && $cliente->usuario?->hasVerifiedEmail() === true
            && (bool) Ajuste::obtener('ia.activa') && ClaveIa::configurada() && ! Cache::has(self::CAIDA)
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
        $pedidos = self::pedidosComprados($cliente);
        $hoy = ConsumoIa::where('cliente_id', $cliente->id)->where('uso', 'venta')->where('created_at', '>=', now()->startOfDay())->count();
        if ($hoy >= self::limiteDiario($pedidos !== [])) {
            return __($pedidos === [] ? 'asistente.limite_dia_nuevo' : 'asistente.limite_dia', [], $idioma);
        }

        $mensajes = [['role' => 'system', 'content' => self::instrucciones($idioma).self::memoria($pedidos)]];
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

    /** D218: mensajes al día; cuota moderada para quien aún no ha comprado. */
    public static function limiteDiario(bool $haComprado): int
    {
        return (int) Ajuste::obtener($haComprado ? 'ia.mensajes_dia' : 'ia.mensajes_dia_nuevos');
    }

    /**
     * Los últimos pedidos pagados del cliente, para recordárselos (D218): número, fecha y qué prendas, sin
     * importes (la IA no da precios) ni datos personales.
     *
     * @return list<string>
     */
    public static function pedidosComprados(Cliente $cliente): array
    {
        $noComprados = [EstadoPedido::PendientePago->value, EstadoPedido::Cancelado->value, EstadoPedido::Reembolsado->value];

        return Pedido::where('cliente_id', $cliente->id)->whereNotIn('estado', $noComprados)->with('lineas')->latest('id')->limit(5)->get()
            ->map(fn (Pedido $p) => $p->numero.' ('.$p->created_at->format('d/m/Y').'): '.$p->lineas
                ->map(fn ($l) => $l->nombreEn('es').(($c = $l->colorEn('es')) ? ' · '.$c : '').' × '.$l->unidades)->implode('; '))
            ->values()->all();
    }

    /** @param list<string> $pedidos */
    private static function memoria(array $pedidos): string
    {
        if ($pedidos === []) {
            return ' Este cliente aún no ha comprado: ayúdale a dar el primer paso con su colección, sin presionar.';
        }

        return ' Este cliente ya ha comprado con nosotros. Sus últimos pedidos: '.implode(' | ', $pedidos).'. '
            .'Recuérdaselos con naturalidad cuando venga al caso y anímale a repetir o a ampliar su colección (por ejemplo, la misma prenda en otro color '
            .'o con su diseño en una sudadera); en Mi cuenta → Pedidos puede repetir un pedido. No des importes de esos pedidos: si pregunta precios, usa las herramientas.';
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
