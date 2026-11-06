<?php

namespace App\Filament\Pages\Pendientes;

use Filament\Pages\Page;

/**
 * Sección del menú que todavía no está construida.
 *
 * Mantiene el menú completo, organizado como WooCommerce, desde la fase 1 y
 * explica qué contendrá y en qué fase llega. Cada subclase se sustituirá por
 * la sección real cuando se construya.
 */
abstract class PaginaPendiente extends Page
{
    protected string $view = 'filament.pages.pagina-pendiente';

    /** Fase (o paso) en la que se construye, p. ej. «fase 2» o «fase 1 · paso 1.4». */
    protected static string $disponibleEn;

    /** Qué hará la sección, en una o dos frases. */
    protected static string $descripcion;

    /** Equivalente en WooCommerce, si lo hay. */
    protected static ?string $equivalenteWooCommerce = null;

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public static function getDisponibleEn(): string
    {
        return static::$disponibleEn;
    }

    public static function getDescripcion(): string
    {
        return static::$descripcion;
    }

    public static function getEquivalenteWooCommerce(): ?string
    {
        return static::$equivalenteWooCommerce;
    }

    protected function getViewData(): array
    {
        return [
            'disponibleEn' => static::getDisponibleEn(),
            'descripcion' => static::getDescripcion(),
            'equivalenteWooCommerce' => static::getEquivalenteWooCommerce(),
        ];
    }
}
