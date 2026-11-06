<?php

namespace App\Dominio\Sistema\Errores;

use RuntimeException;

/** Fallo al hacer o probar una copia de seguridad. Se trata como crítico. */
class ErrorDeCopia extends RuntimeException {}
