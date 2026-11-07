{{-- «Tu marca, aquí»: vista previa del diseño del cliente sobre la prenda (public/js/visor.js). --}}
<section class="tu-marca" id="tu-marca" aria-labelledby="tu-marca-titulo">
    <div>
        <p class="etiqueta">{{ __('tienda.tu_marca_etiqueta') }}</p>
        <h2 id="tu-marca-titulo">{{ __('tienda.tu_marca_titulo') }}</h2>
        <p class="claro" style="margin-top: 8px;">{{ __('tienda.tu_marca_texto') }}</p>
    </div>

    <div class="pestanas" role="tablist" aria-label="{{ __('tienda.tu_marca_etiqueta') }}">
        <button type="button" role="tab" id="pestana-logo" aria-selected="true" aria-controls="panel-logo" data-modo="logo">{{ __('tienda.pestana_logo') }}</button>
        <button type="button" role="tab" id="pestana-texto" aria-selected="false" aria-controls="panel-texto" data-modo="texto" tabindex="-1">{{ __('tienda.pestana_texto') }}</button>
    </div>

    <div id="panel-logo" role="tabpanel" aria-labelledby="pestana-logo">
        <label class="soltar" id="soltar" for="archivo-diseno">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 16V4m0 0-4 4m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
            <strong>{{ __('tienda.soltar') }}</strong>
            <span class="claro">{{ __('tienda.soltar_formatos') }}</span>
            <span class="nombre-logo" id="nombre-logo"></span>
        </label>
        <input type="file" id="archivo-diseno" class="oculto" accept="image/png,image/jpeg,image/webp,image/svg+xml">
    </div>

    <div id="panel-texto" role="tabpanel" aria-labelledby="pestana-texto" hidden>
        <div class="grupo">
            <label for="texto-marca" class="oculto">{{ __('tienda.texto_marca') }}</label>
            <input type="text" id="texto-marca" class="campo-marca" maxlength="24" autocomplete="off" placeholder="{{ __('tienda.texto_marca') }}">
            <span>{{ __('tienda.tipografia') }}</span>
            <div class="opciones" id="fuentes" role="radiogroup" aria-label="{{ __('tienda.tipografia') }}">
                @foreach ([
                    'font-family: \'Segoe UI\', Arial, sans-serif; font-weight: 700;',
                    'font-family: \'Iowan Old Style\', Georgia, serif; font-style: italic;',
                    'font-family: Impact, \'Arial Black\', sans-serif;',
                    'font-family: \'Segoe Script\', \'Brush Script MT\', cursive;',
                ] as $i => $estilo)
                    <button type="button" class="boton-pildora borde fuente-muestra" role="radio" aria-checked="{{ $i === 0 ? 'true' : 'false' }}" aria-label="{{ __('tienda.fuente_'.$i) }}" data-valor="{{ $i }}" style="{{ $estilo }}">Aa</button>
                @endforeach
            </div>
            <span>{{ __('tienda.tinta') }}</span>
            <div class="opciones" id="tintas" role="radiogroup" aria-label="{{ __('tienda.tinta') }}">
                <button type="button" class="tinta-muestra" role="radio" aria-checked="true" data-valor="auto" aria-label="{{ __('tienda.tinta_auto') }}">Auto</button>
                @foreach (['negro' => '#16161A', 'blanco' => '#FFFFFF', 'rojo' => '#C8102E', 'dorado' => '#C9A227'] as $clave => $hex)
                    <button type="button" class="tinta-muestra" role="radio" aria-checked="false" data-valor="{{ $clave }}" aria-label="{{ __('tienda.tinta_'.$clave) }}"><span style="background: {{ $hex }};"></span></button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grupo">
        <span>{{ __('tienda.donde') }}</span>
        <div class="opciones" id="zonas-diseno" role="radiogroup" aria-label="{{ __('tienda.donde') }}"></div>
    </div>

    <p class="tu-marca-error" id="tu-marca-error" role="alert" hidden></p>
    <p class="tu-marca-estado" id="tu-marca-estado" aria-live="polite"></p>

    <div class="acciones-marca" id="tu-marca-acciones" hidden>
        <button type="button" class="boton-claro" id="ver-coleccion" aria-expanded="false" aria-controls="coleccion">{{ __('tienda.ver_tu_coleccion') }}</button>
        <button type="button" class="boton-texto" id="quitar-diseno">{{ __('tienda.quitar_diseno') }}</button>
    </div>

    <p class="claro" style="font-size: 13px;">{{ __('tienda.privacidad') }}</p>
</section>
