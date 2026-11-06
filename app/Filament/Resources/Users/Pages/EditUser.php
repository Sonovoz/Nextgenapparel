<?php

namespace App\Filament\Resources\Users\Pages;

use App\Dominio\Sistema\Acceso\ReglasUsuarios;
use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return 'Editar usuario';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $usuario */
        $usuario = $this->getRecord();
        $data['rol'] = $usuario->rol()?->value;

        return $data;
    }

    /**
     * @param  User  $record
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Los campos desactivados (rol y activo de uno mismo) no llegan en
        // $data: se conservan los valores actuales.
        $rol = isset($data['rol']) ? Rol::from($data['rol']) : $record->rol();
        $activo = array_key_exists('activo', $data) ? (bool) $data['activo'] : $record->activo;
        unset($data['rol']);

        app(ReglasUsuarios::class)->validar(auth()->user(), $record, $rol, $activo);

        return DB::transaction(function () use ($record, $data, $rol): User {
            $record->update($data);

            if ($rol !== null) {
                $record->syncRoles([$rol->value]);
            }

            return $record;
        });
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
