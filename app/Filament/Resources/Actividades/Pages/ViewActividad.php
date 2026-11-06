<?php

namespace App\Filament\Resources\Actividades\Pages;

use App\Filament\Resources\Actividades\ActividadResource;
use Filament\Resources\Pages\ViewRecord;

class ViewActividad extends ViewRecord
{
    protected static string $resource = ActividadResource::class;

    public function getTitle(): string
    {
        return 'Detalle de la actividad';
    }
}
