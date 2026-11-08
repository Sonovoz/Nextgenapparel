<?php

namespace App\Filament\Resources\Disenos\Pages;

use App\Filament\Resources\Disenos\DisenoResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDiseno extends ViewRecord
{
    protected static string $resource = DisenoResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->nombre_original;
    }

    protected function getHeaderActions(): array
    {
        return [DisenoResource::accionDescargar(), DisenoResource::accionBorrar()];
    }
}
