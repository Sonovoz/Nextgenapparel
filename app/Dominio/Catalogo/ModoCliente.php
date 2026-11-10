<?php

namespace App\Dominio\Catalogo;

/**
 * «Elige tu modo» de la portada (D203): para quién es cada pack. Un pack sin modos es genérico y sale
 * en todos. Los textos (nombre y frase) están en lang/{es,en}/portada.php.
 */
enum ModoCliente: string
{
    case Creadores = 'creadores';
    case Eventos = 'eventos';
    case Empresas = 'empresas';

    public function numero(): int
    {
        return match ($this) {
            self::Creadores => 1,
            self::Eventos => 2,
            self::Empresas => 3,
        };
    }

    public function nombre(): string
    {
        return __('portada.quien_'.$this->numero());
    }

    /** @return array<string, string> para el backend */
    public static function opciones(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => __('portada.quien_'.$m->numero(), [], 'es')])->all();
    }
}
