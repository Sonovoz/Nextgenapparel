<?php

namespace App\Dominio\Pedidos;

use App\Models\Cliente;
use App\Models\Pedido;
use Illuminate\Support\Facades\DB;

/**
 * El invitado abre el enlace del correo (D166, D169): el correo es suyo, así que el
 * pedido pasa a la ficha con ese correo (la de su cuenta, si la tiene, o la de
 * invitado, que se crea ahora). Antes de este paso el pedido no es de nadie: escribir
 * el correo de otro no cuelga pedidos en su cuenta. Si después se registra con ese
 * correo, su ficha de invitado (con sus pedidos) pasa a la cuenta (VincularCliente).
 */
final class ConfirmarCorreoPedido
{
    public function __invoke(Pedido $pedido): void
    {
        DB::transaction(function () use ($pedido) {
            $pedido = Pedido::whereKey($pedido->getKey())->lockForUpdate()->firstOrFail();
            if ($pedido->correoConfirmado()) {
                return;
            }
            $cliente = Cliente::where('correo', $pedido->correo)->lockForUpdate()->first()
                ?? Cliente::create([
                    'correo' => $pedido->correo,
                    'tipo' => $pedido->tipo_cliente,
                    'nombre' => $pedido->nombre_fiscal,
                    'nif' => $pedido->nif,
                    'recargo_equivalencia' => $pedido->recargo_equivalencia,
                    'telefono' => $pedido->direccion_envio['telefono'] ?? null,
                    'idioma' => $pedido->idioma,
                ]);
            $pedido->update(['correo_confirmado_en' => now(), 'cliente_id' => $cliente->id]);
            $pedido->historial()->create(['estado' => $pedido->estado, 'nota' => 'El cliente confirmó su correo']);
        });
    }
}
