<?php

namespace App\Console\Commands;

use App\Dominio\Facturacion\Emisor;
use App\Dominio\Pedidos\EstadoPedido;
use App\Jobs\EmitirFacturaPedido;
use App\Models\Pedido;
use Illuminate\Console\Command;

/** Emite, en orden, las facturas de los pedidos pagados que aún no la tienen (p. ej. por faltar los datos fiscales). */
class EmitirFacturasPendientesCommand extends Command
{
    protected $signature = 'facturas:emitir-pendientes';

    protected $description = 'Emitir las facturas pendientes de los pedidos pagados';

    public function handle(): int
    {
        if (($falta = Emisor::falta()) !== []) {
            $this->warn('Faltan datos fiscales en Ajustes → Facturación: '.implode(', ', $falta).'.');

            return self::SUCCESS;
        }
        $pagados = array_map(fn (EstadoPedido $e) => $e->value, [EstadoPedido::Pagado, EstadoPedido::DisenoEnRevision, EstadoPedido::RequiereCambios,
            EstadoPedido::EnProduccion, EstadoPedido::Enviado, EstadoPedido::Entregado]);
        $n = 0;
        Pedido::whereIn('estado', $pagados)->whereDoesntHave('facturas')->orderBy('id')->each(function (Pedido $pedido) use (&$n) {
            EmitirFacturaPedido::dispatchSync($pedido->id);
            $n++;
        });
        $this->info("Facturas emitidas: {$n}");

        return self::SUCCESS;
    }
}
