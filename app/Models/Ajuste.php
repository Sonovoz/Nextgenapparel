<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Ajustes configurables desde el backend (regla 4). Se guardan en la base de
 * datos y se leen con caché; si la base de datos no responde, se usa el valor
 * por defecto para que la tienda siga funcionando.
 */
class Ajuste extends Model
{
    /** Valores por defecto mientras no se cambien en el backend. */
    public const POR_DEFECTO = [
        'tienda.nombre' => 'Nextgenapparel',
        'tienda.correo_contacto' => null,
        // IVA general provisional hasta la fase 3, en centésimas de % (2100 = 21 %) (D75).
        'impuestos.iva_general' => 2100,
        // Calidad de los diseños: ppp efectivo mínimos de cada nivel (ficha § 7).
        'disenos.ppp_correcto' => 300,
        'disenos.ppp_aceptable' => 200,
        'disenos.ppp_minimo' => 150,
        // Archivos de diseño (fase 4): tamaño máximo, píxeles máximos del lado mayor,
        // mínimos del lado menor de lo que se imprime y días que se guardan sin pedido.
        'disenos.max_mb' => 30,
        'disenos.max_px' => 8000, // 67 cm a 300 ppp; más solo cuesta memoria y tiempo
        'disenos.min_px' => 300,
        'disenos.dias_sin_pedido' => 30,
        // Frenos al abuso: subidas por hora e IP y diseños nuevos por visitante y día.
        'disenos.subidas_hora' => 60,
        'disenos.max_dia' => 40,
        // Carrito (paso 5.5): días que se guarda desde su último uso, con cuenta o sin ella.
        'carrito.dias' => 30,
        // Pedidos (D169): prefijo del número y días que vale el enlace para que un invitado confirme su correo.
        'pedidos.prefijo' => 'PED-',
        'pedidos.dias_confirmar_correo' => 7,
        // Paso 6.3: días sin pagar tras los que el pedido se cancela solo (y devuelve el cupón, D173),
        // y días extra de producción según las unidades: [{desde: 100, dias: 2}, …] (vacío: ninguno).
        'pedidos.dias_sin_pago' => 7,
        'pedidos.plazo_tramos' => [],
        // Paso 6.6b: días desde la entrega (o el envío) para abrir una incidencia (D178).
        'incidencias.dias' => 14,
        // Paso 7.2 (D182): el cliente cancela un pedido pagado y sin producir al momento (devolución automática) o lo pide al equipo.
        'pagos.cancelacion_automatica' => false,
        // D187: días que vale un presupuesto.
        'presupuestos.dias_validez' => 15,
        // Paso 6.4: SMTP del panel (contraseña cifrada), estados que avisan al cliente, plantillas
        // propias por estado e idioma y nombres visibles de los estados (vacíos: los de lang/).
        'correo.smtp' => [],
        'correo.estados_activos' => ['pagado', 'requiere_cambios', 'enviado', 'cancelado'],
        'correo.plantillas' => [],
        'pedidos.estados_visibles' => [],
        // Facturación (D175): datos fiscales de la tienda y series (no se cambian tras la primera factura).
        'facturacion.emisor' => [],
        'facturacion.series' => ['completa' => 'F', 'simplificada' => 'T', 'rectificativa' => 'R'],
        // Recargo de equivalencia (paso 5.4): «equipo» (lo activa el equipo en la ficha) o «cliente» (lo marca él).
        'clientes.recargo_lo_indica' => 'equipo',
        // Mejora con IA para el cliente (D143): activa, desde qué nivel y texto propio por idioma (vacío = el de lang/disenos.php).
        'disenos.ia_activa' => true,
        'disenos.ia_desde' => 'justa',
        'disenos.ia_texto' => null,
        // Ofertas: avisar «te faltan…» desde este avance (centésimas de %, 5000 = 50 %).
        'ofertas.aviso_cercana' => 5000,
        // Buscadores: apagado hasta la puesta en marcha; solo cuenta en producción (D102).
        'seo.indexar' => false,
    ];

    protected $table = 'ajustes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['valor' => 'json'];
    }

    public static function obtener(string $clave): mixed
    {
        try {
            $valores = Cache::rememberForever('ajustes', fn () => static::pluck('valor', 'clave')->all());
        } catch (Throwable) {
            $valores = [];
        }

        return array_key_exists($clave, $valores) ? $valores[$clave] : (self::POR_DEFECTO[$clave] ?? null);
    }

    public static function guardar(string $clave, mixed $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        Cache::forget('ajustes');
    }
}
