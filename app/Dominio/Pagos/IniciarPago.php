<?php

namespace App\Dominio\Pagos;

use App\Dominio\Pedidos\EstadoPedido;
use App\Models\Pago;
use App\Models\Pedido;

/**
 * Prepara el cobro de un pedido sin pagar (D181): el total que calculó el motor, en la pasarela activa.
 * Cada visita a la página de pago es un intento nuevo; solo se cobra si el cliente paga allí.
 */
final class IniciarPago
{
    public function __construct(private readonly Pasarela $pasarela) {}

    public function __invoke(Pedido $pedido, ?string $metodo, string $urlVuelta, ?string $urlAviso): PagoIniciado
    {
        if ($pedido->estado !== EstadoPedido::PendientePago || ! $pedido->correoConfirmado()) {
            throw new PagoNoDisponible('El pedido no se puede pagar ahora.');
        }
        $metodos = $this->pasarela->metodos();
        $metodo = isset($metodos[(string) $metodo]) ? (string) $metodo : (string) array_key_first($metodos);

        $iniciado = $this->pasarela->iniciar(new PeticionPago($pedido->id, (string) $pedido->numero, $pedido->total, $pedido->idioma, $urlVuelta, $urlAviso, $metodo));
        Pago::create(['pedido_id' => $pedido->id, 'pasarela' => $this->pasarela->nombre(), 'metodo' => $metodo, 'referencia' => $iniciado->referencia,
            'importe' => $pedido->total, 'estado' => 'pendiente', 'pruebas' => $this->pasarela->pruebas()]);

        return $iniciado;
    }
}
