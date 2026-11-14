@php
    // El menú nunca debe impedir mostrar la página (autosuficiencia).
    $familiasMenu = rescue(fn () => \App\Dominio\Catalogo\Escaparate::familias(), collect(), report: false);
    $idiomaActual = app()->getLocale();
    $seo = $seo ?? [];
    $descripcionMeta = $descripcion ?? __('portada.descripcion');
    $canonical = $alternos[$idiomaActual];
    $localesOg = ['es' => 'es_ES', 'en' => 'en_GB'];
    $nombreTienda = rescue(fn () => \App\Models\Ajuste::obtener('tienda.nombre'), 'Nextgenapparel', report: false);
    $portada = route('portada');
    $urlNosotros = route('nosotros', ['pagina' => __('portada.ruta_nosotros')]);
    $enNosotros = url()->current() === $urlNosotros;
    // Cuenta del cliente (paso 5.2): «Entrar» o «Mi cuenta».
    $conCuenta = auth()->check();
    $urlCuenta = \App\Support\RutaCuenta::url($conCuenta ? 'inicio' : 'entrar');
    $textoCuenta = $conCuenta ? __('cuenta.mi_cuenta') : __('cuenta.entrar');
    // Carrito (paso 5.6): número de líneas, sin crear ningún carrito.
    $urlCarrito = \App\Http\Controllers\CarritoController::url('ver');
    $lineasCarrito = rescue(fn () => \App\Dominio\Carrito\CarritoActual::de(request())?->lineas()->count() ?? 0, 0, report: false);
