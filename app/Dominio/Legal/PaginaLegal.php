<?php

namespace App\Dominio\Legal;

/** Los textos legales de la tienda (paso 8.1, D183). */
enum PaginaLegal: string
{
    case Aviso = 'aviso-legal';
    case Privacidad = 'privacidad';
    case Cookies = 'cookies';
    case Condiciones = 'condiciones-de-venta';
    case Envios = 'envios-e-incidencias';

    public function titulo(?string $idioma = null): string
    {
        return __('legal.'.$this->value, [], $idioma);
    }
}
