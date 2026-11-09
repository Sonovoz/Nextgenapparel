<?php

namespace App\Filament\Resources\Blog\Pages;

use App\Filament\Concerns\AccionSeoIa;
use App\Filament\Resources\Blog\EntradaBlogResource;
use Filament\Resources\Pages\EditRecord;

class EditEntradaBlog extends EditRecord
{
    protected static string $resource = EntradaBlogResource::class;

    protected function getHeaderActions(): array
    {
        return [AccionSeoIa::make('una entrada del blog', ['titulo', 'extracto', 'contenido'], ['titulo', 'extracto', 'contenido'])];
    }
}
