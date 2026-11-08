<?php

namespace App\Dominio\Clientes;

use App\Models\Cliente;
use App\Models\Direccion;
use Illuminate\Support\Facades\DB;

/**
 * Direcciones de un cliente (paso 5.3): varias de cada tipo y una predeterminada por
 * tipo. La primera de cada tipo es la predeterminada; si se borra la predeterminada,
 * pasa a serlo la más reciente que quede.
 */
final class Direcciones
{
    /** @param array<string, mixed> $datos */
    public function guardar(Cliente $cliente, array $datos, ?Direccion $direccion = null): Direccion
    {
        return DB::transaction(function () use ($cliente, $datos, $direccion): Direccion {
            Cliente::whereKey($cliente->id)->lockForUpdate()->first(); // una sola predeterminada aunque lleguen dos a la vez (B5)
            $tipo = $datos['tipo'] instanceof TipoDireccion ? $datos['tipo'] : TipoDireccion::from($datos['tipo']);
            $quiereSerlo = (bool) ($datos['predeterminada'] ?? false);
            unset($datos['predeterminada']);

            $direccion ??= new Direccion(['cliente_id' => $cliente->id]);
            $tipoAnterior = $direccion->exists ? $direccion->tipo : null;
            $direccion->fill($datos);
            $sinOtras = ! $cliente->direcciones()->where('tipo', $tipo)->whereKeyNot($direccion->id)->exists();
            $direccion->predeterminada = $quiereSerlo || $sinOtras || ($direccion->predeterminada && $tipoAnterior === $tipo);
            $direccion->save();

            if ($direccion->predeterminada) {
                $this->unicaPredeterminada($cliente, $direccion);
            }
            if ($tipoAnterior !== null && $tipoAnterior !== $tipo) {
                $this->asegurarPredeterminada($cliente, $tipoAnterior);
            }

            return $direccion;
        });
    }

    public function predeterminar(Direccion $direccion): void
    {
        DB::transaction(function () use ($direccion): void {
            Cliente::whereKey($direccion->cliente_id)->lockForUpdate()->first();
            $direccion->update(['predeterminada' => true]);
            $this->unicaPredeterminada($direccion->cliente, $direccion);
        });
    }

    public function borrar(Direccion $direccion): void
    {
        DB::transaction(function () use ($direccion): void {
            $direccion->delete();
            $this->asegurarPredeterminada($direccion->cliente, $direccion->tipo);
        });
    }

    private function unicaPredeterminada(Cliente $cliente, Direccion $direccion): void
    {
        $cliente->direcciones()->where('tipo', $direccion->tipo)->whereKeyNot($direccion->id)->update(['predeterminada' => false]);
    }

    private function asegurarPredeterminada(Cliente $cliente, TipoDireccion $tipo): void
    {
        $delTipo = $cliente->direcciones()->where('tipo', $tipo);
        if (! (clone $delTipo)->where('predeterminada', true)->exists()) {
            (clone $delTipo)->latest('id')->first()?->update(['predeterminada' => true]);
        }
    }
}
