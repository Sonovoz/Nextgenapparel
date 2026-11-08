<?php

namespace App\Filament\Resources\Catalogo;

use App\Filament\Concerns\CamposTraducibles;
use App\Filament\Navegacion\GrupoNavegacion;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Base de las secciones sencillas de Productos (lista con edición en ventana).
 */
abstract class RecursoCatalogo extends Resource
{
    protected static string|UnitEnum|null $navigationGroup = GrupoNavegacion::Productos;

    /** @return list<string> */
    protected static function camposTraducibles(): array
    {
        $modelo = static::getModel();

        return (new $modelo)->translatable ?? [];
    }

    public static function accionCrear(): CreateAction
    {
        return CreateAction::make()
            ->mutateDataUsing(fn (array $data) => CamposTraducibles::limpiar($data, static::camposTraducibles()));
    }

    public static function accionEditar(): EditAction
    {
        return EditAction::make()
            ->mutateRecordDataUsing(fn (array $data, Model $record) => CamposTraducibles::rellenar($data, $record))
            ->mutateDataUsing(fn (array $data) => CamposTraducibles::limpiar($data, static::camposTraducibles()));
    }

    public static function accionBorrar(): DeleteAction
    {
        return DeleteAction::make()
            ->modalDescription('Solo se puede borrar si no se usa en ninguna prenda ni en ningún carrito. Si se usa, desactívalo.');
    }
}
