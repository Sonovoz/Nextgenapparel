<?php

namespace App\Dominio\Pagos;

use RuntimeException;

/** Lo que dice venir de la pasarela no está firmado por ella. */
final class AvisoNoValido extends RuntimeException {}
