<?php

namespace App\Filament\Navegacion;

use Filament\Support\Contracts\HasLabel;

/**
 * Grupos del menú del backend, en el orden de WooCommerce (D02).
 *
 * El orden de los casos es el orden en que aparecen en el menú.
 */
enum GrupoNavegacion: string implements HasLabel
{
    case Pedidos = 'pedidos';
    case Produccion = 'produccion';
    case Clientes = 'clientes';
    case Productos = 'productos';
    case Marketing = 'marketing';
    case Analiticas = 'analiticas';
    case Ajustes = 'ajustes';
    case Herramientas = 'herramientas';
    case UsuariosYRoles = 'usuarios';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pedidos => 'Pedidos',
            self::Produccion => 'Producción',
            self::Clientes => 'Clientes',
            self::Productos => 'Productos',
            self::Marketing => 'Marketing',
            self::Analiticas => 'Analíticas',
            self::Ajustes => 'Ajustes',
            self::Herramientas => 'Herramientas',
            self::UsuariosYRoles => 'Usuarios y roles',
        };
    }
}
