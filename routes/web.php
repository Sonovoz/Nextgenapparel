<?php

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

        // Sobre nosotros (D145): /es/sobre-nosotros y /en/about-us; antes de las familias.
        Route::get('/{pagina}', [TiendaController::class, 'nosotros'])
            ->where('pagina', implode('|', array_map(fn ($i) => trans('portada.ruta_nosotros', [], $i), array_keys(config('idiomas.disponibles')))))
            ->name('nosotros');

        // Tienda: /es/camisetas y /es/camisetas/camiseta-essential (URL traducida en /en).
        // Las rutas fijas futuras (carrito, cuenta…) se registran antes de estas.
        Route::get('/{familia}', [TiendaController::class, 'familia'])->name('tienda.familia');
        Route::get('/{familia}/{prenda}', [TiendaController::class, 'prenda'])->name('tienda.prenda');
        Route::get('/{familia}/{prenda}/precio', [TiendaController::class, 'precio'])->name('tienda.precio')->middleware('throttle:120,1');
    });
