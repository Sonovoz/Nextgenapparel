<?php

namespace App\Dominio\Asistente;

use RuntimeException;

/** La IA no responde, no está configurada o se ha llegado al límite: el asistente se oculta y la tienda sigue. */
final class IaNoDisponible extends RuntimeException {}
