<?php

namespace App\Filament\Resources\Catalogo\Pages;

use App\Filament\Resources\Catalogo\ZonaImpresionResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageZonasImpresion extends ManageRecords
{
    protected static string $resource = ZonaImpresionResource::class;

    public function getTitle(): string
    {
        return Str::ucfirst(static::getResource()::getPluralModelLabel());
    }

    protected function getHeaderActions(): array
    {
        return [ZonaImpresionResource::accionCrear()];
    }
}
