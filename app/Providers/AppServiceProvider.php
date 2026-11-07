<?php

namespace App\Providers;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Dominio\Sistema\Errores\ErrorDeCopia;
use App\Models\User;
use App\Policies\ActividadPolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Translatable\Facades\Translatable;

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

        Gate::policy(Activity::class, ActividadPolicy::class);

        // Textos del catálogo: si falta la traducción, se muestra en español.
        Translatable::fallback(fallbackLocale: 'es', fallbackAny: true);

        // Una copia de seguridad fallida va al log de errores como crítica
        // (y avisa por correo).
        Event::listen(BackupHasFailed::class, function (BackupHasFailed $evento): void {
            report(new ErrorDeCopia('La copia de seguridad ha fallado: '.$evento->exception->getMessage(), previous: $evento->exception));
        });

        // Registro de actividad: accesos al backend.
        Event::listen(Login::class, function (Login $evento): void {
            if ($evento->user instanceof User) {
                Actividad::apuntar('accesos', 'Inicio de sesión', $evento->user, quien: $evento->user);
            }
        });

        Event::listen(Failed::class, function (Failed $evento): void {
            Actividad::apuntar('accesos', 'Intento de acceso fallido', $evento->user instanceof User ? $evento->user : null, [
                'email' => mb_substr((string) ($evento->credentials['email'] ?? ''), 0, 255),
                'ip' => request()->ip(),
            ]);
        });
    }
}
