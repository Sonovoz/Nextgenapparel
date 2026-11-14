<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\AvatarIniciales;
use App\Filament\Pages\Escritorio;
use App\Http\Middleware\DosPasosEquipo;
use App\Http\Middleware\IdiomaBackend;
use App\Http\Middleware\NoIndexar;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // En la primera instalación lleva a /instalar (D208).
            ->login(Login::class)
            // «Mi perfil»: nombre, contraseña y verificación en dos pasos (D186).
            ->profile(isSimple: false)
            // Dos pasos con app autenticadora y códigos de recuperación, obligatoria para el equipo (portada de Clave de Alma).
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()->brandName('Nextgenapparel')], isRequired: true)
            ->multiFactorAuthenticationRequiredMiddlewareName(DosPasosEquipo::class)
            ->brandName('Nextgenapparel')
            // Crédito del desarrollo (Minimedia) en el pie del backend, también en la pantalla de entrada.
            ->renderHook(PanelsRenderHook::FOOTER, fn () => view('filament.partials.credito'))
            // D240: aviso de pedidos nuevos pagados arriba de todas las páginas.
            ->renderHook(PanelsRenderHook::CONTENT_START, fn () => view('filament.partials.pedidos-nuevos'))
            // D240: a todo el ancho (como WooCommerce): las tablas no se comprimen ni piden desplazarse.
            ->maxContentWidth(Width::Full)
            ->defaultAvatarProvider(AvatarIniciales::class)
            ->colors([
                // Gris neutro para todo el backend: sobrio y coherente con el frontend.
                'primary' => Color::Zinc,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Escritorio::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                NoIndexar::class,
            ])
            // Solo el idioma se repite en las peticiones internas de Livewire.
            // Marcar como persistentes las cookies o la sesión las procesaría
            // dos veces y rompería la sesión (error 419 al entrar).
            ->middleware([
                IdiomaBackend::class,
            ], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
