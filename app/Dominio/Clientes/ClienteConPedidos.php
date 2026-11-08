<?php

namespace App\Dominio\Clientes;

use RuntimeException;

/** Un cliente con pedidos no se borra (revisión N3 del 6.1, A2): sus pedidos llevan datos personales que hay que anonimizar con el criterio del asesor. */
final class ClienteConPedidos extends RuntimeException {}
