<?php

namespace App\Dominio\Pagos;

/**
 * Pasarela de pago (fase 7, D181), portada de Clave de Alma (ADR 0026): «redsys» o «simulada».
 * Los datos de la tarjeta nunca pasan por nuestro servidor.
 */
interface Pasarela
{
    public function nombre(): string;

    /** «Métodos de pago», como en WooCommerce: clave => nombre (es). @return array<string, string> */
    public function metodos(): array;

    /** Cobros de prueba (entorno de pruebas): no se cobra dinero de verdad. */
    public function pruebas(): bool;

    public function iniciar(PeticionPago $peticion): PagoIniciado;

    /**
     * El aviso o la vuelta del cliente, comprobada su firma. Lanza AvisoNoValido si no es auténtico;
     * null si no trae ningún resultado.
     *
     * @param  array<string, mixed>  $campos
     */
    public function evento(array $campos): ?EventoPasarela;

    /** Devuelve (todo o parte de) un cobro hecho. */
    public function devolver(string $referencia, int $importe, string $clave): ResultadoDevolucion;
}
