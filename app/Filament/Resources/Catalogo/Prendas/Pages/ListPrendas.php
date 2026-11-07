<?php

namespace App\Filament\Resources\Catalogo\Prendas\Pages;

use App\Filament\Resources\Catalogo\Prendas\PrendaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrendas extends ListRecords
{
    protected static string $resource = PrendaResource::class;

    public function getTitle(): string
    {
        return 'Productos';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Añadir prenda')];
    }
}
