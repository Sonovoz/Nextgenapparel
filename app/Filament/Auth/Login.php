<?php

namespace App\Filament\Auth;

use App\Dominio\Sistema\Instalacion\AccesoTemporal;
use Filament\Auth\Pages\Login as LoginFilament;

/**
 * Entrada al backend. En la primera instalación, sin ningún superadministrador,
 * lleva a /instalar para crearlo con el acceso temporal (D208).
 */
class Login extends LoginFilament
{
    public function mount(): void
    {
        if (AccesoTemporal::pendiente()) {
            $this->redirect(route('instalacion'));

            return;
        }

        parent::mount();
    }
}
