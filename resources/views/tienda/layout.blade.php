@php
    // El menú nunca debe impedir mostrar la página (autosuficiencia).
    $familiasMenu = rescue(fn () => \App\Dominio\Catalogo\Escaparate::familias(), collect(), report: false);
    $idiomaActual = app()->getLocale();
    $seo = $seo ?? [];
    $descripcionMeta = $descripcion ?? __('portada.descripcion');
    $canonical = $alternos[$idiomaActual];
    $localesOg = ['es' => 'es_ES', 'en' => 'en_GB'];
    $nombreTienda = rescue(fn () => \App\Models\Ajuste::obtener('tienda.nombre'), 'NextGen Apparel', report: false);
@endphp
<!DOCTYPE html>
<html lang="{{ $idiomaActual }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
    <meta name="description" content="{{ $descripcionMeta }}">
    {{-- Fuera del índice salvo en producción con el interruptor de Ajustes encendido (D102). --}}
    <meta name="robots" content="{{ \App\Dominio\Seo\Indexacion::metaRobots() }}">
    <link rel="canonical" href="{{ $canonical }}">
    @foreach ($alternos as $codigo => $url)
        <link rel="alternate" hreflang="{{ $codigo }}" href="{{ $url }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $alternos[config('idiomas.predeterminado')] }}">
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
    <style>
        :root {
            --fondo: #FFFFFF; --fondo-suave: #F3F2EF; --tinta: #16161A; --tinta-suave: #5C5C66; --linea: #E7E5E0;
            --serif: "Iowan Old Style", "Palatino Linotype", Palatino, Georgia, serif;
            --sans: "Segoe UI", system-ui, -apple-system, Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--fondo); color: var(--tinta); font-family: var(--sans); font-size: 16px; line-height: 1.6; }
        a { color: var(--tinta); }
        a:focus-visible, button:focus-visible { outline: 2px solid var(--tinta); outline-offset: 3px; }
        img { max-width: 100%; display: block; }
        .contenedor { max-width: 1280px; margin: 0 auto; padding: 0 24px; }
        .cabecera { border-bottom: 1px solid var(--linea); }
        .cabecera .contenedor { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 32px; min-height: 72px; }
        .marca { font-family: var(--serif); font-size: 24px; text-decoration: none; }
        .menu, .idiomas { list-style: none; display: flex; flex-wrap: wrap; gap: 4px 20px; margin: 0; padding: 0; }
        @media (max-width: 720px) {
            .cabecera .contenedor { display: grid; grid-template-columns: 1fr auto; grid-template-areas: "marca idiomas" "menu menu"; gap: 0 16px; min-height: 0; padding-top: 10px; }
            .marca { grid-area: marca; font-size: 21px; align-self: center; }
            .nav-idiomas { grid-area: idiomas; }
            .nav-menu { grid-area: menu; margin: 0 -24px; padding: 0 24px; overflow-x: auto; scrollbar-width: none; border-top: 1px solid var(--linea); }
            .nav-menu .menu { flex-wrap: nowrap; gap: 24px; }
            .menu a { white-space: nowrap; }
            .idiomas { gap: 12px; }
            .idiomas a { font-size: 13px; }
            .idiomas .nombre-largo { display: none; }
            .contenedor { padding: 0 16px; }
            .nav-menu { margin: 0 -16px; padding: 0 16px; }
        }
        @media (min-width: 721px) { .idiomas .nombre-corto { display: none; } }
        .menu a, .idiomas a { display: inline-flex; align-items: center; min-height: 44px; text-decoration: none; font-size: 14px; color: var(--tinta-suave); }
        .menu a[aria-current="page"], .idiomas a[aria-current="page"] { color: var(--tinta); font-weight: 600; }
        .pie { border-top: 1px solid var(--linea); margin-top: 96px; }
        .pie .contenedor { padding-top: 24px; padding-bottom: 24px; font-size: 14px; color: var(--tinta-suave); }
        .etiqueta { margin: 0 0 12px; font-size: 13px; letter-spacing: .12em; text-transform: uppercase; color: var(--tinta-suave); }
        h1, h2 { font-family: var(--serif); font-weight: 400; letter-spacing: -.02em; }
        .ruta { font-size: 13px; color: var(--tinta-suave); display: flex; flex-wrap: wrap; gap: 8px; padding: 20px 0; }
        .ruta a { color: var(--tinta-suave); text-decoration: none; }
        .rejilla { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 32px 24px; }
        .tarjeta { text-decoration: none; display: flex; flex-direction: column; gap: 12px; }
        .tarjeta-foto { background: var(--fondo-suave); border-radius: 4px; aspect-ratio: 5 / 6; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .tarjeta-foto img { width: 86%; height: auto; transition: transform .4s ease; }
        .tarjeta:hover .tarjeta-foto img { transform: scale(1.03); }
        .tarjeta-nombre { font-family: var(--serif); font-size: 22px; margin: 0; }
        .tarjeta-precio { margin: 0; font-size: 14px; color: var(--tinta-suave); }
        .sin-foto { color: var(--tinta-suave); font-size: 13px; }
        @media (prefers-reduced-motion: reduce) { .tarjeta-foto img { transition: none; } }
    </style>
    @stack('estilos')
</head>
<body>
    <header class="cabecera">
        <div class="contenedor">
            <a class="marca" href="{{ route('portada') }}">NextGen Apparel</a>
            <nav class="nav-menu" aria-label="{{ __('tienda.nuestras_prendas') }}">
                <ul class="menu">
                    @foreach ($familiasMenu as $f)
                        @php $urlFamilia = route('tienda.familia', ['familia' => \App\Dominio\Catalogo\Escaparate::slug($f, $idiomaActual)]); @endphp
                        <li><a href="{{ $urlFamilia }}" @if (url()->current() === $urlFamilia) aria-current="page" @endif>{{ $f->nombre }}</a></li>
                    @endforeach
                </ul>
            </nav>
            <nav class="nav-idiomas" aria-label="{{ __('portada.cambiar_idioma') }}">
                <ul class="idiomas">
                    @foreach (config('idiomas.disponibles') as $codigo => $nombre)
                        <li><a href="{{ $alternos[$codigo] }}" hreflang="{{ $codigo }}" lang="{{ $codigo }}" @if ($codigo === $idiomaActual) aria-current="page" @endif aria-label="{{ $nombre }}"><span class="nombre-largo">{{ $nombre }}</span><span class="nombre-corto" aria-hidden="true">{{ strtoupper($codigo) }}</span></a></li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="pie">
        <div class="contenedor">© {{ now()->year }} NextGen Apparel · {{ __('portada.pie') }}</div>
    </footer>
    @stack('scripts')
</body>
</html>
