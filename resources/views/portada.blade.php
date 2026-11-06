<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('portada.titulo_pagina') }}</title>
    <meta name="description" content="{{ __('portada.descripcion') }}">
    {{-- Portal en construcción: fuera del índice hasta la puesta en marcha. --}}
    <meta name="robots" content="noindex, nofollow">
    @foreach (config('idiomas.disponibles') as $codigo => $nombre)
        <link rel="alternate" hreflang="{{ $codigo }}" href="{{ route('portada', ['idioma' => $codigo]) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ route('portada', ['idioma' => config('idiomas.predeterminado')]) }}">
    <style>
        :root {
            --fondo: #FFFFFF;
            --fondo-suave: #F3F2EF;
            --tinta: #16161A;
            --tinta-suave: #5C5C66;
            --linea: #E7E5E0;
            --serif: "Iowan Old Style", "Palatino Linotype", Palatino, Georgia, serif;
            --sans: "Segoe UI", system-ui, -apple-system, Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--fondo); color: var(--tinta); font-family: var(--sans); font-size: 16px; line-height: 1.6; }
        a { color: var(--tinta); }
        a:focus-visible { outline: 2px solid var(--tinta); outline-offset: 3px; }
        .contenedor { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        header { border-bottom: 1px solid var(--linea); }
        header .contenedor { display: flex; justify-content: space-between; align-items: center; gap: 16px; min-height: 72px; flex-wrap: wrap; }
        .marca { font-family: var(--serif); font-size: 24px; text-decoration: none; }
        nav ul { list-style: none; display: flex; gap: 4px; margin: 0; padding: 0; }
        nav a { display: inline-flex; align-items: center; min-height: 44px; padding: 0 12px; text-decoration: none; font-size: 14px; color: var(--tinta-suave); }
        nav a[aria-current="page"] { color: var(--tinta); font-weight: 600; }
        main .contenedor { padding-top: 96px; padding-bottom: 96px; }
        .etiqueta { margin: 0 0 20px; font-size: 13px; letter-spacing: .12em; text-transform: uppercase; color: var(--tinta-suave); }
        h1 { margin: 0; max-width: 16ch; font-family: var(--serif); font-weight: 400; font-size: clamp(40px, 7vw, 76px); line-height: 1.05; letter-spacing: -.02em; }
        .texto { max-width: 56ch; margin: 28px 0 0; font-size: 18px; color: #3A3A42; }
        ol { list-style: none; margin: 64px 0 0; padding: 32px; background: var(--fondo-suave); display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; counter-reset: paso; }
        ol li { counter-increment: paso; display: flex; flex-direction: column; gap: 8px; }
        ol li::before { content: counter(paso); font-family: var(--serif); font-size: 28px; }
        footer { border-top: 1px solid var(--linea); }
        footer .contenedor { padding-top: 24px; padding-bottom: 24px; font-size: 14px; color: var(--tinta-suave); }
    </style>
</head>
<body>
    <header>
        <div class="contenedor">
            <a class="marca" href="{{ route('portada') }}">NextGen Apparel</a>
            <nav aria-label="{{ __('portada.cambiar_idioma') }}">
                <ul>
                    @foreach (config('idiomas.disponibles') as $codigo => $nombre)
                        <li>
                            <a href="{{ route('portada', ['idioma' => $codigo]) }}"
                               hreflang="{{ $codigo }}" lang="{{ $codigo }}"
                               @if ($codigo === app()->getLocale()) aria-current="page" @endif>{{ $nombre }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="contenedor">
            <p class="etiqueta">{{ __('portada.etiqueta') }}</p>
            <h1>{{ __('portada.titular') }}</h1>
            <p class="texto">{{ __('portada.texto') }}</p>
            <ol>
                <li>{{ __('portada.paso_1') }}</li>
                <li>{{ __('portada.paso_2') }}</li>
                <li>{{ __('portada.paso_3') }}</li>
            </ol>
        </div>
    </main>

    <footer>
        <div class="contenedor">© {{ now()->year }} NextGen Apparel · {{ __('portada.pie') }}</div>
    </footer>
</body>
</html>
