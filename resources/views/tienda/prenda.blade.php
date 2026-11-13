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
        'anadir_en' => __('tienda.anadir_en'),
        'elige_zona_nuevo' => __('tienda.elige_zona_nuevo'),
        'solapa' => __('tienda.solapa'),
        'listo_varios' => __('tienda.listo_varios'),
        'pestana_logo' => __('tienda.pestana_logo'),
        'procesando_diseno' => __('tienda.procesando_diseno'),
        'procesando_diseno_detalle' => __('tienda.procesando_diseno_detalle'),
        'procesando_pedido' => __('tienda.procesando_pedido'),
        'procesando_pedido_detalle' => __('tienda.procesando_pedido_detalle'),
        'zona_libre' => __('tienda.zona_libre'),
        'zona_con_diseno' => __('tienda.zona_con_diseno'),
        'zona_editando' => __('tienda.zona_editando'),
        'solapa_corto' => __('tienda.solapa_corto'),
        'por_prenda' => __('tienda.por_prenda'),
        'quitar_diseno' => __('tienda.quitar_diseno'),
        'quitar_diseno_zona' => __('tienda.quitar_diseno_zona'),
        'empezar_de_nuevo' => __('tienda.empezar_de_nuevo'),
        'confirmar_empezar' => __('tienda.confirmar_empezar'),
        'decimal' => $idioma === 'en' ? '.' : ',',
        // Niveles, precio en directo y cantidades (D142)
        'desde' => __('tienda.desde'),
        'por_unidad' => __('tienda.por_unidad'),
        'iva_incluido' => __('tienda.iva_incluido'),
        'tu_prenda' => __('tienda.tu_prenda'),
        'tu_prenda_desde' => __('tienda.tu_prenda_desde'),
        'un_diseno' => __('tienda.un_diseno'),
        'n_disenos' => __('tienda.n_disenos'),
        'total' => __('tienda.total'),
        'ud' => __('tienda.ud'),
        'uds' => __('tienda.uds'),
        'media' => __('tienda.media'),
        'decision_uno' => __('tienda.decision_uno'),
        'decision_varios' => __('tienda.decision_varios'),
        'decision_pregunta' => __('tienda.decision_pregunta'),
        'siguiente_cantidades' => __('tienda.siguiente_cantidades'),
        'siguiente_resumen' => __('tienda.siguiente_resumen'),
        'elige_cantidades' => __('tienda.elige_cantidades'),
        'quitar_color' => __('tienda.quitar_color'),
        'una_menos' => __('tienda.una_menos'),
        'una_mas' => __('tienda.una_mas'),
        'ver_en_prenda' => __('tienda.ver_en_prenda'),
        'resumen_disenos' => __('tienda.resumen_disenos'),
        'resumen_prendas' => __('tienda.resumen_prendas'),
        'resumen_total' => __('tienda.resumen_total'),
        'anadir_pedido' => __('tienda.anadir_pedido'),
        'anadido_desde' => __('tienda.anadido_desde'),
        'anadiendo' => __('tienda.anadiendo'),
        'error_anadir' => __('tienda.error_anadir'),
        'calculando' => __('tienda.calculando'),
        'ia_copiar' => __('tienda.ia_copiar'),
        'ia_copiado' => __('tienda.ia_copiado'),
        // Subida de los logos al pasar al pedido (fase 4).
        'comprobando' => __('disenos.comprobando'),
        'error_original' => __('disenos.error_original'),
        'error_no_png' => __('disenos.error_no_png'),
        'error_red' => __('disenos.error_red'),
        'error_servidor' => __('disenos.error_servidor'),
        'error_sin_ppp' => __('disenos.error_sin_ppp'),
        'exigir_ppp' => (bool) \App\Models\Ajuste::obtener('disenos.exigir_ppp'),
        'error_muchas' => __('disenos.error_muchas'),
        'error_conexion' => __('disenos.error_conexion'),
        'depurar' => (bool) config('app.debug'),
        'biblioteca_quitar' => __('disenos.biblioteca_quitar'),
        'biblioteca_usar' => __('disenos.biblioteca_usar'),
        'error_caducada' => __('disenos.error_caducada'),
        'espera_subida' => __('disenos.espera_subida'),
        'error_cambiado' => __('disenos.error_cambiado'),
        'biblioteca_confirmar' => __('disenos.biblioteca_confirmar'),
        'error_pesado' => __('disenos.error_pesado', ['mb' => \App\Dominio\Disenos\AnalizadorPng::maxMbEfectivo()]),
    ];
    // Misma regla que App\Dominio\Disenos\Calidad: umbrales de Ajustes y textos de aviso.
    $calidadVisor = [
        'umbrales' => \App\Dominio\Disenos\Calidad::umbrales(),
        'textos' => trans('disenos'),
        'ia' => \App\Dominio\Disenos\MejoraIa::paraVisor(),
    ];
