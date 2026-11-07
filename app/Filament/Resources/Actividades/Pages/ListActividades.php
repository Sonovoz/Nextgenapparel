<?php

namespace App\Filament\Resources\Actividades\Pages;

use App\Filament\Resources\Actividades\ActividadResource;
use Filament\Resources\Pages\ListRecords;

class ListActividades extends ListRecords
{
    protected static string $resource = ActividadResource::class;

    public function getTitle(): string
    {
        return 'Registro de actividad';
    }
}
