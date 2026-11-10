<?php

use App\Dominio\Catalogo\ModoCliente;
use App\Dominio\Legal\PaginaLegal;
use App\Http\Controllers\AsistenteController;
use App\Http\Controllers\Backend\ArchivoDisenoController;
use App\Http\Controllers\Backend\ArchivoPedidoController;
use App\Http\Controllers\Backend\ProduccionController;
use App\Http\Controllers\BajaComercialController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CarritoController;
use App\Http\Controllers\CierreController;
use App\Http\Controllers\Cuenta\AccesoController;
use App\Http\Controllers\Cuenta\BorradoController;
use App\Http\Controllers\Cuenta\CambiarContrasenaController;
use App\Http\Controllers\Cuenta\ContrasenaController;
use App\Http\Controllers\Cuenta\CuentaController;
use App\Http\Controllers\Cuenta\DatosController;
use App\Http\Controllers\Cuenta\DireccionesController;
use App\Http\Controllers\Cuenta\MisDisenosController;
use App\Http\Controllers\Cuenta\RegistroController;
use App\Http\Controllers\Cuenta\VerificacionController;
use App\Http\Controllers\DisenoController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ModoController;
use App\Http\Controllers\PackController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PresupuestoController;
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

// Backend: archivos privados de los pedidos (paso 6.3): maquetas y originales, solo con permiso.
Route::prefix('admin/archivos-pedidos/{pedido}')->group(function () {
    Route::get('/maquetas/{maqueta}', [ArchivoPedidoController::class, 'maqueta'])->name('backend.pedidos.maqueta')->whereNumber('maqueta');
    Route::get('/impresiones/{impresion}/original', [ArchivoPedidoController::class, 'original'])->name('backend.pedidos.original')->whereNumber('impresion');
    Route::get('/facturas/{factura:uuid}', [ArchivoPedidoController::class, 'factura'])->name('backend.pedidos.factura')->whereUuid('factura');
    Route::get('/incidencias/fotos/{foto}', [ArchivoPedidoController::class, 'fotoIncidencia'])->name('backend.pedidos.foto-incidencia')->whereNumber('foto');
});

// Baja de los correos comerciales (v2, D189): enlace firmado de cada correo, sin entrar.
Route::get('/marketing/baja/{cliente}', BajaComercialController::class)->name('marketing.baja')->whereUuid('cliente');

// Pagos (fase 7): el aviso firmado de la pasarela (sin CSRF ni sesión) y la página del pago simulado.
// Sin CSRF por `preventRequestForgery(except:)` en bootstrap/app.php (auditoría final, C1).
Route::post('/pagos/aviso', [PagoController::class, 'aviso'])->name('pagos.aviso');
Route::get('/pagos/simulada', [PagoController::class, 'simulada'])->name('pagos.simulada');

