<?php

namespace App\Dominio\Pagos;

use RuntimeException;

/** La pasarela no está configurada o no admite el cobro. */
final class PagoNoDisponible extends RuntimeException {}
