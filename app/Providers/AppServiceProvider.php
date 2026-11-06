<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // El superadministrador tiene todos los permisos de sección. Las
        // reglas que protegen a los propios usuarios (no desactivarse, no
        // quedarse sin superadministradores) están en UserPolicy y no se
        // saltan: la política se consulta antes que este atajo.
        Gate::after(fn (User $usuario, string $habilidad, ?bool $resultado) => $resultado ?? ($usuario->esSuperadministrador() ? true : null));
    }
}
