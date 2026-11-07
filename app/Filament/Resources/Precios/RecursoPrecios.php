<?php

namespace App\Filament\Resources\Precios;

use App\Dominio\Precios\Ambito;
use App\Filament\Resources\Catalogo\RecursoCatalogo;
use App\Models\Familia;
use App\Models\Prenda;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Base de las secciones de precios, envíos y marketing: lista con edición
 * en ventana, como las del catálogo. Cada recurso fija su grupo del menú.
 */
abstract class RecursoPrecios extends RecursoCatalogo
{
    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can(static::$navigationGroup->permiso());
    }

    /** Ámbito: todo el catálogo, una familia o una prenda. */
    protected static function camposAmbito(string $etiqueta = 'Se aplica a'): array
    {
        return [Grid::make(2)->schema([
            Select::make('ambito')->label($etiqueta)->options(Ambito::class)->required()->native(false)->live()->default(Ambito::Catalogo),
            Select::make('ambito_id')->label(fn (Get $get) => static::ambito($get) === Ambito::Prenda ? 'Prenda' : 'Familia')->native(false)
                ->visible(fn (Get $get) => static::ambito($get) !== Ambito::Catalogo)
                ->required(fn (Get $get) => static::ambito($get) !== Ambito::Catalogo)
                ->options(fn (Get $get) => static::ambito($get) === Ambito::Prenda
                    ? Prenda::orderBy('id')->get()->pluck('nombre', 'id')
                    : Familia::orderBy('orden')->get()->pluck('nombre', 'id')),
        ])];
    }

    protected static function camposVigencia(): array
    {
        return [Grid::make(2)->schema([
            DateTimePicker::make('desde')->label('Desde')->seconds(false)->helperText('Vacío: desde ya.'),
            DateTimePicker::make('hasta')->label('Hasta')->seconds(false)->after('desde')->helperText('Vacío: sin fin.'),
        ])];
    }

    private static function ambito(Get $get): Ambito
    {
        $valor = $get('ambito');

        return $valor instanceof Ambito ? $valor : (Ambito::tryFrom((string) $valor) ?? Ambito::Catalogo);
    }
}
