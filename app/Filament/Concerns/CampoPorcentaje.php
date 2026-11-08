<?php

namespace App\Filament\Concerns;

use Filament\Forms\Components\TextInput;

/** Porcentaje en pantalla («21» o «5,2») y en centésimas enteras en la base de datos (2100, 520). */
class CampoPorcentaje
{
    public static function make(string $campo, string $etiqueta): TextInput
    {
        return TextInput::make($campo)
            ->label($etiqueta)
            ->inputMode('decimal')
            ->rule('regex:/^\d{1,2}([.,]\d{1,2})?$|^100([.,]0{1,2})?$/')
            ->validationMessages(['regex' => 'Escribe un porcentaje con hasta 2 decimales, por ejemplo 21 o 5,2.'])
            ->suffix('%')
            ->formatStateUsing(fn ($state) => $state === null ? null : self::formatear((int) $state, false))
            ->dehydrateStateUsing(fn ($state) => (int) round(((float) str_replace(',', '.', (string) $state)) * 100));
    }

    public static function formatear(?int $centesimas, bool $conSimbolo = true): string
    {
        if ($centesimas === null) {
            return '—';
        }
        $texto = rtrim(rtrim(number_format($centesimas / 100, 2, ',', ''), '0'), ',');

        return $conSimbolo ? $texto.' %' : $texto;
    }
}
