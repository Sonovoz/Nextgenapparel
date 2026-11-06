<?php

namespace App\Filament\Resources\Users\Pages;

use App\Dominio\Sistema\Acceso\Rol;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return 'Nuevo usuario';
    }

    protected function handleRecordCreation(array $data): Model
    {
        $rol = Rol::from($data['rol']);
        unset($data['rol']);

        return DB::transaction(function () use ($data, $rol): User {
            $usuario = User::create($data);
            $usuario->syncRoles([$rol->value]);

            return $usuario;
        });
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
