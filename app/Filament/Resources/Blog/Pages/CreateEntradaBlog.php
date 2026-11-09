<?php

namespace App\Filament\Resources\Blog\Pages;

use App\Filament\Concerns\AccionSeoIa;
use App\Filament\Resources\Blog\EntradaBlogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEntradaBlog extends CreateRecord
{
    protected static string $resource = EntradaBlogResource::class;

    protected function getHeaderActions(): array
    {
        return [AccionSeoIa::make('una entrada del blog', ['titulo', 'extracto', 'contenido'], ['titulo', 'extracto', 'contenido'])];
    }
}
