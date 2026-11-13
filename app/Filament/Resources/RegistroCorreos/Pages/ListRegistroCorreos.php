<?php

namespace App\Filament\Resources\RegistroCorreos\Pages;

use App\Filament\Resources\RegistroCorreos\RegistroCorreoResource;
use Filament\Resources\Pages\ListRecords;

class ListRegistroCorreos extends ListRecords
{
    protected static string $resource = RegistroCorreoResource::class;

    public function getTitle(): string
    {
        return 'Registro de correos';
    }
}