// Backend: documentos de producción (paso 6.5b), con la sección Producción.
Route::prefix('admin/produccion')->group(function () {
    Route::get('/pedidos/{pedido}/orden-de-trabajo', [ProduccionController::class, 'orden'])->name('backend.produccion.orden');
    Route::get('/pedidos/{pedido}/paquete', [ProduccionController::class, 'paquete'])->name('backend.produccion.paquete');
    Route::get('/hoja-de-compra.csv', [ProduccionController::class, 'hojaCompra'])->name('backend.produccion.hoja');
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
            Route::get($tramo('confirmar_correo').'/{id}/{hash}', [VerificacionController::class, 'confirmarCorreo'])->name('confirmar-correo')->middleware('throttle:cuenta-verificar');

            Route::middleware('auth')->group(function () use ($tramo) {
                Route::get('/', [CuentaController::class, 'inicio'])->name('inicio');
                Route::post($tramo('salir'), [AccesoController::class, 'salir'])->name('salir');
                Route::post($tramo('reenviar'), [VerificacionController::class, 'reenviar'])->name('reenviar')->middleware('throttle:cuenta-reenviar');

                // Área de cliente (paso 5.3).
                Route::get($tramo('datos'), [DatosController::class, 'editar'])->name('datos');
                Route::put($tramo('datos'), [DatosController::class, 'guardar'])->name('datos.guardar')->middleware('throttle:cuenta-datos');
                $direccion = $tramo('direcciones').'/{direccion}';
                Route::get($tramo('direcciones'), [DireccionesController::class, 'lista'])->name('direcciones');
                Route::get($tramo('direcciones').'/'.$tramo('nueva'), [DireccionesController::class, 'crear'])->name('direcciones.crear');
                Route::post($tramo('direcciones'), [DireccionesController::class, 'guardar'])->name('direcciones.guardar');
                Route::get($direccion.'/'.$tramo('editar'), [DireccionesController::class, 'editar'])->name('direcciones.editar')->whereUuid('direccion');
                Route::put($direccion, [DireccionesController::class, 'actualizar'])->name('direcciones.actualizar')->whereUuid('direccion');
                Route::delete($direccion, [DireccionesController::class, 'borrar'])->name('direcciones.borrar')->whereUuid('direccion');
                Route::post($direccion.'/'.$tramo('predeterminada'), [DireccionesController::class, 'predeterminar'])->name('direcciones.predeterminar')->whereUuid('direccion');
                Route::get($tramo('disenos'), [MisDisenosController::class, 'lista'])->name('disenos');
                Route::delete($tramo('disenos').'/{diseno}', [MisDisenosController::class, 'borrar'])->name('disenos.borrar')->whereUuid('diseno');
                Route::get($tramo('contrasena'), [CambiarContrasenaController::class, 'editar'])->name('contrasena');
                Route::put($tramo('contrasena'), [CambiarContrasenaController::class, 'guardar'])->name('contrasena.guardar')->middleware('throttle:cuenta-contrasena');
                Route::post($tramo('borrado'), [BorradoController::class, 'solicitar'])->name('borrado')->middleware('throttle:cuenta-borrado');
            });
        });

    // Carrito (paso 5.6): /es/carrito y /en/cart, con cuenta o sin ella.
    Route::prefix($idioma.'/'.$tramo('carrito'))
        ->name("carrito.{$idioma}.")
        ->middleware(EstablecerIdioma::class)
        ->group(function () use ($tramo) {
            $linea = $tramo('lineas').'/{uuid}';
            Route::get('/', [CarritoController::class, 'ver'])->name('ver');
            Route::post($tramo('lineas'), [CarritoController::class, 'anadir'])->name('anadir')->middleware('throttle:carrito');
            Route::patch($linea, [CarritoController::class, 'tallas'])->name('tallas')->whereUuid('uuid')->middleware('throttle:carrito');
            Route::delete($linea, [CarritoController::class, 'quitar'])->name('quitar')->whereUuid('uuid')->middleware('throttle:carrito');
            Route::post($tramo('cupon'), [CarritoController::class, 'cupon'])->name('cupon')->middleware('throttle:carrito');
            // Cierra el pedido (paso 5.8): datos, envío y total; confirmar y pagar, en las fases 6 y 7.
            Route::get($tramo('cerrar'), [CierreController::class, 'ver'])->name('cerrar');
            Route::post($tramo('cerrar'), [CierreController::class, 'calcular'])->name('cerrar.calcular')->middleware('throttle:carrito');
            Route::get($tramo('disenos').'/{diseno}', [CarritoController::class, 'miniatura'])->name('miniatura')->whereUuid('diseno');
            // Confirmar el pedido (paso 6.1): crea el pedido; el pago, en la fase 7.
            Route::post($tramo('cerrar').'/'.$tramo('confirmar'), [PedidoController::class, 'confirmar'])->name('confirmar')->middleware('throttle:carrito')
                ->block(10, 10); // un doble clic espera a la primera petición y no pisa la sesión (M3)
        });

    // Presupuestos (D187): /es/presupuestos/{uuid} y /en/quotes/{uuid}, sin indexar.
    Route::prefix($idioma.'/'.$tramo('presupuestos'))
        ->name("presupuestos.{$idioma}.")
        ->middleware(EstablecerIdioma::class)
        ->group(function () {
            Route::post('/', [PresupuestoController::class, 'crear'])->name('crear')->middleware('throttle:carrito');
            Route::get('/{presupuesto}', [PresupuestoController::class, 'ver'])->name('ver')->whereUuid('presupuesto');
            Route::get('/{presupuesto}/pdf', [PresupuestoController::class, 'pdf'])->name('pdf')->whereUuid('presupuesto');
            Route::post('/{presupuesto}/carrito', [PresupuestoController::class, 'pasar'])->name('pasar')->whereUuid('presupuesto')->middleware('throttle:carrito');
        });

    // Pedidos (paso 6.1): /es/pedidos/{uuid} y /en/orders/{uuid}, sin indexar.
    Route::prefix($idioma.'/'.$tramo('pedidos'))
        ->name("pedidos.{$idioma}.")
        ->middleware(EstablecerIdioma::class)
        ->group(function () use ($tramo) {
            Route::get('/{pedido}', [PedidoController::class, 'ver'])->name('ver')->whereUuid('pedido');
            Route::get('/{pedido}/'.$tramo('confirmar_correo_pedido'), [PedidoController::class, 'confirmarCorreo'])->name('confirmar-correo')->whereUuid('pedido')->middleware('throttle:cuenta-verificar');
            Route::post('/{pedido}/'.$tramo('reenviar_enlace'), [PedidoController::class, 'reenviar'])->name('reenviar')->whereUuid('pedido')->middleware('throttle:cuenta-reenviar');
            Route::get('/{pedido}/'.$tramo('acceso'), [PedidoController::class, 'acceso'])->name('acceso')->whereUuid('pedido')->middleware('throttle:cuenta-verificar');
            Route::get('/{pedido}/'.$tramo('facturas').'/{factura:uuid}', [PedidoController::class, 'factura'])->name('factura')->whereUuid('pedido')->whereUuid('factura');
            Route::get('/{pedido}/'.$tramo('maquetas').'/{maqueta}', [PedidoController::class, 'maqueta'])->name('maqueta')->whereUuid('pedido')->whereNumber('maqueta');
            // Visor del cliente (paso 6.6): cancelar (o pedir que se cancele) y repetir.
            Route::post('/{pedido}/'.$tramo('cancelar'), [PedidoController::class, 'cancelar'])->name('cancelar')->whereUuid('pedido')->middleware('throttle:carrito');
            Route::post('/{pedido}/'.$tramo('repetir'), [PedidoController::class, 'repetir'])->name('repetir')->whereUuid('pedido')->middleware('throttle:carrito');
            // Pagar el pedido (fase 7): a la pasarela y vuelta.
            Route::post('/{pedido}/'.$tramo('pagar'), [PagoController::class, 'pagar'])->name('pagar')->whereUuid('pedido')->middleware('throttle:carrito');
            Route::get('/{pedido}/'.$tramo('pago'), [PagoController::class, 'vuelta'])->name('pago-vuelta')->whereUuid('pedido');
            // Incidencias con fotos (paso 6.6b).
            Route::post('/{pedido}/'.$tramo('incidencias'), [PedidoController::class, 'abrirIncidencia'])->name('incidencia')->whereUuid('pedido')->middleware('throttle:carrito');
            Route::get('/{pedido}/'.$tramo('incidencias').'/'.$tramo('fotos').'/{foto}', [PedidoController::class, 'fotoIncidencia'])->name('foto-incidencia')->whereUuid('pedido')->whereNumber('foto');
        });

    // El idioma va en la ruta (para EstablecerIdioma y las URL), no en un parámetro de la URL.
    foreach ([$idioma.'/'.$tramo('cuenta'), $idioma.'/'.$tramo('carrito'), $idioma.'/'.$tramo('pedidos'), $idioma.'/'.$tramo('presupuestos')] as $prefijo) {
        foreach (Route::getRoutes()->getRoutes() as $ruta) {
            if (str_starts_with($ruta->uri(), $prefijo)) {
                $ruta->defaults('idioma', $idioma);
            }
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

        // Packs (paso 5.7): /es/packs/pack-joven, antes de las familias.
        Route::get('/packs/{pack}', [PackController::class, 'ver'])->name('packs.ver');
        // Modos de la portada (D203): /es/modo/eventos, con sus packs y los genéricos; antes de las familias.
        Route::get('/modo/{modo}', [ModoController::class, 'ver'])->name('modo')->whereIn('modo', array_column(ModoCliente::cases(), 'value'));
        Route::post('/packs/{pack}', [PackController::class, 'anadir'])->name('packs.anadir')->middleware('throttle:carrito');
        Route::post('/packs/{pack}/diseno', [PackController::class, 'subir'])->name('packs.subir')->middleware('throttle:disenos');

        // Asistente IA de venta (v2, D194): solo con cuenta; antes de las familias.
        Route::post('/asistente', AsistenteController::class)->name('asistente')->middleware(['auth', 'throttle:carrito']);

        // Blog (v2, D195): /es/blog y /es/blog/{slug}; antes de las familias.
        Route::get('/blog', [BlogController::class, 'index'])->name('blog');
        Route::get('/blog/{slug}', [BlogController::class, 'ver'])->name('blog.ver');

        // Textos legales (paso 8.1): /es/legal/privacidad…; antes de las familias.
        Route::get('/legal/{pagina}', [LegalController::class, 'ver'])->name('legal')->whereIn('pagina', array_column(PaginaLegal::cases(), 'value'));

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
