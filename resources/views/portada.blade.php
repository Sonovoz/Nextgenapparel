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
        // La espalda no compite con la carga inicial: su brillo se pide al terminar la página.
        window.addEventListener('load', function () {
            document.querySelectorAll('[data-mascara]').forEach(function (e) { e.style.setProperty('--mascara', 'url(' + e.dataset.mascara + ')'); });
            document.querySelectorAll('img[data-src]').forEach(function (i) { i.srcset = i.dataset.srcset; i.src = i.dataset.src; });
        });
    </script>
    <script>
        // Zona de juego: la camiseta gira sola. Al pasar el ratón se vuelve hacia ti y se
        // inclina un poco siguiendo el puntero, con un movimiento suavizado; para darle la
        // vuelta se arrastra (ratón o dedo). Al salir, vuelve sin saltos a su giro. Sin librerías.
        (function () {
            var escenario = document.getElementById('escenario');
            var giro = document.getElementById('giro');
            if (!escenario || !giro || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            var completa = giro.classList.contains('con-espalda'); // 360° si hay foto de la espalda
            var INCLINACION = 18;   // grados máximos al seguir el ratón
            var ARRASTRE = completa ? 0.35 : 0.25; // grados por píxel arrastrado
            var SUAVIDAD = 0.07;    // fracción del camino que recorre en cada fotograma
            var INICIO = completa ? { ry: -14, rx: 3 } : { ry: -26, rx: 3 }; // primer fotograma del giro automático
            var actual = { ry: 0, rx: 0 }, objetivo = { ry: 0, rx: 0 };
            var base = 0, puntero = { x: 0, y: 0 }, arrastre = null, volviendo = false, cuadro = null, espera = null;

            function poner(ry, rx) {
                giro.style.setProperty('--ry', ry.toFixed(2) + 'deg');
                giro.style.setProperty('--rx', rx.toFixed(2) + 'deg');
                var cara = ((ry % 360) + 540) % 360 - 180; // -180…180 respecto a la cara visible
                if (Math.abs(cara) > 90) cara = cara > 0 ? cara - 180 : cara + 180;
                giro.style.setProperty('--brillo', (50 + cara * 1.6).toFixed(0) + '%');
            }
            // Ángulo real en este instante (aunque lo esté moviendo la animación CSS).
            function anguloActual() {
                var m = getComputedStyle(giro).transform;
                if (!m || m === 'none') return 0;
                var v = m.replace(/^matrix(3d)?\(|\)$/g, '').split(',').map(Number);
                return v.length === 16 ? Math.atan2(-v[2], v[0]) * 180 / Math.PI : 0;
            }
            function cercano(angulo, referencia) { return angulo + Math.round((referencia - angulo) / 360) * 360; }
            function bucle() {
                actual.ry += (objetivo.ry - actual.ry) * SUAVIDAD;
                actual.rx += (objetivo.rx - actual.rx) * SUAVIDAD;
                poner(actual.ry, actual.rx);
                if (volviendo && Math.abs(objetivo.ry - actual.ry) < 0.4 && Math.abs(objetivo.rx - actual.rx) < 0.4) {
                    volviendo = false; cuadro = null;
                    escenario.classList.remove('manual'); // la animación arranca en su primer fotograma: sin salto
                    return;
                }
                cuadro = requestAnimationFrame(bucle);
            }
            function tomar() {
                clearTimeout(espera); volviendo = false;
                if (!escenario.classList.contains('manual')) {
                    actual.ry = anguloActual(); actual.rx = 0;
                    poner(actual.ry, actual.rx);
                    escenario.classList.add('manual');
                    // De frente o de espaldas: la cara más cercana a como está ahora.
                    base = completa ? Math.round(actual.ry / 180) * 180 : 0;
                }
                if (!cuadro) cuadro = requestAnimationFrame(bucle);
            }
            function apuntar() {
                objetivo.ry = base + puntero.x * INCLINACION * 2;
                objetivo.rx = -puntero.y * INCLINACION * 0.6;
            }
            function soltar() {
                clearTimeout(espera);
                espera = setTimeout(function () {
                    volviendo = true;
                    objetivo.ry = cercano(INICIO.ry, actual.ry); objetivo.rx = INICIO.rx;
                    if (!cuadro) cuadro = requestAnimationFrame(bucle);
                }, 1400);
            }

            escenario.addEventListener('pointermove', function (e) {
                var r = escenario.getBoundingClientRect();
                puntero.x = (e.clientX - r.left) / r.width - 0.5;
                puntero.y = (e.clientY - r.top) / r.height - 0.5;
                if (arrastre) {
                    base = arrastre.base + (e.clientX - arrastre.x) * ARRASTRE;
                    if (!completa) base = Math.max(-45, Math.min(45, base));
                    apuntar(); return;
                }
                if (e.pointerType === 'mouse') { tomar(); apuntar(); }
            });
            escenario.addEventListener('pointerdown', function (e) {
                tomar(); arrastre = { x: e.clientX, base: base };
                try { escenario.setPointerCapture(e.pointerId); } catch (err) {}
                if (e.pointerType === 'mouse') escenario.classList.add('arrastrando');
            });
            ['pointerup', 'pointercancel'].forEach(function (t) {
                escenario.addEventListener(t, function (e) {
                    arrastre = null; escenario.classList.remove('arrastrando');
                    if (e.pointerType !== 'mouse') soltar();
                });
            });
            escenario.addEventListener('pointerleave', function () { arrastre = null; escenario.classList.remove('arrastrando'); soltar(); });
        })();
    </script>
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

    {{-- 1. Zona de juego: la camiseta como el personaje de la pantalla de inicio de una consola --}}
    <section class="arena" aria-labelledby="titular">
        <div class="arena-fondo" aria-hidden="true">
            <span class="arena-halo uno"></span><span class="arena-halo dos"></span>
            <span class="arena-suelo"></span>
            @foreach ([[8, 0], [17, 3.2], [26, 1.4], [35, 5.1], [44, 2.3], [53, 6.4], [62, .8], [71, 4.2], [80, 2.9], [89, 5.7], [22, 7.1], [66, 6.9], [48, 8.2], [93, 1.9]] as [$x, $retraso])
                <span class="chispa" style="--x: {{ $x }}%; --retraso: {{ $retraso }}s;"></span>
            @endforeach
        </div>

        <div class="contenedor arena-rejilla">
            <div class="arena-texto">
                <p class="arena-jugador aparece"><span class="vivo" aria-hidden="true"></span>{{ __('portada.juego_linea') }}</p>
                <h1 id="titular" class="aparece retraso-1">{{ __('portada.hero_titular') }}</h1>
                <p class="lema aparece retraso-2">{{ __('portada.hero_texto') }}</p>
                <div class="arena-caminos aparece retraso-3">{!! $caminos('oscuro') !!}</div>
                <ul class="confianza aparece retraso-3">
                    @if ($minimo)<li>{{ __('portada.confianza_minimo', ['n' => $minimo]) }}</li>@endif
                    <li>{{ __('portada.confianza_calidad') }}</li>
                    <li>{{ __('portada.confianza_pago') }}</li>
                    @if ($plazo)<li>{{ __('portada.confianza_plazo', ['dias' => $plazo]) }}</li>@endif
                </ul>
            </div>

            <div class="escenario" id="escenario">
                <div class="hud hud-arriba" aria-hidden="true">
                    <span><b>Skin 01</b> · {{ __('portada.hud_skin') }}</span>
                    <span class="hud-marca">DTI Textil</span>
                </div>
                {{-- Con la foto de la espalda, vuelta completa de 360°; sin ella, giro de lado a lado. --}}
                @php $conEspalda = is_file(public_path('img/portada/camiseta-dragon-espalda-900.webp')); @endphp
                <div class="giro{{ $conEspalda ? ' con-espalda' : '' }}" id="giro">
                    <div class="cara frente">
                        <img class="prenda" src="@recurso('img/portada/camiseta-dragon-900.webp')"
                             srcset="@recurso('img/portada/camiseta-dragon-560.webp') 560w, @recurso('img/portada/camiseta-dragon-900.webp') 900w, @recurso('img/portada/camiseta-dragon-1120.webp') 1120w"
                             sizes="(min-width: 1024px) 40vw, 82vw" width="900" height="1018" alt="{{ __('portada.hud_alt') }}" fetchpriority="high" draggable="false">
                        <span class="brillo" aria-hidden="true" style="--mascara: url('@recurso('img/portada/camiseta-dragon-560.webp')');"></span>
                    </div>
                    @if ($conEspalda)
                        <div class="cara espalda">
                            <img class="prenda" data-src="@recurso('img/portada/camiseta-dragon-espalda-900.webp')"
                                 data-srcset="@recurso('img/portada/camiseta-dragon-espalda-560.webp') 560w, @recurso('img/portada/camiseta-dragon-espalda-900.webp') 900w, @recurso('img/portada/camiseta-dragon-espalda-1120.webp') 1120w"
                                 sizes="(min-width: 1024px) 40vw, 82vw" width="900" height="1018" alt="" loading="lazy" draggable="false">
                            <span class="brillo" aria-hidden="true" data-mascara="@recurso('img/portada/camiseta-dragon-espalda-560.webp')"></span>
                        </div>
                    @endif
                </div>
                <span class="sombra" aria-hidden="true"></span>
                <div class="hud hud-abajo" aria-hidden="true">
                    <ul class="atributos">
                        @foreach (['tacto' => 96, 'color' => 98, 'durabilidad' => 92] as $atributo => $nivel)
                            <li><span>{{ __('portada.stat_'.$atributo) }}</span><span class="barra"><i style="--nivel: {{ $nivel }}%;"></i></span></li>
                        @endforeach
                    </ul>
                    <span class="pista-girar">↻ {{ __('portada.hud_girar') }}</span>
                </div>
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
                        <span class="numero" aria-hidden="true"><small>{{ __('portada.nivel') }}</small>0{{ $i }}</span>
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
                                <span class="ir" aria-hidden="true"><small>{{ __('portada.seleccionar') }}</small>{!! $flecha !!}</span>
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
                <img src="@recurso('img/portada/macro.webp')" alt="" width="900" height="900" loading="lazy">
                <figcaption>{{ __('portada.macro_pie') }}</figcaption>
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
                        <p class="modo">{{ __('portada.modo') }} 0{{ $i }}</p>
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
