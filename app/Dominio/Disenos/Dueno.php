<?php

namespace App\Dominio\Disenos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Dueño de los diseños de la petición (fase 5): el cliente si ha entrado en su
 * cuenta y, si no, la sesión del visitante (Propietario). Al entrar en la cuenta,
 * los diseños de la sesión pasan al cliente (TraspasarDisenos).
 */
final readonly class Dueno
{
    private function __construct(public ?int $clienteId, public ?string $propietario) {}

    /** Sin `crear`, null si un visitante sin cuenta todavía no tiene diseños. */
    public static function de(Request $request, bool $crear = true): ?self
    {
        $cliente = $request->user()?->cliente;
        if ($cliente !== null) {
            return new self($cliente->id, null);
        }

        $hash = Propietario::hash($request, $crear);

        return $hash === null ? null : new self(null, $hash);
    }

    public static function cliente(int $clienteId): self
    {
        return new self($clienteId, null);
    }

    public static function sesion(string $propietario): self
    {
        return new self(null, $propietario);
    }

    /** @template T of Builder @param T $consulta @return T */
    public function filtrar(Builder $consulta): Builder
    {
        return $this->clienteId !== null
            ? $consulta->where('cliente_id', $this->clienteId)
            : $consulta->whereNull('cliente_id')->where('propietario', $this->propietario);
    }

    /** @return array{cliente_id: ?int, propietario: ?string} */
    public function columnas(): array
    {
        return ['cliente_id' => $this->clienteId, 'propietario' => $this->propietario];
    }
}
