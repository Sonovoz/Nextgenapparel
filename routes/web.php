<?php

use App\Http\Controllers\Backend\ArchivoDisenoController;
use App\Http\Controllers\Cuenta\AccesoController;
use App\Http\Controllers\Cuenta\ContrasenaController;
use App\Http\Controllers\Cuenta\CuentaController;
use App\Http\Controllers\Cuenta\RegistroController;
use App\Http\Controllers\Cuenta\VerificacionController;
use App\Http\Controllers\DisenoController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\TiendaController;
use App\Http\Middleware\EstablecerIdioma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| La raíz redirige al idioma preferido del navegador, si es uno de los
| disponibles; si no, al idioma predeterminado.
*/
Route::get('/', function (Request $request) {
    $idioma = $request->getPreferredLanguage(array_keys(config('idiomas.disponibles')))
        ?? config('idiomas.predeterminado');

    return redirect()->route('portada', ['idioma' => $idioma]);
});

// Backend: archivos privados de la biblioteca de diseños (fase 4), solo con permiso.
Route::prefix('admin/archivos-disenos/{diseno}')->group(function () {
    Route::get('/miniatura', [ArchivoDisenoController::class, 'miniatura'])->name('backend.disenos.miniatura');
    Route::get('/original', [ArchivoDisenoController::class, 'original'])->name('backend.disenos.original');
});

// Cuenta del cliente (paso 5.2), con la URL traducida en cada idioma:
// /es/cuenta/entrar, /en/account/login… Nombres «cuenta.{idioma}.{accion}» (App\Support\RutaCuenta).
foreach (array_keys(config('idiomas.disponibles')) as $idioma) {
    $tramo = fn (string $clave) => trans("rutas.{$clave}", [], $idioma);

    Route::prefix($idioma.'/'.$tramo('cuenta'))
        ->name("cuenta.{$idioma}.")
        ->middleware(EstablecerIdioma::class)
        ->group(function () use ($tramo) {
            Route::middleware('guest')->group(function () use ($tramo) {
                Route::get($tramo('entrar'), [AccesoController::class, 'formulario'])->name('entrar');
                Route::post($tramo('entrar'), [AccesoController::class, 'entrar'])->middleware('throttle:cuenta-entrar');
                Route::get($tramo('registro'), [RegistroController::class, 'formulario'])->name('registro');
                Route::post($tramo('registro'), [RegistroController::class, 'registrar'])->middleware('throttle:cuenta-registro');
                Route::get($tramo('recuperar'), [ContrasenaController::class, 'formularioRecuperar'])->name('recuperar');
                Route::post($tramo('recuperar'), [ContrasenaController::class, 'enviarEnlace'])->middleware('throttle:cuenta-recuperar');
                Route::get($tramo('restablecer').'/{token}', [ContrasenaController::class, 'formularioRestablecer'])->name('restablecer');
                Route::post($tramo('restablecer'), [ContrasenaController::class, 'restablecer'])->name('restablecer.guardar')->middleware('throttle:cuenta-restablecer');
            });

            Route::get($tramo('verificar').'/{id}/{hash}', [VerificacionController::class, 'verificar'])->name('verificar')->middleware('throttle:cuenta-verificar');

            Route::middleware('auth')->group(function () use ($tramo) {
                Route::get('/', [CuentaController::class, 'inicio'])->name('inicio');
                Route::post($tramo('salir'), [AccesoController::class, 'salir'])->name('salir');
                Route::post($tramo('reenviar'), [VerificacionController::class, 'reenviar'])->name('reenviar')->middleware('throttle:cuenta-reenviar');
            });
        });

    // El idioma va en la ruta (para EstablecerIdioma y las URL), no en un parámetro de la URL.
    $prefijo = $idioma.'/'.$tramo('cuenta');
    foreach (Route::getRoutes()->getRoutes() as $ruta) {
        if (str_starts_with($ruta->uri(), $prefijo)) {
            $ruta->defaults('idioma', $idioma);
        }
    }
}

// SEO técnico (D102): antes de las rutas de la tienda.
Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'indice'])->name('sitemap');
Route::get('/sitemap-{idioma}.xml', [SeoController::class, 'sitemap'])
    ->where(['idioma' => implode('|', array_keys(config('idiomas.disponibles')))])
    ->middleware(EstablecerIdioma::class);

Route::prefix('{idioma}')
    ->where(['idioma' => implode('|', array_keys(config('idiomas.disponibles')))])
    ->middleware(EstablecerIdioma::class)
    ->group(function () {
        Route::get('/', [TiendaController::class, 'portada'])->name('portada');

        // Diseños del cliente (fase 4): subida y miniatura, solo para su dueño.
        Route::post('/disenos', [DisenoController::class, 'subir'])->name('disenos.subir')->middleware('throttle:disenos');
        Route::get('/disenos/{diseno}/miniatura', [DisenoController::class, 'miniatura'])->name('disenos.miniatura')->whereUuid('diseno');
        Route::get('/disenos', [DisenoController::class, 'lista'])->name('disenos.lista')->middleware('throttle:disenos-lista');
        Route::delete('/disenos/{diseno}', [DisenoController::class, 'borrar'])->name('disenos.borrar')->whereUuid('diseno')->middleware('throttle:disenos-borrar');

        // Sobre nosotros (D145): /es/sobre-nosotros y /en/about-us; antes de las familias.
        Route::get('/{pagina}', [TiendaController::class, 'nosotros'])
            ->where('pagina', implode('|', array_map(fn ($i) => trans('portada.ruta_nosotros', [], $i), array_keys(config('idiomas.disponibles')))))
            ->name('nosotros');

        // Tienda: /es/camisetas y /es/camisetas/camiseta-essential (URL traducida en /en).
        // Las rutas fijas futuras (carrito, cuenta…) se registran antes de estas.
        Route::get('/{familia}', [TiendaController::class, 'familia'])->name('tienda.familia');
        Route::get('/{familia}/{prenda}', [TiendaController::class, 'prenda'])->name('tienda.prenda');
        Route::get('/{familia}/{prenda}/precio', [TiendaController::class, 'precio'])->name('tienda.precio')->middleware('throttle:precio');
    });
