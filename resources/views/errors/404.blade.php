@php
    // Fuera de las rutas con idioma (o antes de su middleware), el idioma sale del primer tramo de la URL.
    $idioma = request()->segment(1);
    $idioma = array_key_exists((string) $idioma, config('idiomas.disponibles')) ? $idioma : config('idiomas.predeterminado');
    app()->setLocale($idioma);
    \Illuminate\Support\Facades\URL::defaults(['idioma' => $idioma]);
    $alternos = collect(config('idiomas.disponibles'))->keys()->mapWithKeys(fn ($i) => [$i => route('portada', ['idioma' => $i])])->all();
    $flecha = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>';
@endphp

<x-tienda :titulo="__('portada.error_titulo').' · Nextgenapparel'" :descripcion="__('portada.error_texto')" :alternos="$alternos" :seo="['noindex' => true]">
    <section class="final error-404" aria-labelledby="titulo-error">
        <div class="contenedor">
            <p class="etiqueta">404 · {{ __('portada.error_etiqueta') }}</p>
            <h1 class="titulo-seccion" id="titulo-error">{{ __('portada.error_titulo') }}</h1>
            <p class="entradilla" style="margin-left: auto; margin-right: auto;">{{ __('portada.error_texto') }}</p>
            <div class="caminos">
                <a class="camino principal" href="{{ route('portada') }}"><span><strong>{{ __('portada.error_inicio') }}</strong><small>{{ __('portada.error_inicio_detalle') }}</small></span><span class="flecha">{!! $flecha !!}</span></a>
                <a class="camino" href="{{ route('portada') }}#prendas"><span><strong>{{ __('portada.error_prendas') }}</strong><small>{{ __('portada.error_prendas_detalle') }}</small></span><span class="flecha">{!! $flecha !!}</span></a>
            </div>
        </div>
    </section>
</x-tienda>
