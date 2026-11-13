<?php

namespace App\Dominio\Pedidos;

use App\Dominio\Precios\Formato;
use App\Models\Ajuste;
use App\Models\Pedido;

/**
 * Plantillas de los correos del pedido (paso 6.4): qué estados avisan al cliente y con qué
 * asunto y texto en cada idioma. Lo que se escribe en Ajustes → Correos manda; lo vacío,
 * el texto por defecto de lang/{es,en}/correos.php. Regla 4: nada de esto va en el código.
 */
final class CorreosPedido
{
    /** Estados que pueden avisar al cliente (el pendiente de pago lo cubre el enlace de confirmación). */
    public const ESTADOS = [
        EstadoPedido::Pagado, EstadoPedido::DisenoEnRevision, EstadoPedido::RequiereCambios, EstadoPedido::EnProduccion,
        EstadoPedido::Enviado, EstadoPedido::Entregado, EstadoPedido::Cancelado,
    ];

    public static function activo(EstadoPedido $estado): bool
    {
        return in_array($estado->value, (array) Ajuste::obtener('correo.estados_activos'), true);
    }

    /** @param  'asunto'|'texto'  $parte */
    public static function plantilla(EstadoPedido|string $clave, string $parte, string $idioma): string
    {
        $clave = $clave instanceof EstadoPedido ? $clave->value : $clave;
        $propio = trim((string) (((array) Ajuste::obtener('correo.plantillas'))[$clave][$parte][$idioma] ?? ''));

        return $propio !== '' ? $propio : self::porDefecto($clave, $parte, $idioma);
    }

    public static function porDefecto(string $clave, string $parte, string $idioma): string
    {
        return trans("correos.{$clave}_{$parte}", [], $idioma);
    }

    /** Sustituye los huecos con los datos del pedido, en su idioma. */
    public static function rellenar(string $texto, Pedido $pedido): string
    {
        $idioma = $pedido->idioma;
        $fecha = $pedido->fecha_estimada_entrega?->locale($idioma)->isoFormat('LL') ?? trans('correos.sin_fecha', [], $idioma);

        return strtr($texto, [
            ':nombre' => (string) ($pedido->direccion_envio['nombre'] ?? $pedido->nombre_fiscal),
            ':numero' => (string) $pedido->numero,
            ':estado' => $pedido->estado->visible($idioma),
            ':total' => Formato::euros($pedido->total, $idioma),
            ':fecha_entrega' => $fecha,
            ':transportista' => (string) $pedido->transportista,
            ':seguimiento' => (string) $pedido->numero_seguimiento,
            ':enlace_seguimiento' => (string) $pedido->url_seguimiento, // D226
        ]);
    }
}
