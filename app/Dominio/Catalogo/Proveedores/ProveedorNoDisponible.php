<?php

namespace App\Dominio\Catalogo\Proveedores;

use RuntimeException;

/** La API del proveedor no responde, rechaza las credenciales o falta configurarla. El mensaje es para el equipo. */
final class ProveedorNoDisponible extends RuntimeException {}