@endphp

@push('estilos')
    <style>
        .ficha { display: flex; flex-wrap: wrap; gap: 48px 64px; align-items: flex-start; }
        .galeria { flex: 1 1 520px; min-width: 0; }
        .visor { position: relative; background: radial-gradient(90% 70% at 50% 40%, #1C1E2C 0%, var(--fondo-suave) 75%); border-radius: 4px; aspect-ratio: 5 / 6; display: flex; align-items: center; justify-content: center; overflow: hidden; transition: box-shadow .25s ease; }
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
        .ajustador h3, .ajustador .ajustador-titulo { margin: 12px 0 0; font-size: 15px; font-weight: 500; font-family: var(--sans); text-transform: none; letter-spacing: 0; }
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
        .pestanas [aria-selected="true"] { background: #fff; color: #0B0B10; }
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
        .boton-pildora.borde[aria-checked="true"] { background: #fff; color: #0B0B10; border-color: #fff; }
        .fuente-muestra { font-size: 18px; min-width: 56px; }
        .tinta-muestra { width: 40px; height: 40px; border-radius: 999px; border: 1px solid rgba(255,255,255,.35); padding: 0; cursor: pointer; display: flex; align-items: center; justify-content: center; background: transparent; color: #fff; font: inherit; font-size: 11px; }
        .tinta-muestra[aria-checked="true"] { box-shadow: 0 0 0 2px #16161A, 0 0 0 4px #fff; }
        .tinta-muestra span { width: 26px; height: 26px; border-radius: 999px; box-shadow: inset 0 0 0 1px rgba(255,255,255,.25); }
        .tu-marca-estado:empty { display: none; }
        .tu-marca-estado { font-size: 15px; animation: aparecer .5s ease both; }
        .tu-marca-error { color: #FFB4A9; font-size: 14px; }
        .acciones-marca { display: flex; flex-wrap: wrap; gap: 8px; }
        .boton-claro { min-height: 48px; padding: 0 22px; border-radius: 999px; border: 0; background: #fff; color: #0B0B10; font: inherit; font-size: 14px; font-weight: 500; cursor: pointer; }
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
        .segmento { display: flex; background: var(--papel); border: 1px solid var(--linea); border-radius: 999px; padding: 4px; gap: 2px; }
        .boton-pildora { min-height: 36px; padding: 0 16px; border: 0; border-radius: 999px; cursor: pointer; font: inherit; font-size: 13px; background: transparent; color: var(--tinta); }
        .boton-pildora[aria-pressed="true"] { background: var(--tinta); color: var(--fondo); }
        .boton-borde { min-height: 44px; padding: 0 16px; border-radius: 999px; border: 1px solid var(--linea-fuerte); background: rgba(8, 8, 13, .6); color: var(--tinta); cursor: pointer; font: inherit; font-size: 13px; }
        .boton-borde[aria-pressed="true"] { background: var(--tinta); color: var(--fondo); }
        .visor-nota { position: absolute; left: 16px; bottom: 12px; margin: 0; font-size: 12px; color: var(--tinta-suave); }
        .info { flex: 1 1 360px; max-width: 480px; min-width: 0; display: flex; flex-direction: column; gap: 28px; }
        .info h1 { margin: 0; font-size: clamp(34px, 4vw, 52px); line-height: 1; font-weight: 800; text-transform: uppercase; letter-spacing: -.03em; }
        .precio { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; margin-top: 8px; }
        .precio strong { font-family: var(--serif); font-weight: 400; font-size: 32px; }
        .suave { color: var(--tinta-suave); font-size: 14px; margin: 0; }
        .muestras { display: flex; flex-wrap: wrap; gap: 6px; }
        .muestra { width: 44px; height: 44px; border-radius: 999px; border: 1px solid transparent; background: transparent; padding: 0; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .muestra[aria-checked="true"] { border-color: var(--neon); box-shadow: 0 0 12px rgba(142, 160, 255, .5); }
        .muestra span { width: 32px; height: 32px; border-radius: 999px; box-shadow: inset 0 0 0 1px rgba(255,255,255,.28), 0 0 0 1px rgba(255,255,255,.12); }
        .chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .chip { min-width: 52px; height: 40px; border: 1px solid var(--linea-fuerte); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; padding: 0 8px; }
        .zonas { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 8px; }
        .zona-tarjeta { border: 1px solid var(--linea-fuerte); border-radius: 10px; padding: 12px; display: flex; flex-direction: column; gap: 2px; }
        .cta { min-height: 56px; background: var(--tinta); color: #fff; border: 0; font: inherit; font-size: 15px; font-weight: 500; cursor: not-allowed; opacity: .85; }
        .pasos-ficha { list-style: none; margin: 0; padding: 20px; background: var(--papel); border: 1px solid var(--linea); border-radius: 14px; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; font-size: 13px; line-height: 1.4; counter-reset: paso; }
        .pasos-ficha li { counter-increment: paso; display: flex; flex-direction: column; gap: 6px; }
        .pasos-ficha li::before { content: counter(paso); font-family: var(--serif); font-size: 22px; }
        details { border-bottom: 1px solid var(--linea); }
        details:first-of-type { border-top: 1px solid var(--linea); }
        summary { min-height: 56px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; list-style: none; }
        summary::-webkit-details-marker { display: none; }
        summary::after { content: '+'; font-size: 20px; }
        details[open] summary::after { content: '−'; }
        details ul, details table { margin: 0 0 20px; font-size: 14px; color: var(--tinta-suave); }
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
    <script src="@recurso('js/visor.js')" defer></script>
@endpush

<x-tienda :titulo="($prenda->seo_titulo ?: $prenda->nombre).' · Nextgenapparel'" :descripcion="$prenda->seo_descripcion ?: \Illuminate\Support\Str::limit((string) $prenda->descripcion, 155)" :alternos="$alternos" :seo="$seo">
    <div class="contenedor">
        @unless ($conTuMarca)
        <nav class="ruta" aria-label="Ruta">
            <a href="{{ route('portada') }}">{{ __('tienda.inicio') }}</a><span aria-hidden="true">/</span>
            <a href="{{ route('tienda.familia', ['familia' => Escaparate::slug($familia, $idioma)]) }}">{{ $familia->nombre }}</a><span aria-hidden="true">/</span>
            <span>{{ $prenda->nombre }}</span>
        </nav>
        @endunless

        <div class="ficha">
            <section class="galeria" aria-label="{{ $prenda->nombre }}">
                {{-- Estudio: a pantalla completa al diseñar (como la personalización de un videojuego). Fuera de él, la galería de la ficha. --}}
                @php $urlFamilia = route('tienda.familia', ['familia' => Escaparate::slug($familia, $idioma)]); @endphp
                <div class="estudio{{ $conTuMarca ? ' activo pagina' : '' }}" id="estudio" aria-label="{{ __('tienda.estudio') }}" data-salir="{{ $urlFamilia }}">
                <div class="estudio-barra">
                    <div class="estudio-identidad">
                        <a class="estudio-inicio" href="{{ route('portada') }}" aria-label="{{ __('tienda.inicio') }}"><img src="@recurso('img/marca/monograma.png')" alt="" width="190" height="147"></a>
                        <div>
                            <nav class="estudio-ruta" aria-label="Ruta"><a href="{{ route('portada') }}">{{ __('tienda.inicio') }}</a> / <a href="{{ $urlFamilia }}">{{ $familia->nombre }}</a></nav>
                            <h1 class="estudio-titulo">{{ $prenda->nombre }}</h1>
                        </div>
                    </div>
                    <p class="estudio-atajos" aria-hidden="true">
                        <span><kbd>←</kbd><kbd>→</kbd><kbd>↑</kbd><kbd>↓</kbd> {{ __('tienda.atajo_mover') }}</span>
                        <span><kbd>+</kbd><kbd>−</kbd> {{ __('tienda.atajo_tamano') }}</span>
                        <span><kbd>F</kbd> {{ __('tienda.atajo_girar') }}</span>
                        <span><kbd>C</kbd> {{ __('tienda.color') }}</span>
                        <span><kbd>L</kbd> {{ __('tienda.lupa') }}</span>
                        <span><kbd>Esc</kbd> {{ __('tienda.atajo_salir') }}</span>
                    </p>
                    @if ($pedidoActual)
                        {{-- D213: lo ya añadido sigue ahí; se está ampliando el pedido, no empezando otro. --}}
                        <a class="estudio-pedido" href="{{ \App\Http\Controllers\CarritoController::url('ver') }}">{{ trans_choice('tienda.tu_pedido', $pedidoActual['lineas'], ['n' => $pedidoActual['lineas'], 'uds' => $pedidoActual['unidades']]) }}</a>
                    @endif
                    @if ($precio)
                        <p class="estudio-precio" id="estudio-precio" aria-live="polite"><span id="precio-etiqueta">{{ __('tienda.desde') }}</span> <strong id="precio-importe">{{ $precio }}</strong> <small id="precio-detalle">{{ __('tienda.por_unidad') }} · {{ __('tienda.iva_incluido') }}</small></p>
                    @endif
                    <button type="button" class="estudio-cerrar" id="cerrar-estudio" aria-label="{{ __('tienda.cerrar_estudio') }}">×</button>
                </div>
                <div class="estudio-escena">
                <button type="button" class="girar anterior" data-girar aria-label="{{ __('tienda.atajo_girar') }}">‹</button>
                <div class="marco-visor">
                <div class="visor" id="visor" data-llevar='@json($llevar)' data-otras='@json($otras)' data-subida="{{ route('disenos.subir') }}" data-biblioteca="{{ route('disenos.lista') }}" data-borrar="{{ route('disenos.borrar', ['diseno' => '00000000-0000-0000-0000-000000000000']) }}" data-max-bytes="{{ \App\Dominio\Disenos\AnalizadorPng::maxMbEfectivo() * 1024 * 1024 }}" data-prenda="{{ $prenda->id }}" data-anadir="{{ \App\Http\Controllers\CarritoController::url('anadir') }}" data-precio="{{ route('tienda.precio', ['familia' => Escaparate::slug($familia, $idioma), 'prenda' => Escaparate::slug($prenda, $idioma)]) }}" data-colores='@json($colores)' data-textos='@json($textosVisor)' data-calidad='@json($calidadVisor)'>
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
                    <div class="cargando" id="cargando" role="status" hidden>
                        <span class="cabezal" aria-hidden="true"></span>
                        <p>{{ __('tienda.cargando_diseno') }}<span class="puntos" aria-hidden="true"><i></i><i></i><i></i></span></p>
                    </div>
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
                    <div class="estudio-panel" id="estudio-panel">
                        @include('tienda._tu-marca')
                    </div>
                    {{-- Móvil: el precio y el siguiente paso, siempre a mano (D142). --}}
                    <div class="estudio-pie" id="estudio-pie" hidden>
                        <p class="pie-precio"><strong id="pie-importe"></strong> <small id="pie-detalle"></small></p>
                        <button type="button" class="boton-siguiente" id="pie-siguiente"></button>
                    </div>
                @endif
                </div>


            </section>

            @unless ($conTuMarca)
                <section class="info" aria-label="{{ __('tienda.detalles') }}">
                    @include('tienda._info-prenda', ['enEstudio' => false])
                </section>
            @endunless
        </div>

    </div>
    {{-- D213: tras «Añadir a mi pedido», el siguiente paso natural: otra prenda con el mismo diseño ya puesto. --}}
    <div class="anadido" id="anadido" role="dialog" aria-modal="true" aria-labelledby="anadido-titulo" hidden>
        <div class="anadido-hoja">
            <p class="anadido-ok" aria-hidden="true">✓</p>
            <h2 id="anadido-titulo">{{ __('tienda.anadido_titulo') }}</h2>
            <p class="anadido-resumen" id="anadido-resumen"></p>
            <div id="anadido-otras-bloque">
                <h3>{{ __('tienda.anadido_otra') }}</h3>
                <p class="claro">{{ __('tienda.anadido_otra_ayuda') }}</p>
                <ul class="anadido-otras" id="anadido-otras"></ul>
            </div>
            <a class="boton principal anadido-pedido" id="anadido-pedido" href="{{ \App\Http\Controllers\CarritoController::url('ver') }}">{{ __('tienda.anadido_ver_pedido') }} <span class="flecha" aria-hidden="true">→</span></a>
        </div>
    </div>
</x-tienda>
