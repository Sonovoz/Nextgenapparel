<?php

namespace App\Dominio\Marketing;

use App\Dominio\Pedidos\EstadoPedido;
use App\Http\Controllers\PedidoController;
use App\Models\Ajuste;
use App\Models\Campana;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\EnvioCampana;
use App\Models\Pedido;
use App\Notifications\Marketing\CorreoComercial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * Correos comerciales (v2, D190), solo a clientes con cuenta, correo verificado, ficha activa y consentimiento
 * comercial, y nunca dos veces el mismo:
 * - **Nueva compra**: a los días de Ajustes tras la entrega (30), con el enlace a su pedido para repetirlo.
 * - **Reactivación**: a quien no compra desde hace los días de Ajustes (180), con cupón opcional; una vez por periodo.
 * - **Campañas** manuales por segmento (todos, con pedidos, sin pedidos), con cupón opcional.
 */
final class Campanas
{
    /** @return Builder<Cliente> */
    public static function destinatarios(): Builder
    {
        return Cliente::query()->where('activo', true)->whereNotNull('marketing_consentido_en')
            ->whereHas('usuario', fn ($u) => $u->whereNotNull('email_verified_at')->where('activo', true));
    }

    public function nuevaCompra(): int
    {
        if (! (bool) Ajuste::obtener('campanas.nueva_compra_activa')) {
            return 0;
        }
        $limite = now()->subDays(max(1, (int) Ajuste::obtener('campanas.nueva_compra_dias')));
        $enviados = 0;
        Pedido::where('estado', EstadoPedido::Entregado->value)
            ->whereIn('cliente_id', self::destinatarios()->select('id'))
            ->whereHas('historial', fn ($h) => $h->where('estado', EstadoPedido::Entregado->value)->where('created_at', '<=', $limite))
            ->whereNotIn('id', EnvioCampana::where('tipo', 'nueva_compra')->whereNotNull('pedido_id')->select('pedido_id'))
            ->with('cliente')->lazyById()->each(function (Pedido $pedido) use (&$enviados): void {
                $cliente = $pedido->cliente;
                if ($cliente === null) {
                    return;
                }
                $i = $cliente->idioma ?? 'es';
                $this->enviar('nueva_compra', $cliente, trans('marketing.nueva_compra_asunto', [], $i), trans('marketing.nueva_compra_texto', ['numero' => $pedido->numero], $i),
                    trans('marketing.nueva_compra_boton', [], $i), PedidoController::enlacePrivado($pedido), null, pedidoId: $pedido->id);
                $enviados++;
            });

        return $enviados;
    }

    public function reactivacion(): int
    {
        if (! (bool) Ajuste::obtener('campanas.reactivacion_activa')) {
            return 0;
        }
        $dias = max(1, (int) Ajuste::obtener('campanas.reactivacion_dias'));
        $cupon = self::cuponValido(Ajuste::obtener('campanas.reactivacion_cupon'));
        $enviados = 0;
        self::destinatarios()->whereHas('pedidos')
            ->whereDoesntHave('pedidos', fn ($p) => $p->where('created_at', '>', now()->subDays($dias)))
            ->whereNotIn('id', EnvioCampana::where('tipo', 'reactivacion')->where('enviado_en', '>', now()->subDays($dias))->select('cliente_id'))
            ->lazyById()->each(function (Cliente $cliente) use ($cupon, &$enviados): void {
                $i = $cliente->idioma ?? 'es';
                $this->enviar('reactivacion', $cliente, trans('marketing.reactivacion_asunto', [], $i), trans('marketing.reactivacion_texto', [], $i),
                    trans('marketing.reactivacion_boton', [], $i), route('portada', ['idioma' => $i]), $cupon);
                $enviados++;
            });

        return $enviados;
    }

    /** Envía una campaña manual una sola vez. @return int enviados */
    public function campana(Campana $campana): int
    {
        if ($campana->enviada_en !== null) {
            return 0;
        }
        $campana->update(['enviada_en' => now()]); // primero: un doble clic no la manda dos veces
        $cupon = self::cuponValido($campana->cupon);
        $consulta = match ($campana->segmento) {
            'con_pedidos' => self::destinatarios()->whereHas('pedidos'),
            'sin_pedidos' => self::destinatarios()->whereDoesntHave('pedidos'),
            default => self::destinatarios(),
        };
        $enviados = 0;
        $consulta->lazyById()->each(function (Cliente $cliente) use ($campana, $cupon, &$enviados): void {
            $i = $cliente->idioma ?? 'es';
            $this->enviar('campana', $cliente, (string) $campana->getTranslation('asunto', $i), (string) $campana->getTranslation('texto', $i),
                trans('marketing.campana_boton', [], $i), route('portada', ['idioma' => $i]), $cupon, campanaId: $campana->id);
            $enviados++;
        });
        $campana->update(['enviados' => $enviados]);

        return $enviados;
    }

    private static function cuponValido(mixed $codigo): ?string
    {
        return filled($codigo) && Cupon::where('codigo', $codigo)->where('activo', true)->exists() ? (string) $codigo : null;
    }

    private function enviar(string $tipo, Cliente $cliente, string $asunto, string $texto, string $boton, string $enlace, ?string $cupon, ?int $pedidoId = null, ?int $campanaId = null): void
    {
        EnvioCampana::create(['tipo' => $tipo, 'cliente_id' => $cliente->id, 'pedido_id' => $pedidoId, 'campana_id' => $campanaId, 'enviado_en' => now()]);
        Notification::route('mail', $cliente->correo)->notify((new CorreoComercial($cliente, $asunto, $texto, $boton, $enlace, $cupon))->locale($cliente->idioma ?? 'es'));
    }
}