@endphp
<!DOCTYPE html>
<html lang="{{ $idiomaActual }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $titulo }}</title>
    <meta name="description" content="{{ $descripcionMeta }}">
    {{-- Fuera del índice salvo en producción con el interruptor de Ajustes encendido (D102). --}}
    {{-- Las páginas de error nunca se indexan ni declaran URL canónica. --}}
    <meta name="robots" content="{{ empty($seo['noindex']) ? \App\Dominio\Seo\Indexacion::metaRobots() : 'noindex, nofollow' }}">
    @empty($seo['noindex'])
    <link rel="canonical" href="{{ $canonical }}">
    @foreach ($alternos as $codigo => $url)
        <link rel="alternate" hreflang="{{ $codigo }}" href="{{ $url }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $alternos[config('idiomas.predeterminado')] }}">
    @endempty
    <meta property="og:site_name" content="{{ $nombreTienda }}">
    <meta property="og:type" content="{{ $seo['tipo'] ?? 'website' }}">
    <meta property="og:title" content="{{ $titulo }}">
    <meta property="og:description" content="{{ $descripcionMeta }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="{{ $localesOg[$idiomaActual] ?? $idiomaActual }}">
    @foreach (array_diff_key($localesOg, [$idiomaActual => true]) as $locale)
        <meta property="og:locale:alternate" content="{{ $locale }}">
    @endforeach
    @if (! empty($seo['imagen']))
        <meta property="og:image" content="{{ $seo['imagen'] }}">
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    @foreach ($seo['esquemas'] ?? [] as $esquema)
        <script type="application/ld+json">{!! json_encode($esquema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach
    <link rel="icon" type="image/png" href="@recurso('img/marca/favicon.png')">
    <link rel="preload" href="{{ asset('fonts/jost.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="@recurso('css/tienda.css')">
    <meta name="theme-color" content="#07070C">
    {{-- Con JavaScript, las animaciones de entrada; sin él, todo se ve igual. --}}
    <script>document.documentElement.classList.add('js')</script>
    @stack('estilos')
</head>
<body>
    <header class="cabecera" id="cabecera">
        <div class="contenedor">
            <a class="marca" href="{{ $portada }}" aria-label="{{ $nombreTienda }} — {{ __('portada.inicio') }}">
                <img class="monograma" src="@recurso('img/marca/monograma.png')" alt="" width="190" height="147">
                <img class="palabra" src="@recurso('img/marca/nextgenapparel.png')" alt="{{ $nombreTienda }}" width="510" height="80">
            </a>

            <nav class="navegacion" aria-label="{{ __('portada.menu') }}">
                <ul>
                    <li>
                        <details>
                            <summary>{{ __('portada.nav_prendas') }}</summary>
                            <div class="desplegable">
                                @foreach ($familiasMenu as $f)
                                    @php $urlFamilia = route('tienda.familia', ['familia' => \App\Dominio\Catalogo\Escaparate::slug($f, $idiomaActual)]); @endphp
                                    <a href="{{ $urlFamilia }}" @if (url()->current() === $urlFamilia) aria-current="page" @endif>{{ $f->nombre }}</a>
                                @endforeach
                            </div>
                        </details>
                    </li>
                    <li><a href="{{ $portada }}#packs">{{ __('portada.nav_packs') }}</a></li>
                    <li><a href="{{ $portada }}#pasos">{{ __('portada.nav_como') }}</a></li>
                    <li><a href="{{ $portada }}#calidad">{{ __('portada.nav_calidad') }}</a></li>
                    <li><a href="{{ $urlNosotros }}" @if ($enNosotros) aria-current="page" @endif>{{ __('portada.nav_nosotros') }}</a></li>
                </ul>
            </nav>

            <div class="cabecera-acciones">
                <ul class="idiomas" aria-label="{{ __('portada.cambiar_idioma') }}">
                    @foreach (config('idiomas.disponibles') as $codigo => $nombre)
                        <li><a href="{{ $alternos[$codigo] }}" hreflang="{{ $codigo }}" lang="{{ $codigo }}" @if ($codigo === $idiomaActual) aria-current="page" @endif>{{ strtoupper($codigo) }}<span class="oculto"> · {{ $nombre }}</span></a></li>
                    @endforeach
                </ul>
                <a class="enlace-cuenta" href="{{ $urlCuenta }}" aria-label="{{ $textoCuenta }}" @if (url()->current() === $urlCuenta) aria-current="page" @endif>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
                    <span class="texto-cuenta">{{ $textoCuenta }}</span>
                </a>
                <a class="enlace-cuenta enlace-carrito" href="{{ $urlCarrito }}" @if (url()->current() === $urlCarrito) aria-current="page" @endif>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M5 7h14l-1.2 12.2a1 1 0 0 1-1 .8H7.2a1 1 0 0 1-1-.8L5 7Z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
                    <span class="texto-cuenta">{{ __('carrito.enlace') }}</span>
                    @if ($lineasCarrito > 0)<span class="contador-carrito">{{ $lineasCarrito }}<span class="oculto"> {{ trans_choice('carrito.lineas_contador', $lineasCarrito) }}</span></span>@endif
                </a>
                <a class="boton principal cabecera-cta" href="{{ $portada }}#pasos">{{ __('portada.cta_coleccion') }}</a>
                <button class="boton-menu" type="button" aria-expanded="false" aria-controls="menu-movil" aria-label="{{ __('portada.menu') }}"><span></span></button>
            </div>
        </div>
    </header>

    <div class="menu-movil" id="menu-movil" role="dialog" aria-modal="true" aria-label="{{ __('portada.menu') }}">
        <div class="menu-movil-cabecera">
            <a class="marca" href="{{ $portada }}"><img class="monograma" src="@recurso('img/marca/monograma.png')" alt="{{ $nombreTienda }}" width="190" height="147"></a>
            <button class="boton-cerrar" type="button" aria-label="{{ __('portada.cerrar') }}">×</button>
        </div>
        <nav>
            <p class="etiqueta">{{ __('portada.nav_prendas') }}</p>
            <ul>
                @foreach ($familiasMenu as $f)
                    <li><a href="{{ route('tienda.familia', ['familia' => \App\Dominio\Catalogo\Escaparate::slug($f, $idiomaActual)]) }}">{{ $f->nombre }}</a></li>
                @endforeach
            </ul>
            <p class="etiqueta">{{ $nombreTienda }}</p>
            <ul>
                <li><a href="{{ $portada }}#packs">{{ __('portada.nav_packs') }}</a></li>
                <li><a href="{{ $portada }}#pasos">{{ __('portada.nav_como') }}</a></li>
                <li><a href="{{ $portada }}#calidad">{{ __('portada.nav_calidad') }}</a></li>
                <li><a href="{{ $urlNosotros }}" @if ($enNosotros) aria-current="page" @endif>{{ __('portada.nav_nosotros') }}</a></li>
                <li><a href="{{ $urlCuenta }}">{{ $textoCuenta }}</a></li>
            </ul>
        </nav>
        <div class="acciones">
            <a class="boton principal" href="{{ $portada }}#pasos">{{ __('portada.cta_coleccion') }}</a>
            <a class="boton" href="{{ $portada }}#packs">{{ __('portada.cta_packs') }}</a>
        </div>
    </div>

    <main id="contenido">
        {{ $slot }}
    </main>

    @if (\App\Dominio\Asistente\AsistenteVenta::disponible(auth()->user()?->cliente))
        @include('tienda.asistente')
    @endif
    <footer class="pie">
        <div class="contenedor">
            <div class="pie-rejilla">
                <div>
                    <a class="marca" href="{{ $portada }}">
                        <img class="monograma" src="@recurso('img/marca/monograma.png')" alt="" width="190" height="147">
                        <img class="palabra" src="@recurso('img/marca/nextgenapparel.png')" alt="{{ $nombreTienda }}" width="510" height="80">
                    </a>
                    <p style="max-width: 36ch; margin: 22px 0 0; color: #B8B1A9;">{{ __('portada.pie_texto') }}</p>
                </div>
                <div>
                    <h2 class="pie-titulo">{{ __('portada.nav_prendas') }}</h2>
                    <ul>
                        @foreach ($familiasMenu as $f)
                            <li><a href="{{ route('tienda.familia', ['familia' => \App\Dominio\Catalogo\Escaparate::slug($f, $idiomaActual)]) }}">{{ $f->nombre }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <h2 class="pie-titulo">{{ __('portada.pie_estudio') }}</h2>
                    <ul>
                        <li><a href="{{ $portada }}#pasos">{{ __('portada.nav_como') }}</a></li>
                        <li><a href="{{ $portada }}#packs">{{ __('portada.nav_packs') }}</a></li>
                        <li><a href="{{ $portada }}#calidad">{{ __('portada.nav_calidad') }}</a></li>
                        <li><a href="{{ $urlNosotros }}">{{ __('portada.nav_nosotros') }}</a></li>
                        <li><a href="{{ route('guia-imagen', ['guia' => __('guia_imagen.ruta')]) }}">{{ __('guia_imagen.titulo_corto') }}</a></li>
                        <li><a href="{{ route('blog') }}">{{ __('blog.blog') }}</a></li>
                    </ul>
                </div>
                <div>
                    <h2 class="pie-titulo">{{ __('portada.cambiar_idioma') }}</h2>
                    <ul>
                        @foreach (config('idiomas.disponibles') as $codigo => $nombre)
                            <li><a href="{{ $alternos[$codigo] }}" hreflang="{{ $codigo }}" lang="{{ $codigo }}">{{ $nombre }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <nav class="pie-legal" aria-label="{{ __('legal.legal') }}">
                @foreach (\App\Dominio\Legal\PaginaLegal::cases() as $paginaLegal)
                    <a href="{{ route('legal', ['idioma' => $idiomaActual, 'pagina' => $paginaLegal->value]) }}">{{ $paginaLegal->titulo() }}</a>
                @endforeach
            </nav>
            <div class="pie-firma">
                <span>© {{ now()->year }} {{ $nombreTienda }}</span>
                <span>{{ __('portada.firma') }}</span>
            </div>
            <div class="pie-credito">@include('partials.credito-minimedia')</div>
        </div>
    </footer>

    <script>
        // Cabecera con línea al bajar, menú móvil y apariciones suaves (sin librerías).
        (function () {
            var cabecera = document.getElementById('cabecera');
            var marcar = function () { cabecera.classList.toggle('con-sombra', window.scrollY > 8); };
            window.addEventListener('scroll', marcar, { passive: true }); marcar();

            var menu = document.getElementById('menu-movil');
            var abrir = document.querySelector('.boton-menu');
            var cerrar = menu.querySelector('.boton-cerrar');
            function estado(abierto) {
                menu.classList.toggle('abierto', abierto);
                document.body.classList.toggle('menu-abierto', abierto);
                abrir.setAttribute('aria-expanded', String(abierto));
                if (abierto) cerrar.focus(); else abrir.focus();
            }
            abrir.addEventListener('click', function () { estado(true); });
            cerrar.addEventListener('click', function () { estado(false); });
            menu.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { estado(false); }); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && menu.classList.contains('abierto')) estado(false); });

            // El desplegable de Prendas se cierra al pulsar fuera.
            document.addEventListener('click', function (e) {
                document.querySelectorAll('.navegacion details[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
            });

            var elementos = document.querySelectorAll('.aparece');
            if (!('IntersectionObserver' in window)) { elementos.forEach(function (el) { el.classList.add('visible'); }); return; }
            var observador = new IntersectionObserver(function (entradas) {
                entradas.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('visible'); observador.unobserve(e.target); } });
            }, { rootMargin: '0px 0px -8% 0px' });
            elementos.forEach(function (el) { observador.observe(el); });
        })();
    </script>
    @include('tienda._procesando')
    @stack('scripts')
</body>
</html>
