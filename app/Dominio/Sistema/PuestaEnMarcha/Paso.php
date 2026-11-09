<?php

namespace App\Dominio\Sistema\PuestaEnMarcha;

use Filament\Pages\Page;

/** Un paso de la puesta en marcha; `donde` es la página o el recurso del panel en el que se hace (null: el .env del servidor). */
final readonly class Paso
{
    public function __construct(
        public string $titulo,
        public string $como,
        /** @var class-string<Page>|class-string<\Filament\Resources\Resource>|null */
        public ?string $donde,
        public bool $hecho,
        public bool $opcional = false,
    ) {}
}
