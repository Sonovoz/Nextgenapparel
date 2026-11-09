<?php

namespace App\Filament\Resources\Blog\Pages;

use App\Filament\Resources\Blog\EntradaBlogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEntradasBlog extends ListRecords
{
    protected static string $resource = EntradaBlogResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nueva entrada')];
    }
}
