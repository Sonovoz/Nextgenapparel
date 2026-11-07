@php
    use App\Dominio\Catalogo\Escaparate;
    $idioma = app()->getLocale();
    $numero = fn (int $mm) => rtrim(rtrim(number_format($mm / 10, 1, $idioma === 'en' ? '.' : ',', ''), '0'), ',.');
    $cm = fn (?int $mm) => $mm === null ? '—' : $numero($mm).' cm';
    $primerColor = $colores[0] ?? null;
    $fotoInicial = $primerColor['caras']['delantera'] ?? $primerColor['caras']['trasera'] ?? null;
    // «Tu marca, aquí» solo tiene sentido si hay alguna maqueta con zonas colocadas.
    $conTuMarca = collect($colores)->contains(fn ($c) => collect($c['caras'])->contains(fn ($cara) => ! empty($cara['zonas'])));
    $textosVisor = [
        'prenda' => $prenda->nombre,
        'sin_foto' => __('tienda.sin_foto'),
        'listo' => __('tienda.listo'),
        'listo_2' => __('tienda.listo_2'),
        'ver_coleccion' => __('tienda.ver_tu_coleccion'),
        'ocultar_coleccion' => __('tienda.ocultar_coleccion'),
        'coleccion_texto' => __('tienda.coleccion_texto'),
        'ver_en_grande' => __('tienda.ver_en_grande'),
        'error_archivo' => __('tienda.error_archivo'),
        'error_grande' => __('tienda.error_grande'),
        'medida_impresion' => __('tienda.medida_impresion'),
        'calidad_sin_diseno' => __('tienda.calidad_sin_diseno'),
        'tus_disenos' => __('tienda.tus_disenos'),
        'anadir_diseno' => __('tienda.anadir_diseno'),
        'elige_zona_nuevo' => __('tienda.elige_zona_nuevo'),
        'solapa' => __('tienda.solapa'),
        'listo_varios' => __('tienda.listo_varios'),
        'pestana_logo' => __('tienda.pestana_logo'),
        'decimal' => $idioma === 'en' ? '.' : ',',
    ];
    // Misma regla que App\Dominio\Disenos\Calidad: umbrales de Ajustes y textos de aviso.
    $calidadVisor = [
        'umbrales' => \App\Dominio\Disenos\Calidad::umbrales(),
        'textos' => trans('disenos'),
    ];
@endphp

