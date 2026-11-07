<?php

namespace App\Dominio\Sistema\Acceso;

use App\Filament\Navegacion\GrupoNavegacion;
use Filament\Support\Contracts\HasLabel;

/**
 * Roles del backend (D47). Son fijos y se definen aquí, en el código:
 * `php artisan migrate` los crea o actualiza en cada entorno.
 */
enum Rol: string implements HasLabel
{
    case Superadministrador = 'superadministrador';
    case Administrador = 'administrador';
    case GestorTienda = 'gestor-tienda';
    case Produccion = 'produccion';

    public function getLabel(): string
    {
        return match ($this) {
            self::Superadministrador => 'Superadministrador',
            self::Administrador => 'Administrador',
            self::GestorTienda => 'Gestor de la tienda',
            self::Produccion => 'Producción',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Superadministrador => 'Acceso total, incluida la gestión de usuarios y roles.',
            self::Administrador => 'Todo el backend salvo usuarios y roles.',
            self::GestorTienda => 'La tienda del día a día: pedidos, producción, clientes, productos, marketing y analíticas. Sin ajustes ni herramientas, como en WooCommerce.',
            self::Produccion => 'Solo producción: órdenes de trabajo y hoja de compra.',
        };
    }

    /**
     * Grupos del menú a los que tiene acceso.
     *
     * @return list<GrupoNavegacion>
     */
    public function grupos(): array
    {
        return match ($this) {
            self::Superadministrador => GrupoNavegacion::cases(),
            self::Administrador => array_values(array_filter(
                GrupoNavegacion::cases(),
                fn (GrupoNavegacion $grupo) => $grupo !== GrupoNavegacion::UsuariosYRoles,
            )),
            self::GestorTienda => [
                GrupoNavegacion::Pedidos,
                GrupoNavegacion::Produccion,
                GrupoNavegacion::Clientes,
                GrupoNavegacion::Productos,
                GrupoNavegacion::Marketing,
                GrupoNavegacion::Analiticas,
            ],
            self::Produccion => [
                GrupoNavegacion::Produccion,
            ],
        };
    }

    public function puedeVer(GrupoNavegacion $grupo): bool
    {
        return in_array($grupo, $this->grupos(), true);
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $rol) => $rol->value, self::cases());
    }
}
