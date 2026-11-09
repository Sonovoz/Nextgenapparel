<?php

namespace App\Providers;

use App\Dominio\Carrito\FusionarCarrito;
use App\Dominio\Disenos\Propietario;
use App\Dominio\Disenos\TraspasarDisenos;
use App\Dominio\Pagos\Pasarela;
use App\Dominio\Pagos\PasarelaRedsys;
use App\Dominio\Pagos\PasarelaSimulada;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Dominio\Sistema\Correo\Smtp;
use App\Dominio\Sistema\Errores\ErrorDeCopia;
use App\Models\Ajuste;
use App\Models\Caja;
use App\Models\ClaseImpuesto;
use App\Models\Cliente;
use App\Models\Color;
use App\Models\Cupon;
use App\Models\Direccion;
use App\Models\Diseno;
use App\Models\Factura;
use App\Models\Familia;
use App\Models\Foto;
use App\Models\Pack;
use App\Models\Pedido;
use App\Models\Prenda;
use App\Models\Proveedor;
use App\Models\ReglaMinimo;
use App\Models\ReglaPrecio;
use App\Models\Talla;
use App\Models\User;
use App\Models\Variante;
use App\Models\ZonaEnvio;
use App\Models\ZonaImpresion;
use App\Policies\ActividadPolicy;
use App\Policies\CatalogoPolicy;
use App\Policies\ClientePolicy;
use App\Policies\DireccionPolicy;
use App\Policies\DisenoPolicy;
use App\Policies\FacturaPolicy;
use App\Policies\PedidoPolicy;
use App\Policies\SeccionPolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use LogicException;
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
        // Pasarela de pago (fase 7, D181): Redsys con los datos del .env; la simulada nunca en producción.
        $this->app->singleton(Pasarela::class, function (): Pasarela {
            if (config('pagos.pasarela') === 'redsys') {
                $c = (array) config('pagos.redsys');
                if ($this->app->isProduction() && $c['entorno'] !== 'live') {
                    throw new LogicException('En producción Redsys tiene que ser el real (REDSYS_ENVIRONMENT=live).');
                }

                return new PasarelaRedsys((string) $c['comercio'], (string) $c['terminal'], (string) $c['clave'], (string) $c['entorno'], (bool) $c['tarjeta'], (bool) $c['bizum'], (string) $c['nombre']);
            }
            if ($this->app->isProduction()) {
                throw new LogicException('En producción la pasarela de pago es Redsys (PAGOS_PASARELA=redsys).');
            }

            return new PasarelaSimulada;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SMTP escrito en Ajustes → Correos (paso 6.4): manda sobre el .env, al crear el correo.
        $smtpAplicado = false;
        $this->app->beforeResolving('mail.manager', function () use (&$smtpAplicado): void {
            if (! $smtpAplicado) {
                $smtpAplicado = true;
                rescue(fn () => Smtp::aplicar(), report: false);
            }
        });

        // Subidas de diseños: un límite por IP frena el abuso sin molestar a quien prueba varias versiones.
        RateLimiter::for('disenos', fn (Request $request) => Limit::perHour(max(1, (int) Ajuste::obtener('disenos.subidas_hora')))->by($request->ip()));

        // Cada limitador lleva su propio contador: con «throttle:6,1» sin nombre todas las rutas
        // comparten el de la IP y quien se registra, entra y pide otra contraseña se quedaba bloqueado.
        foreach ([
            'cuenta-entrar' => 30, 'cuenta-registro' => 10, 'cuenta-recuperar' => 6, 'cuenta-restablecer' => 10,
            'cuenta-verificar' => 10, 'cuenta-reenviar' => 3, 'disenos-lista' => 120, 'disenos-borrar' => 60, 'precio' => 120, 'carrito' => 60,
            // Área de cliente: piden la contraseña actual; por usuario e IP (revisión N3 del 5.3, M2 y B2).
            'cuenta-datos' => 10, 'cuenta-contrasena' => 6, 'cuenta-borrado' => 5,
        ] as $nombre => $porMinuto) {
            RateLimiter::for($nombre, fn (Request $request) => Limit::perMinute($porMinuto)->by($nombre.'|'.($request->user()?->id ?? '-').'|'.$request->ip()));
        }

        // @recurso('img/portada/macro.webp'): URL pública con la fecha del archivo, para que el
        // navegador no siga mostrando la versión antigua al cambiar una imagen con el mismo nombre (D145).
        Blade::directive('recurso', fn (string $ruta) => "<?php echo e(asset({$ruta}).'?v='.@filemtime(public_path({$ruta}))); ?>");

        // El superadministrador tiene todos los permisos de sección. Las
        // reglas que protegen a los propios usuarios (no desactivarse, no
        // quedarse sin superadministradores) están en UserPolicy y no se
        // saltan: la política se consulta antes que este atajo.
        Gate::after(fn (User $usuario, string $habilidad, ?bool $resultado) => $resultado ?? ($usuario->esSuperadministrador() ? true : null));

        Gate::policy(Activity::class, ActividadPolicy::class);
        Gate::policy(Diseno::class, DisenoPolicy::class);
        Gate::policy(Cliente::class, ClientePolicy::class);
        Gate::policy(Pedido::class, PedidoPolicy::class);
        Gate::policy(Factura::class, FacturaPolicy::class);
        Gate::policy(Direccion::class, DireccionPolicy::class);

        foreach ([Familia::class, Color::class, Talla::class, ZonaImpresion::class, Proveedor::class, Prenda::class, Variante::class, Foto::class] as $modelo) {
            Gate::policy($modelo, CatalogoPolicy::class);
        }

        foreach ([ClaseImpuesto::class, ZonaEnvio::class, Caja::class, ReglaPrecio::class,
            ReglaMinimo::class, Cupon::class, Pack::class] as $modelo) {
            Gate::policy($modelo, SeccionPolicy::class);
        }

        // Textos del catálogo: si falta la traducción, se muestra en español.
        Translatable::fallback(fallbackLocale: 'es', fallbackAny: true);

        // Una copia de seguridad fallida va al log de errores como crítica
        // (y avisa por correo).
        Event::listen(BackupHasFailed::class, function (BackupHasFailed $evento): void {
            report(new ErrorDeCopia('La copia de seguridad ha fallado: '.$evento->exception->getMessage(), previous: $evento->exception));
        });

        // Registro de actividad: accesos de los empleados, entren por el backend o por la tienda
        // (con la misma sesión llegan al backend); los de clientes, no (D154, revisión N3 M1).
        $esEmpleado = fn ($usuario) => $usuario instanceof User && $usuario->roles()->exists();
        $enBackend = fn () => request()->is('admin', 'admin/*', 'livewire*');
        Event::listen(Login::class, function (Login $evento) use ($esEmpleado, $enBackend): void {
            if ($evento->user instanceof User && ($enBackend() || $esEmpleado($evento->user))) {
                Actividad::apuntar('accesos', 'Inicio de sesión', $evento->user, quien: $evento->user);
            }
        });

        // Quien vuelve con «recordarme» no pasa por el formulario de entrar: su carrito sin cuenta
        // y sus diseños de la sesión se suman igual (revisión N3 del 5.5, B6).
        Event::listen(Login::class, function (Login $evento): void {
            $guarda = auth()->guard($evento->guard);
            $cliente = $evento->user instanceof User ? $evento->user->cliente : null;
            if ($cliente === null || ! $guarda instanceof SessionGuard || ! $guarda->viaRemember() || ! request()->hasSession()) {
                return;
            }
            $propietario = Propietario::hash(request(), crear: false);
            if ($propietario !== null) {
                app(TraspasarDisenos::class)($propietario, $cliente);
            }
            app(FusionarCarrito::class)(request(), $cliente);
        });

        Event::listen(Failed::class, function (Failed $evento) use ($esEmpleado, $enBackend): void {
            if (! $enBackend() && ! $esEmpleado($evento->user)) {
                return;
            }
            Actividad::apuntar('accesos', 'Intento de acceso fallido', $evento->user instanceof User ? $evento->user : null, [
                'email' => mb_substr((string) ($evento->credentials['email'] ?? ''), 0, 255),
                'ip' => request()->ip(),
            ]);
        });
    }
}
