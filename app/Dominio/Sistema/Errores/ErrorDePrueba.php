<?php

namespace App\Dominio\Sistema\Errores;

use RuntimeException;

/**
 * Error provocado a propósito desde el backend o con
 * `php artisan nga:error-prueba` para comprobar que el log de errores y
 * las alertas funcionan. Se trata como crítico.
 */
class ErrorDePrueba extends RuntimeException {}
