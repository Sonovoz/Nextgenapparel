<?php

namespace App\Dominio\Catalogo;

use InvalidArgumentException;

/** Un dato del catálogo incumple una regla (se detiene antes de guardarlo). */
class DatoCatalogoInvalido extends InvalidArgumentException {}