@push('estilos')
    <style>
        .ficha { display: flex; flex-wrap: wrap; gap: 48px 64px; align-items: flex-start; }
        .galeria { flex: 1 1 520px; min-width: 0; }
        .visor { position: relative; background: radial-gradient(120% 90% at 50% 40%, #FAF9F7 0%, var(--fondo-suave) 70%); border-radius: 4px; aspect-ratio: 5 / 6; display: flex; align-items: center; justify-content: center; overflow: hidden; transition: box-shadow .25s ease; }
        .visor.soltando { box-shadow: inset 0 0 0 2px var(--tinta); }
        .visor-lienzo { position: relative; width: 86%; aspect-ratio: 5 / 6; transform: perspective(1400px) rotateY(0deg); transition: transform .19s ease-in; touch-action: pan-y; }
        .visor-lienzo.girando { transform: perspective(1400px) rotateY(90deg); }
        .visor-lienzo.volviendo { transform: perspective(1400px) rotateY(-90deg); transition: none; }
        .visor-lienzo img { width: 100%; height: auto; user-select: none; }
        .capa { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; transition: opacity .35s ease; }
        .capa.entrando { opacity: 0; }
        .capa img { position: absolute; inset: 0; }
        .diseno { position: absolute; pointer-events: none; }
        .diseno.estampar { animation: estampar .6s cubic-bezier(.2, .8, .2, 1) both; }
        @keyframes estampar { 0% { opacity: 0; transform: scale(1.18); filter: blur(6px); } 55% { opacity: 1; transform: scale(.97); filter: blur(0); } 100% { opacity: 1; transform: scale(1); } }
        .zona-marca { position: absolute; border: 2px dashed currentColor; background: color-mix(in srgb, currentColor 8%, transparent); }
        .zona-marca.elegida { border-style: solid; }
        .zona-marca.con-diseno { background: transparent; border-width: 1px; opacity: .7; }
        .diseno { z-index: 1; }
        .diseno.movible { pointer-events: auto; cursor: grab; touch-action: none; }
        .diseno.arrastrando { cursor: grabbing; }
        .zona-guia { position: absolute; border: 1px dashed rgba(22,22,26,.45); border-radius: 2px; opacity: 0; transition: opacity .2s ease; pointer-events: none; z-index: 2; }
        .visor-lienzo.ajustando .zona-guia { opacity: 1; }
        .ajustador { display: flex; flex-direction: column; gap: 14px; padding-top: 4px; border-top: 1px solid rgba(255,255,255,.14); }
        .ajustador h3 { margin: 12px 0 0; font-size: 15px; font-weight: 500; }
        .fila-tamano { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .fila-tamano input[type=range] { flex: 1 1 180px; accent-color: #fff; min-height: 32px; }
        .fila-tamano output { font-size: 14px; min-width: 150px; }
        .calidad { border-radius: 4px; padding: 14px 16px; background: rgba(255,255,255,.06); border-left: 4px solid #8F8D87; display: flex; flex-direction: column; gap: 4px; font-size: 14px; }
        .calidad[data-nivel="correcta"] { border-left-color: #3DBE7A; }
        .calidad[data-nivel="buena"] { border-left-color: #A6CF4F; }
        .calidad[data-nivel="justa"] { border-left-color: #F2B33D; }
        .calidad[data-nivel="baja"] { border-left-color: #F0624D; background: rgba(240,98,77,.12); }
        .calidad-cabecera { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
        .calidad-cabecera strong { font-size: 15px; font-weight: 600; }
        .calidad > p:empty, .calidad-cabecera span:empty { display: none; }
        .lupa { position: absolute; width: 170px; height: 170px; border-radius: 999px; overflow: hidden; pointer-events: none; background: #fff; box-shadow: 0 0 0 3px #fff, 0 14px 36px rgba(22,22,26,.35); z-index: 3; }
        .lupa .capa { position: absolute; }
        .lupa .capa img { width: 100%; height: 100%; }
        .tu-marca { margin-top: 24px; padding: 28px; background: #16161A; color: #fff; border-radius: 4px; display: flex; flex-direction: column; gap: 20px; }
        .tu-marca .etiqueta { color: #B9B7B0; margin: 0 0 6px; }
        .tu-marca h2 { margin: 0; font-size: clamp(26px, 3vw, 32px); line-height: 1.15; }
        .tu-marca p { margin: 0; }
        .tu-marca .claro { color: #C9C7C0; font-size: 14px; }
        .pestanas { display: flex; gap: 4px; background: rgba(255,255,255,.08); border-radius: 999px; padding: 4px; align-self: flex-start; flex-wrap: wrap; }
        .pestanas [role="tab"] { min-height: 40px; padding: 0 18px; border: 0; border-radius: 999px; background: transparent; color: #fff; font: inherit; font-size: 14px; cursor: pointer; }
        .pestanas [aria-selected="true"] { background: #fff; color: var(--tinta); }
        .tu-marca button:focus-visible, .tu-marca input:focus-visible, .soltar:focus-within { outline: 2px solid #fff; outline-offset: 3px; }
        .soltar { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; min-height: 140px; padding: 20px; border: 1.5px dashed rgba(255,255,255,.45); border-radius: 4px; text-align: center; cursor: pointer; transition: background .2s ease, border-color .2s ease; }
        .soltar:hover, .soltar.soltando { background: rgba(255,255,255,.06); border-color: #fff; }
        .soltar strong { font-weight: 500; }
        .soltar .nombre-logo { display: none; font-size: 13px; color: #C9C7C0; }
        .soltar.con-logo .nombre-logo { display: block; }
        .oculto { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
        .campo-marca { width: 100%; min-height: 52px; padding: 0 16px; border: 1px solid rgba(255,255,255,.35); border-radius: 4px; background: transparent; color: #fff; font: inherit; font-size: 18px; }
        .campo-marca::placeholder { color: #8F8D87; }
        .grupo { display: flex; flex-direction: column; gap: 10px; }
        .grupo > span { font-size: 13px; color: #C9C7C0; }
        .opciones { display: flex; flex-wrap: wrap; gap: 6px; }
        .boton-pildora.borde { border: 1px solid rgba(255,255,255,.35); color: #fff; min-height: 40px; }
        .boton-pildora.borde[aria-checked="true"] { background: #fff; color: var(--tinta); border-color: #fff; }
        .fuente-muestra { font-size: 18px; min-width: 56px; }
        .tinta-muestra { width: 40px; height: 40px; border-radius: 999px; border: 1px solid rgba(255,255,255,.35); padding: 0; cursor: pointer; display: flex; align-items: center; justify-content: center; background: transparent; color: #fff; font: inherit; font-size: 11px; }
        .tinta-muestra[aria-checked="true"] { box-shadow: 0 0 0 2px #16161A, 0 0 0 4px #fff; }
        .tinta-muestra span { width: 26px; height: 26px; border-radius: 999px; box-shadow: inset 0 0 0 1px rgba(255,255,255,.25); }
        .tu-marca-estado:empty { display: none; }
        .tu-marca-estado { font-size: 15px; animation: aparecer .5s ease both; }
        .tu-marca-error { color: #FFB4A9; font-size: 14px; }
        .acciones-marca { display: flex; flex-wrap: wrap; gap: 8px; }
        .boton-claro { min-height: 48px; padding: 0 22px; border-radius: 999px; border: 0; background: #fff; color: var(--tinta); font: inherit; font-size: 14px; font-weight: 500; cursor: pointer; }
        .boton-texto { min-height: 48px; padding: 0 12px; border: 0; background: transparent; color: #C9C7C0; font: inherit; font-size: 14px; text-decoration: underline; cursor: pointer; }
        .coleccion { margin-top: 72px; }
        .coleccion h2 { font-size: clamp(30px, 4vw, 40px); margin: 0; }
        .coleccion-rejilla { margin-top: 28px; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .coleccion-tarjeta { border: 0; padding: 0; background: transparent; font: inherit; text-align: left; cursor: pointer; display: flex; flex-direction: column; gap: 10px; animation: aparecer .6s cubic-bezier(.2, .8, .2, 1) both; }
        .coleccion-foto { background: var(--fondo-suave); border-radius: 4px; aspect-ratio: 5 / 6; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .coleccion-tarjeta:hover .coleccion-foto .visor-lienzo { transform: scale(1.03); transition: transform .4s ease; }
        .coleccion-nombre { font-size: 14px; }
        @keyframes aparecer { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) {
            .visor-lienzo, .capa, .soltar { transition: none; }
            .diseno.estampar, .coleccion-tarjeta, .tu-marca-estado { animation: none; }
        }
        .zona-marca span { position: absolute; left: 0; top: -20px; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; white-space: nowrap; }
        .visor-controles { position: absolute; top: 16px; left: 16px; right: 16px; display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
        .segmento { display: flex; background: #fff; border-radius: 999px; padding: 4px; gap: 2px; }
        .boton-pildora { min-height: 36px; padding: 0 16px; border: 0; border-radius: 999px; cursor: pointer; font: inherit; font-size: 13px; background: transparent; color: var(--tinta); }
        .boton-pildora[aria-pressed="true"] { background: var(--tinta); color: #fff; }
        .boton-borde { min-height: 44px; padding: 0 16px; border-radius: 999px; border: 1px solid var(--tinta); background: #fff; cursor: pointer; font: inherit; font-size: 13px; }
        .boton-borde[aria-pressed="true"] { background: var(--tinta); color: #fff; }
        .visor-nota { position: absolute; left: 16px; bottom: 12px; margin: 0; font-size: 12px; color: var(--tinta-suave); }
        .info { flex: 1 1 360px; max-width: 480px; min-width: 0; display: flex; flex-direction: column; gap: 28px; }
        .info h1 { margin: 0; font-size: clamp(34px, 4vw, 44px); line-height: 1.1; font-weight: 300; }
        .precio { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; margin-top: 8px; }
        .precio strong { font-family: var(--serif); font-weight: 400; font-size: 32px; }
        .suave { color: var(--tinta-suave); font-size: 14px; margin: 0; }
        .muestras { display: flex; flex-wrap: wrap; gap: 6px; }
        .muestra { width: 44px; height: 44px; border-radius: 999px; border: 1px solid transparent; background: transparent; padding: 0; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .muestra[aria-checked="true"] { border-color: var(--tinta); }
        .muestra span { width: 32px; height: 32px; border-radius: 999px; box-shadow: inset 0 0 0 1px rgba(22,22,26,.14); }
        .chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .chip { min-width: 52px; height: 40px; border: 1px solid #DAD7D0; display: flex; align-items: center; justify-content: center; font-size: 13px; padding: 0 8px; }
        .zonas { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 8px; }
        .zona-tarjeta { border: 1px solid #DAD7D0; padding: 12px; display: flex; flex-direction: column; gap: 2px; }
        .cta { min-height: 56px; background: var(--tinta); color: #fff; border: 0; font: inherit; font-size: 15px; font-weight: 500; cursor: not-allowed; opacity: .85; }
        .pasos-ficha { list-style: none; margin: 0; padding: 20px; background: #F7F6F3; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; font-size: 13px; line-height: 1.4; counter-reset: paso; }
        .pasos-ficha li { counter-increment: paso; display: flex; flex-direction: column; gap: 6px; }
        .pasos-ficha li::before { content: counter(paso); font-family: var(--serif); font-size: 22px; }
        details { border-bottom: 1px solid var(--linea); }
        details:first-of-type { border-top: 1px solid var(--linea); }
        summary { min-height: 56px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; list-style: none; }
        summary::-webkit-details-marker { display: none; }
        summary::after { content: '+'; font-size: 20px; }
        details[open] summary::after { content: '−'; }
        details ul, details table { margin: 0 0 20px; font-size: 14px; color: #3A3A42; }
        table { border-collapse: collapse; width: 100%; }
        th, td { text-align: left; padding: 6px 8px 6px 0; border-bottom: 1px solid var(--linea); }
        .visor-controles { z-index: 4; }
        @media (max-width: 600px) {
            .pasos-ficha { grid-template-columns: 1fr; }
            .visor-controles { top: 10px; left: 10px; right: 10px; flex-wrap: nowrap; }
            .boton-pildora { min-height: 34px; padding: 0 12px; font-size: 12px; }
            .boton-borde { min-height: 42px; padding: 0 12px; font-size: 12px; white-space: nowrap; }
            .tu-marca { padding: 22px 18px; }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/visor.js') }}?v={{ @filemtime(public_path('js/visor.js')) }}" defer></script>
@endpush

<x-tienda :titulo="($prenda->seo_titulo ?: $prenda->nombre).' · Nextgenapparel'" :descripcion="$prenda->seo_descripcion ?: \Illuminate\Support\Str::limit((string) $prenda->descripcion, 155)" :alternos="$alternos" :seo="$seo">
    <div class="contenedor">
        <nav class="ruta" aria-label="Ruta">
            <a href="{{ route('portada') }}">{{ __('tienda.inicio') }}</a><span aria-hidden="true">/</span>
            <a href="{{ route('tienda.familia', ['familia' => Escaparate::slug($familia, $idioma)]) }}">{{ $familia->nombre }}</a><span aria-hidden="true">/</span>
            <span>{{ $prenda->nombre }}</span>
        </nav>

        <div class="ficha">
            <section class="galeria" aria-label="{{ $prenda->nombre }}">
                {{-- Estudio: a pantalla completa al diseñar (como la personalización de un videojuego). Fuera de él, la galería de la ficha. --}}
                <div class="estudio" id="estudio" aria-label="{{ __('tienda.estudio') }}">
                <div class="estudio-barra">
                    <p class="estudio-titulo"><span>{{ __('tienda.estudio') }}</span> {{ $prenda->nombre }}</p>
                    <p class="estudio-atajos" aria-hidden="true">
                        <span><kbd>←</kbd><kbd>→</kbd><kbd>↑</kbd><kbd>↓</kbd> {{ __('tienda.atajo_mover') }}</span>
                        <span><kbd>+</kbd><kbd>−</kbd> {{ __('tienda.atajo_tamano') }}</span>
                        <span><kbd>F</kbd> {{ __('tienda.atajo_girar') }}</span>
                        <span><kbd>C</kbd> {{ __('tienda.color') }}</span>
                        <span><kbd>L</kbd> {{ __('tienda.lupa') }}</span>
                        <span><kbd>Esc</kbd> {{ __('tienda.atajo_salir') }}</span>
                    </p>
                    <button type="button" class="estudio-cerrar" id="cerrar-estudio" aria-label="{{ __('tienda.cerrar_estudio') }}">×</button>
                </div>
                <div class="estudio-escena">
                <button type="button" class="girar anterior" data-girar aria-label="{{ __('tienda.atajo_girar') }}">‹</button>
                <div class="marco-visor">
                <div class="visor" id="visor" data-colores='@json($colores)' data-textos='@json($textosVisor)' data-calidad='@json($calidadVisor)'>
                    <div class="visor-controles">
                        <div class="segmento" role="group" aria-label="{{ __('tienda.delantera') }} / {{ __('tienda.trasera') }}">
                            <button type="button" class="boton-pildora" data-cara="delantera" aria-pressed="true">{{ __('tienda.delantera') }}</button>
                            <button type="button" class="boton-pildora" data-cara="trasera" aria-pressed="false">{{ __('tienda.trasera') }}</button>
                        </div>
                        <button type="button" class="boton-borde" id="ver-zonas" aria-pressed="false">{{ __('tienda.ver_zonas') }}</button>
                    </div>
                    <div class="visor-lienzo" id="lienzo">
                        <div class="capa">
                            @if ($fotoInicial)
                                <img id="foto" fetchpriority="high" src="{{ $fotoInicial['url'] }}" alt="{{ $fotoInicial['alt'] ?? $prenda->nombre }}" width="1000" height="1200">
                            @else
                                <p class="sin-foto" id="foto-vacia">{{ __('tienda.sin_foto') }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="lupa" id="lupa" hidden></div>
                    <p class="visor-nota">{{ __('tienda.vista_orientativa') }}</p>
                    <p class="pista" id="pista" aria-hidden="true">{{ __('tienda.pista_arrastrar') }}</p>
                </div>
                </div>
                <button type="button" class="girar siguiente" data-girar aria-label="{{ __('tienda.atajo_girar') }}">›</button>
                <button type="button" class="boton-lupa" id="boton-lupa" aria-pressed="false">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                    {{ __('tienda.lupa') }}
                </button>
                <div class="pieles" id="pieles" aria-label="{{ __('tienda.coleccion_titulo') }}">
                    <p class="pieles-titulo"><span>{{ __('tienda.coleccion_titulo') }}</span> <span id="coleccion-texto"></span></p>
                    <div class="pieles-lista" id="pieles-lista"></div>
                </div>
                </div>
                @if ($conTuMarca)
                    <div class="estudio-panel">
                        @include('tienda._tu-marca')
                    </div>
                @endif
                </div>

                @if ($conTuMarca)
                    <div class="invitacion-estudio">
                        <div>
                            <p class="etiqueta">{{ __('tienda.tu_marca_etiqueta') }}</p>
                            <p class="invitacion-titulo">{{ __('tienda.tu_marca_titulo') }}</p>
                            <p class="suave">{{ __('tienda.invitacion_texto') }}</p>
                        </div>
                        <button type="button" class="boton principal" data-abrir-estudio>{{ __('tienda.abrir_estudio') }}</button>
                    </div>
                @endif
            </section>

            <section class="info" aria-label="{{ __('tienda.detalles') }}">
                <div>
                    <p class="etiqueta">{{ $familia->nombre }} · DTI Textil</p>
                    <h1>{{ $prenda->nombre }}</h1>
                    @if ($precio)
                        <div class="precio">
                            <span class="suave">{{ __('tienda.desde') }}</span>
                            <strong>{{ $precio }}</strong>
                            <span class="suave">{{ __('tienda.por_unidad') }} · {{ __('tienda.con_impresion') }} · {{ __('tienda.iva_incluido') }}</span>
                        </div>
                    @endif
                    @if ($prenda->descripcion)
                        <p style="margin: 16px 0 0; color: #3A3A42;">{{ $prenda->descripcion }}</p>
                    @endif
                </div>

                @if (count($colores) > 0)
                    <div>
                        <p class="suave" style="margin-bottom: 10px;">{{ __('tienda.color') }} · <strong id="nombre-color" style="color: var(--tinta); font-weight: 500;">{{ $primerColor['nombre'] }}</strong></p>
                        <div class="muestras" role="radiogroup" aria-label="{{ __('tienda.color') }}">
                            @foreach ($colores as $i => $color)
                                <button type="button" class="muestra" role="radio" aria-checked="{{ $i === 0 ? 'true' : 'false' }}" aria-label="{{ $color['nombre'] }}" data-color="{{ $i }}">
                                    <span style="background: {{ $color['hex'] }};"></span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <p class="suave" style="margin-bottom: 10px;">{{ __('tienda.tallas_disponibles') }}</p>
                    <div class="chips">
                        @foreach ($tallas as $talla)
                            <span class="chip">{{ $talla }}</span>
                        @endforeach
                    </div>
                    <p class="suave" style="margin-top: 10px; font-size: 13px;">{{ __('tienda.tallas_nota') }}</p>
                </div>

                @if ($prenda->zonas->isNotEmpty())
                    <div>
                        <p class="suave" style="margin-bottom: 10px;">{{ __('tienda.donde_imprimimos') }}</p>
                        <div class="zonas">
                            @foreach ($prenda->zonas as $zona)
                                <div class="zona-tarjeta">
                                    <span style="font-size: 14px; font-weight: 500;">{{ $zona->nombre }}</span>
                                    <span class="suave" style="font-size: 12px;">{{ __('tienda.hasta', ['medida' => $numero($zona->ancho_max_mm).' × '.$numero($zona->alto_max_mm).' cm']) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <p class="suave" style="margin-top: 10px; font-size: 13px;">{{ __('tienda.zonas_nota') }}</p>
                    </div>
                @endif

                <div style="display: flex; flex-direction: column; gap: 8px;">
                    @if ($conTuMarca)
                        <button type="button" class="boton principal" data-abrir-estudio style="width: 100%;">{{ __('tienda.abrir_estudio') }}</button>
                    @else
                        <button type="button" class="boton inactivo" disabled style="width: 100%;">{{ __('tienda.personalizar') }}</button>
                    @endif
                    <p class="suave" id="proximamente" style="font-size: 13px;">{{ __('tienda.proximamente') }} {{ __('tienda.minimo') }}</p>
                </div>

                <ol class="pasos-ficha">
                    <li>{{ __('tienda.paso_1') }}</li>
                    <li>{{ __('tienda.paso_2') }}</li>
                    <li>{{ __('tienda.paso_3') }}</li>
                </ol>

                <div>
                    <details>
                        <summary>{{ __('tienda.detalles') }}</summary>
                        <ul>
                            @if ($prenda->composicion)<li>{{ __('tienda.composicion') }}: {{ $prenda->composicion }}</li>@endif
                            @if ($prenda->gramaje)<li>{{ __('tienda.gramaje') }}: {{ $prenda->gramaje }} g/m²</li>@endif
                            @if ($prenda->detalles)<li>{{ $prenda->detalles }}</li>@endif
                        </ul>
                    </details>
                    @if ($medidas->isNotEmpty())
                        <details>
                            <summary>{{ __('tienda.guia_tallas') }}</summary>
                            <table>
                                <thead><tr><th scope="col">{{ __('tienda.talla') }}</th><th scope="col">{{ __('tienda.largo') }}</th><th scope="col">{{ __('tienda.ancho') }}</th><th scope="col">{{ __('tienda.manga') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($medidas as $medida)
                                        <tr><th scope="row">{{ $medida->talla->nombre }}</th><td>{{ $cm($medida->largo_mm) }}</td><td>{{ $cm($medida->ancho_mm) }}</td><td>{{ $cm($medida->manga_mm) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </details>
                    @endif
                    <details>
                        <summary>{{ __('tienda.zonas_calidad') }}</summary>
                        <ul><li>{{ __('tienda.calidad_texto') }}</li></ul>
                    </details>
                    <details>
                        <summary>{{ __('tienda.plazos') }}</summary>
                        <ul><li>{{ __('tienda.plazo_produccion', ['dias' => $familia->plazo_base_dias]) }}</li><li>{{ __('tienda.plazo_envio') }}</li></ul>
                    </details>
                    <details>
                        <summary>{{ __('tienda.garantia') }}</summary>
                        <ul><li>{{ __('tienda.garantia_desistimiento') }}</li><li>{{ __('tienda.garantia_defecto') }}</li></ul>
                    </details>
                </div>

                <p class="suave" style="font-size: 13px;">{{ __('tienda.pago_seguro') }}</p>
            </section>
        </div>

    </div>
</x-tienda>
