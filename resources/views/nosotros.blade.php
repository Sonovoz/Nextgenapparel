@php
    // Sobre nosotros (D145): confianza en el centro de producción. Página fija, sin base de datos.
    $portada = route('portada');
    $flecha = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>';
@endphp

<x-tienda :titulo="__('nosotros.titulo_pagina')" :descripcion="__('nosotros.descripcion')" :alternos="$alternos" :seo="$seo">
<div class="pagina-nosotros">
    {{-- 1. Lema --}}
    <section class="nosotros-hero" aria-labelledby="titulo-nosotros">
        <div class="contenedor nosotros-hero-rejilla">
            <div>
                <p class="etiqueta">{{ __('nosotros.etiqueta') }}</p>
                <h1 class="titulo-seccion" id="titulo-nosotros">{{ __('nosotros.titular') }}</h1>
                <p class="entradilla">{{ __('nosotros.entradilla') }}</p>
            </div>
            <figure class="nosotros-foto">
                <img src="@recurso('img/portada/camiseta-dragon-900.webp')"
                     srcset="@recurso('img/portada/camiseta-dragon-560.webp') 560w, @recurso('img/portada/camiseta-dragon-900.webp') 900w, @recurso('img/portada/camiseta-dragon-1120.webp') 1120w"
                     sizes="(min-width: 1024px) 40vw, 90vw" alt="{{ __('nosotros.foto_alt') }}" width="900" height="1018" fetchpriority="high">
                <figcaption class="nosotros-sello"><span>{{ __('nosotros.foto_sello') }}</span><b>{{ __('nosotros.foto_sello_detalle') }}</b></figcaption>
            </figure>
        </div>
    </section>

    {{-- 2. Certificaciones --}}
    <section class="seccion" aria-labelledby="titulo-sellos">
        <div class="contenedor">
            <p class="etiqueta aparece">{{ __('nosotros.sellos_etiqueta') }}</p>
            <h2 class="titulo-seccion aparece" id="titulo-sellos">{{ __('nosotros.sellos_titulo') }}</h2>
            <ol class="sellos">
                @foreach ([1, 2, 3] as $i)
                    <li class="sello-tarjeta aparece retraso-{{ $i }}">
                        <p class="sello-numero"><span aria-hidden="true">0{{ $i }}</span> · {{ __('nosotros.sello_'.$i.'_etiqueta') }}</p>
                        <h3>{{ __('nosotros.sello_'.$i) }}</h3>
                        <p>{{ __('nosotros.sello_'.$i.'_texto') }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- 3. Qué significa para tu pedido --}}
    <section class="seccion-calidad" aria-labelledby="titulo-pedido">
        <div class="contenedor calidad-rejilla">
            <div>
                <p class="etiqueta aparece">{{ __('nosotros.pedido_etiqueta') }}</p>
                <h2 class="titulo-seccion aparece" id="titulo-pedido">{{ __('nosotros.pedido_titulo') }}</h2>
                <p class="entradilla aparece">{{ __('nosotros.pedido_texto') }}</p>
                <figure class="cita aparece">
                    <blockquote><p>{{ __('nosotros.cita') }}</p></blockquote>
                    <figcaption>{{ __('nosotros.cita_autor') }}</figcaption>
                </figure>
            </div>
            <ul class="ventajas">
                @foreach ([1, 2, 3, 4] as $i)
                    <li class="aparece retraso-{{ min($i, 3) }}">
                        <span class="numero" aria-hidden="true">0{{ $i }}</span>
                        <div>
                            <p class="pilar-etiqueta">{{ __('nosotros.pilar_'.$i.'_etiqueta') }}</p>
                            <h3>{{ __('nosotros.pilar_'.$i) }}</h3>
                            <p>{{ __('nosotros.pilar_'.$i.'_texto') }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 4. Red internacional --}}
    <section class="seccion" aria-labelledby="titulo-red">
        <div class="contenedor red">
            <p class="etiqueta aparece">{{ __('nosotros.red_etiqueta') }}</p>
            <h2 class="titulo-seccion aparece" id="titulo-red">{{ __('nosotros.red_titulo') }}</h2>
            <p class="entradilla aparece">{{ __('nosotros.red_texto') }}</p>
            <ul class="fichas aparece">
                @foreach (range(1, 6) as $i)
                    <li>{{ __('nosotros.red_'.$i) }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 5. Maquinaria DTI --}}
    <section class="seccion" aria-labelledby="titulo-dti">
        <div class="contenedor calidad-rejilla">
            <figure class="lupa-tejido aparece" style="margin: 0;">
                <img src="@recurso('img/portada/macro.webp')" alt="" width="900" height="900" loading="lazy">
                <figcaption>{{ __('portada.macro_pie') }}</figcaption>
            </figure>
            <div>
                <p class="etiqueta aparece">{{ __('nosotros.dti_etiqueta') }}</p>
                <h2 class="titulo-seccion aparece" id="titulo-dti">{{ __('nosotros.dti_titulo') }}</h2>
                <p class="entradilla aparece">{{ __('nosotros.dti_texto') }}</p>
                <ul class="fichas aparece">
                    @foreach (range(1, 4) as $i)
                        <li>{{ __('nosotros.dti_'.$i) }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- 6. Formación continua --}}
    <section class="seccion" aria-labelledby="titulo-academia">
        <div class="contenedor">
            <p class="etiqueta aparece">{{ __('nosotros.academia_etiqueta') }}</p>
            <h2 class="titulo-seccion aparece" id="titulo-academia">{{ __('nosotros.academia_titulo') }}</h2>
            <p class="entradilla aparece">{{ __('nosotros.academia_texto') }}</p>
            <div class="quien-rejilla">
                @foreach ([1, 2, 3] as $i)
                    <article class="aparece retraso-{{ $i }}">
                        <h3>{{ __('nosotros.academia_'.$i) }}</h3>
                        <ul class="academia-puntos">
                            @foreach (__('nosotros.academia_'.$i.'_puntos') as $punto)
                                <li>{{ $punto }}</li>
                            @endforeach
                        </ul>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 7. Llamada final --}}
    <section class="final" aria-labelledby="titulo-final">
        <div class="contenedor">
            <h2 class="titulo-seccion aparece" id="titulo-final">{{ __('nosotros.final_titulo') }}</h2>
            <p class="entradilla aparece" style="margin-left: auto; margin-right: auto;">{{ __('nosotros.final_texto') }}</p>
            <div class="caminos aparece">
                <a class="camino principal" href="{{ $portada }}#pasos"><span><strong>{{ __('portada.cta_coleccion') }}</strong><small>{{ __('portada.cta_coleccion_detalle') }}</small></span><span class="flecha">{!! $flecha !!}</span></a>
                <a class="camino" href="{{ $portada }}#packs"><span><strong>{{ __('portada.cta_packs') }}</strong><small>{{ __('portada.cta_packs_detalle') }}</small></span><span class="flecha">{!! $flecha !!}</span></a>
            </div>
        </div>
    </section>
</div>
</x-tienda>
