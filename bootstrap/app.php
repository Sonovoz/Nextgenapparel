<?php

use App\Dominio\Sistema\Errores\RegistrarError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Herramientas → Log de errores (paso 1.4). Laravel sigue escribiendo
        // además cada error en storage/logs.
        $exceptions->report(function (Throwable $error): void {
            app(RegistrarError::class)($error);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
