@php
    use App\Dominio\Catalogo\Escaparate;
    $idioma = app()->getLocale();
    $flecha = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>';
    $caminos = function (string $clase = '') use ($flecha) {
        return '<div class="caminos '.$clase.'">'
            .'<a class="camino principal" href="#pasos"><span><strong>'.e(__('portada.cta_coleccion')).'</strong><small>'.e(__('portada.cta_coleccion_detalle')).'</small></span><span class="flecha">'.$flecha.'</span></a>'
            .'<a class="camino" href="#packs"><span><strong>'.e(__('portada.cta_packs')).'</strong><small>'.e(__('portada.cta_packs_detalle')).'</small></span><span class="flecha">'.$flecha.'</span></a>'
            .'</div>';
    };
@endphp

@push('scripts')
    <script>
        // Paso 1 en la portada: el logo se queda en este navegador (sessionStorage)
        // y la ficha lo coloca sobre la prenda al abrirse. No se sube a ningún sitio.
        (function () {
            var caja = document.getElementById('prueba-logo');
            if (!caja) return;
            var entrada = document.getElementById('logo-portada');
            var zona = caja.querySelector('.soltar-portada');
            var error = caja.querySelector('.error-logo');

            // Animación de subida: dura al menos MINIMO ms para que se vea y se entienda.
            var MINIMO = 1100;
            function fallo() { zona.classList.remove('subiendo'); error.textContent = caja.dataset.error; error.hidden = false; }
            function leer(archivo) {
                error.hidden = true;
                if (!archivo || !/^image\/(png|jpeg|webp|svg\+xml)$/.test(archivo.type)) { fallo(); return; }
                zona.classList.add('subiendo');
                var inicio = Date.now();
                var url = URL.createObjectURL(archivo);
                var img = new Image();
                img.onload = function () {
                    var w = img.naturalWidth || 1000, h = img.naturalHeight || 1000;
                    var escala = Math.min(1, 1600 / Math.max(w, h));
                    var c = document.createElement('canvas');
                    c.width = Math.round(w * escala); c.height = Math.round(h * escala);
                    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                    URL.revokeObjectURL(url);
                    try {
                        sessionStorage.setItem('nga.logo', JSON.stringify({ nombre: archivo.name, tipo: archivo.type, datos: c.toDataURL('image/png'), ancho: w, alto: h }));
                    } catch (e) { /* sin espacio: la ficha permite subirlo allí */ }
                    setTimeout(function () { window.location.href = caja.dataset.destino; }, Math.max(0, MINIMO - (Date.now() - inicio)));
                };
                img.onerror = function () { URL.revokeObjectURL(url); fallo(); };
                img.src = url;
            }

            entrada.addEventListener('change', function () { leer(entrada.files[0]); });
            ['dragover', 'dragenter'].forEach(function (t) { zona.addEventListener(t, function (e) { e.preventDefault(); zona.classList.add('soltando'); }); });
            ['dragleave', 'drop'].forEach(function (t) { zona.addEventListener(t, function () { zona.classList.remove('soltando'); }); });
            zona.addEventListener('drop', function (e) { e.preventDefault(); leer(e.dataTransfer.files[0]); });
        })();
    </script>
@endpush

