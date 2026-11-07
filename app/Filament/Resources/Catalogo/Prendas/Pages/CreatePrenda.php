<?php

namespace App\Filament\Resources\Catalogo\Prendas\Pages;

use App\Filament\Concerns\CamposTraducibles;
use App\Filament\Resources\Catalogo\Prendas\PrendaResource;
use App\Models\Prenda;
use Filament\Resources\Pages\CreateRecord;

class CreatePrenda extends CreateRecord
{
    protected static string $resource = PrendaResource::class;

    public function getTitle(): string
    {
        return 'Nueva prenda';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return CamposTraducibles::limpiar($data, (new Prenda)->translatable);
    }

    /** Tras crearla, a la edición para añadir variaciones, zonas y medidas. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
