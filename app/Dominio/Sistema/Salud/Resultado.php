<?php

namespace App\Dominio\Sistema\Salud;

/**
 * Resultado de una comprobación de salud.
 */
final class Resultado
{
    public function __construct(
        public readonly string $clave,
        public readonly string $nombre,
        public readonly EstadoSalud $estado,
        public readonly string $mensaje,
    ) {}

    public static function correcto(string $mensaje): array
    {
        return [EstadoSalud::Correcto, $mensaje];
    }

    public static function aviso(string $mensaje): array
    {
        return [EstadoSalud::Aviso, $mensaje];
    }

    public static function fallo(string $mensaje): array
    {
        return [EstadoSalud::Fallo, $mensaje];
    }

    /** @return array{clave: string, nombre: string, estado: string, mensaje: string} */
    public function aArray(): array
    {
        return [
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'estado' => $this->estado->value,
            'mensaje' => $this->mensaje,
        ];
    }

    /** @param  array{clave: string, nombre: string, estado: string, mensaje: string}  $datos */
    public static function desdeArray(array $datos): self
    {
        return new self($datos['clave'], $datos['nombre'], EstadoSalud::from($datos['estado']), $datos['mensaje']);
    }
}
