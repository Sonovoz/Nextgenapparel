<?php

use App\Dominio\Disenos\AnalizadorPng;
use App\Dominio\Sistema\Errores\RegistrarError;
use App\Http\Middleware\CuentaActiva;
use App\Support\RutaCuenta;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Caddy termina el HTTPS y reenvía a Nginx dentro de la red de Docker:
        // hay que fiarse de sus cabeceras X-Forwarded-* para que las URL
        // (canonical, sitemap, recursos) salgan en https (D108). El contenedor
        // de la aplicación no se publica fuera de esa red.
        $middleware->trustProxies(at: '*');
        // …pero solo para el dominio de APP_URL (y sus subdominios): nadie puede
        // colar otro host, p. ej. en los enlaces de los correos. Se admite además
        // 127.0.0.1 para la comprobación de salud del contenedor (compose.yaml).
        // No actúa en local ni en tests.
        $middleware->trustHosts(at: ['^127\.0\.0\.1$'], subdomains: true);

        // Cuenta del cliente (paso 5.2): sin sesión, a «Entrar» en el idioma de la URL;
        // con sesión, de «Entrar» o «Registro» a «Mi cuenta». El backend tiene sus propias redirecciones.
        $idiomaDe = fn (Request $request): string => array_key_exists((string) $request->segment(1), config('idiomas.disponibles'))
            ? (string) $request->segment(1) : config('idiomas.predeterminado');
        $middleware->redirectGuestsTo(fn (Request $request) => RutaCuenta::url('entrar', [], $idiomaDe($request)));
        $middleware->redirectUsersTo(fn (Request $request) => RutaCuenta::url('inicio', [], $idiomaDe($request)));

        // Tienda (revisión N3 de la fase 5): al cambiar la contraseña se cierran las demás sesiones
        // de ese usuario (AuthenticateSession, A1) y una cuenta desactivada sale al momento (A2).
        $middleware->web(append: [AuthenticateSession::class, CuentaActiva::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Herramientas → Log de errores (paso 1.4). Laravel sigue escribiendo
        // además cada error en storage/logs.
        $exceptions->report(function (Throwable $error): void {
            app(RegistrarError::class)($error);
        });

        // Un diseño más grande que el límite de PHP (post_max_size) no llega ni a la ruta:
        // se responde con el mismo aviso que el resto de errores de subida, en su idioma.
        $exceptions->render(function (PostTooLargeException $error, Request $request) {
            if (! $request->is('*/disenos')) {
                return null;
            }
            $idioma = array_key_exists((string) $request->segment(1), config('idiomas.disponibles')) ? $request->segment(1) : config('idiomas.predeterminado');
            app()->setLocale($idioma);

            return response()->json(['message' => __('disenos.error_subida', ['mb' => AnalizadorPng::maxMbEfectivo()]), 'motivo' => 'subida'], 413);
        });

        // Demasiadas peticiones en un formulario de la cuenta: aviso en la propia página, no una 429 en blanco.
        $exceptions->render(function (ThrottleRequestsException $error, Request $request) {
            if ($request->expectsJson() || ! str_starts_with((string) $request->route()?->getName(), 'cuenta.')) {
                return null;
            }

            return back()->withInput($request->except(['contrasena', 'contrasena_confirmation', 'token']))
                ->with('error', __('cuenta.demasiados_intentos', ['segundos' => (int) ($error->getHeaders()['Retry-After'] ?? 60)]));
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
