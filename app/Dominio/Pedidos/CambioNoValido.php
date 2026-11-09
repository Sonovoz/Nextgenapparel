<?php

namespace App\Dominio\Pedidos;

use RuntimeException;

/** Un cambio de estado que no se puede hacer; el mensaje es para el equipo (backend en español). */
final class CambioNoValido extends RuntimeException {}
