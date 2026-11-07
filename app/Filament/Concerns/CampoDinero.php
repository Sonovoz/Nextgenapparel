<?php

namespace App\Filament\Concerns;

use Filament\Forms\Components\TextInput;

/**
 * Importe en euros en pantalla y en céntimos enteros en la base de datos (regla 7).
 */
class CampoDinero
{
    public static function make(string $campo, string $etiqueta): TextInput
    {
        // Admite coma o punto decimal («4,50» o «4.50»), hasta 2 decimales.
        return TextInput::make($campo)
            ->label($etiqueta)
            ->inputMode('decimal')
            ->rule('regex:/^\d{1,6}([.,]\d{1,2})?$/')
            ->validationMessages(['regex' => 'Escribe un importe en euros, por ejemplo 4,50.'])
            ->prefix('€')
            ->formatStateUsing(fn ($state) => $state === null ? null : number_format($state / 100, 2, ',', ''))
            ->dehydrateStateUsing(fn ($state) => self::aCentimos($state));
    }

    public static function aCentimos(mixed $valor): int
    {
        return (int) round(((float) str_replace(',', '.', (string) $valor)) * 100);
    }

    public static function formatear(?int $centimos): string
    {
        return $centimos === null ? '—' : number_format($centimos / 100, 2, ',', '.').' €';
    }
}
