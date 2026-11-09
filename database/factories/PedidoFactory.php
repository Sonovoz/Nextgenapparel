<?php

namespace Database\Factories;

use App\Dominio\Pedidos\EstadoPedido;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Un pedido de invitado con el correo confirmado, sin líneas (los tests añaden las suyas). */
class PedidoFactory extends Factory
{
    public function definition(): array
    {
        static $n = 0;
        $n++;
        $direccion = ['nombre' => 'Lucía Pérez', 'empresa' => null, 'direccion' => 'Calle Luna 3', 'direccion_2' => null,
            'codigo_postal' => '28004', 'localidad' => 'Madrid', 'provincia' => null, 'pais' => 'ES', 'telefono' => '600123123'];

        return [
            'numero' => sprintf('PED-%04d', $n), 'estado' => EstadoPedido::PendientePago, 'idioma' => 'es',
            'correo' => "cliente{$n}@ejemplo.com", 'correo_confirmado_en' => now(), 'tipo_cliente' => 'particular', 'nombre_fiscal' => 'Lucía Pérez',
            'direccion_envio' => $direccion, 'direccion_facturacion' => $direccion,
            'bruto' => 10500, 'descuentos' => 0, 'subtotal' => 10500, 'envio' => 490, 'base_imponible' => 10990, 'impuestos' => 2308, 'total' => 13298,
            'desglose' => ['pasos' => []], 'condiciones_aceptadas_en' => now(), 'condiciones_texto' => 'Acepto',
        ];
    }
}