<x-tienda :titulo="__('portada.titulo_pagina')" :descripcion="__('portada.descripcion')" :alternos="$alternos" :seo="$seo">

    {{-- 1. Portada: la idea y los dos caminos --}}
    <section class="portada-hero" aria-labelledby="titular">
        <div class="contenedor hero-rejilla">
            <div class="hero-texto">
                <p class="etiqueta aparece">{{ __('portada.hero_etiqueta') }}</p>
                <h1 id="titular" class="aparece retraso-1">{{ __('portada.hero_titular') }}</h1>
                <div class="separador corto aparece retraso-1"><span></span></div>
                <p class="lema aparece retraso-2">{{ __('portada.hero_texto') }}</p>
                <div class="aparece retraso-3">{!! $caminos() !!}</div>
                <ul class="confianza aparece retraso-3">
                    @if ($minimo)<li>{{ __('portada.confianza_minimo', ['n' => $minimo]) }}</li>@endif
                    <li>{{ __('portada.confianza_calidad') }}</li>
                    <li>{{ __('portada.confianza_pago') }}</li>
                    @if ($plazo)<li>{{ __('portada.confianza_plazo', ['dias' => $plazo]) }}</li>@endif
                </ul>
            </div>
            <div class="hero-fotos">
                <img class="principal" src="{{ asset('img/portada/pareja-1024.webp') }}"
                     srcset="{{ asset('img/portada/pareja-640.webp') }} 640w, {{ asset('img/portada/pareja-1024.webp') }} 1024w, {{ asset('img/portada/pareja-1600.webp') }} 1600w"
                     sizes="(min-width: 1024px) 46vw, 100vw" width="1600" height="1004" alt="" fetchpriority="high">
                <img class="secundaria" src="{{ asset('img/portada/sudadera-640.webp') }}" width="640" height="856" alt="" loading="lazy">
                <p class="sello" aria-hidden="true">DTI Textil<b>{{ __('portada.firma') }}</b></p>
            </div>
        </div>
    </section>

    {{-- 2. Tu marca en 3 pasos, con prueba del logo --}}
    <section class="seccion" id="pasos" aria-labelledby="titulo-pasos">
        <div class="contenedor">
            <p class="etiqueta aparece">{{ __('portada.pasos_etiqueta') }}</p>
            <h2 class="titulo-seccion aparece" id="titulo-pasos">{{ __('portada.pasos_titulo') }}</h2>
            <div class="pasos-rejilla">
                @foreach ([1, 2, 3] as $i)
                    <article class="paso aparece retraso-{{ $i }}">
                        <span class="numero" aria-hidden="true">0{{ $i }}</span>
                        <h3>{{ __('portada.paso_'.$i) }}</h3>
                        <p>{{ __('portada.paso_'.$i.'_texto') }}</p>
                    </article>
                @endforeach
            </div>

            @if ($urlPrueba)
                <div class="prueba-logo aparece" id="prueba-logo" data-destino="{{ $urlPrueba }}#tu-marca" data-error="{{ __('portada.soltar_error') }}">
                    <div>
                        <p class="etiqueta">{{ __('portada.paso_1') }}</p>
                        <h3 style="font-size: clamp(30px, 4vw, 44px); text-transform: uppercase;">{{ __('portada.soltar_titulo') }}</h3>
                        <p class="entradilla" style="font-size: 16px;">{{ __('portada.soltar_texto') }}</p>
                    </div>
                    <label class="soltar-portada" for="logo-portada">
                        <span class="en-reposo">
                            <span class="icono-subir"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 16V4m0 0-5 5m5-5 5 5M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg></span>
                            <span class="boton principal">{{ __('portada.soltar_boton') }}</span>
                            <small class="arrastra">{{ __('portada.soltar_arrastra') }}</small>
                            <small>{{ __('portada.soltar_privacidad') }}</small>
                        </span>
                        <span class="subiendo-estado" aria-live="polite">
                            <span class="icono-subir vuela"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 16V4m0 0-5 5m5-5 5 5M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg></span>
                            <strong>{{ __('portada.subiendo') }}</strong>
                            <span class="barra-subida"><span></span></span>
                        </span>
                        <small class="error-logo" role="alert" style="color: #C62828;" hidden></small>
                    </label>
                    <input class="oculto" type="file" id="logo-portada" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                </div>
            @endif
        </div>
    </section>

    {{-- 3. Packs --}}
    <section class="seccion" id="packs" aria-labelledby="titulo-packs">
        <div class="contenedor">
            <p class="etiqueta aparece">{{ __('portada.packs_etiqueta') }}</p>
            <h2 class="titulo-seccion aparece" id="titulo-packs">{{ __('portada.packs_titulo') }}</h2>
            <p class="entradilla aparece">{{ __('portada.packs_texto') }}</p>
            @if ($packs->isEmpty())
                <p class="entradilla">{{ __('portada.sin_packs') }}</p>
            @else
                <div class="carrusel">
                    @foreach ($packs as $pack)
                        <article class="pack aparece">
                            <div class="pack-foto">
                                @if ($pack['foto'])<img src="{{ $pack['foto']->url() }}" alt="" width="1000" height="1200" loading="lazy">@endif
                            </div>
                            <div class="pack-cuerpo">
                                <h3>{{ $pack['nombre'] }}</h3>
                                <p class="pack-precio">{{ $pack['precio'] }}<small>{{ __('portada.pack_iva') }}</small></p>
                                <ul class="pack-datos">
                                    <li>{{ __('portada.pack_prendas', ['n' => $pack['unidades']]) }}</li>
                                    @if ($pack['zonas'])<li>{{ __('portada.pack_impresion', ['zonas' => $pack['zonas']]) }}</li>@endif
                                    @if ($pack['descripcion'])<li>{{ $pack['descripcion'] }}</li>@endif
                                </ul>
                                {{-- La página de cada pack llega con el configurador (fase 5). --}}
                                <span class="boton inactivo" aria-disabled="true">{{ __('portada.pack_proximamente') }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- 4. Prendas --}}
    @if ($familias->isNotEmpty())
        <section class="seccion" id="prendas" aria-labelledby="titulo-prendas">
            <div class="contenedor">
                <p class="etiqueta aparece">{{ __('portada.prendas_etiqueta') }}</p>
                <h2 class="titulo-seccion aparece" id="titulo-prendas">{{ __('portada.prendas_titulo') }}</h2>
                <div class="familias-rejilla">
                    @foreach ($familias as $i => $item)
                        <a class="familia-tarjeta aparece retraso-{{ min($i + 1, 3) }}" href="{{ route('tienda.familia', ['familia' => Escaparate::slug($item['familia'], $idioma)]) }}">
                            @if ($item['foto'])<img src="{{ $item['foto']->url() }}" alt="" width="1000" height="1200" loading="lazy">@endif
                            <span class="texto">
                                <span>
                                    <h3>{{ $item['familia']->nombre }}</h3>
                                    @if ($item['desde'])<p>{{ __('portada.desde_impresion', ['precio' => $item['desde']]) }}</p>@endif
                                </span>
                                <span class="ir" aria-hidden="true">{!! $flecha !!}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 5. Calidad DTI Textil --}}
    <section class="seccion-calidad" id="calidad" aria-labelledby="titulo-calidad">
        <div class="contenedor calidad-rejilla">
            <figure class="lupa-tejido aparece" style="margin: 0;">
                <img src="{{ asset('img/portada/macro.webp') }}" alt="" width="900" height="900" loading="lazy">
                <figcaption>Macro · DTI Textil</figcaption>
            </figure>
            <div>
                <p class="etiqueta aparece">{{ __('portada.calidad_etiqueta') }}</p>
                <h2 class="titulo-seccion aparece" id="titulo-calidad">{{ __('portada.calidad_titulo') }}</h2>
                <p class="entradilla aparece">{{ __('portada.calidad_texto') }}</p>
                <ul class="ventajas">
                    @foreach ([1, 2, 3, 4] as $i)
                        <li class="aparece retraso-{{ min($i, 3) }}">
                            <span class="numero" aria-hidden="true">0{{ $i }}</span>
                            <div><h3>{{ __('portada.calidad_'.$i) }}</h3><p>{{ __('portada.calidad_'.$i.'_texto') }}</p></div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- 6. Para quién --}}
    <section class="seccion" aria-labelledby="titulo-quien">
        <div class="contenedor">
            <p class="etiqueta aparece">{{ __('portada.quien_etiqueta') }}</p>
            <h2 class="titulo-seccion aparece" id="titulo-quien">{{ __('portada.quien_titulo') }}</h2>
            <div class="quien-rejilla">
                @foreach ([1, 2, 3] as $i)
                    <article class="aparece retraso-{{ $i }}">
                        <h3>{{ __('portada.quien_'.$i) }}</h3>
                        <p>{{ __('portada.quien_'.$i.'_texto') }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 7. Preguntas y llamada final --}}
    <section class="seccion" aria-labelledby="titulo-preguntas">
        <div class="contenedor">
            <h2 class="titulo-seccion aparece" id="titulo-preguntas" style="text-align: center;">{{ __('portada.faq_titulo') }}</h2>
            <div class="separador corto" style="margin: 24px auto;"><span></span></div>
            <div class="preguntas">
                @if ($plazo)
                    <details><summary>{{ __('portada.faq_plazo') }}</summary><p>{{ __('portada.faq_plazo_texto', ['dias' => $plazo]) }}</p></details>
                @endif
                <details><summary>{{ __('portada.faq_minimo') }}</summary><p>{{ $minimo ? __('portada.faq_minimo_texto', ['n' => $minimo]) : __('portada.faq_minimo_libre') }}</p></details>
                <details><summary>{{ __('portada.faq_archivo') }}</summary><p>{{ __('portada.faq_archivo_texto') }}</p></details>
                <details><summary>{{ __('portada.faq_pago') }}</summary><p>{{ __('portada.faq_pago_texto') }}</p></details>
            </div>
        </div>
    </section>

    <section class="final" aria-labelledby="titulo-final">
        <div class="contenedor">
            <h2 class="titulo-seccion aparece" id="titulo-final">{{ __('portada.final_titulo') }}</h2>
            <p class="entradilla aparece" style="margin-left: auto; margin-right: auto;">{{ __('portada.final_texto') }}</p>
            <div class="aparece">{!! $caminos() !!}</div>
        </div>
    </section>
</x-tienda>
