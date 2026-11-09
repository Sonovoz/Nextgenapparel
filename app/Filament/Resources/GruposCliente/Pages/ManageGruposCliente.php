<?php

namespace App\Filament\Resources\GruposCliente\Pages;

use App\Filament\Resources\GruposCliente\GrupoClienteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGruposCliente extends ManageRecords
{
    protected static string $resource = GrupoClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuevo grupo')];
    }
}
