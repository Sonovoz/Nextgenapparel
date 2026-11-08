{{-- «Tu marca, aquí»: vista previa del diseño del cliente sobre la prenda (public/js/visor.js). --}}
<section class="tu-marca" id="tu-marca" aria-labelledby="tu-marca-titulo">
    <div>
        <p class="etiqueta">{{ __('tienda.tu_marca_etiqueta') }}</p>
        <h2 id="tu-marca-titulo">{{ __('tienda.tu_marca_titulo') }}</h2>
        <p class="claro" style="margin-top: 8px;">{{ __('tienda.tu_marca_texto') }}</p>
    </div>

    {{-- Energía de la calidad, siempre a la vista mientras se diseña. --}}
    <div class="calidad" id="calidad" role="status" aria-live="polite" data-nivel="">
        <div class="calidad-cabecera"><strong id="calidad-etiqueta">{{ __('tienda.calidad_sin_diseno') }}</strong><span class="claro" id="calidad-ppp"></span></div>
        <div class="calidad-barra" aria-hidden="true"><span id="calidad-relleno"></span></div>
        <p id="calidad-mensaje"></p>
        <p class="claro" id="calidad-consejo"></p>
        <p id="calidad-fondo"></p>
    </div>

    <div class="herramientas" role="tablist" aria-label="{{ __('tienda.estudio') }}">
        <button type="button" role="tab" class="herramienta" id="h-diseno" aria-selected="true" aria-controls="hp-diseno" data-herramienta="diseno">{{ __('tienda.herramienta_diseno') }}</button>
        <button type="button" role="tab" class="herramienta" id="h-zona" aria-selected="false" aria-controls="hp-zona" data-herramienta="zona" tabindex="-1">{{ __('tienda.herramienta_zona') }}</button>
        <button type="button" role="tab" class="herramienta" id="h-ajustar" aria-selected="false" aria-controls="hp-ajustar" data-herramienta="ajustar" tabindex="-1">{{ __('tienda.herramienta_ajustar') }}</button>
        <button type="button" role="tab" class="herramienta" id="h-prenda" aria-selected="false" aria-controls="hp-prenda" data-herramienta="prenda" tabindex="-1">{{ __('tienda.herramienta_prenda') }}</button>
    </div>

    <div id="hp-prenda" role="tabpanel" aria-labelledby="h-prenda" class="herramienta-panel info-prenda" hidden>
        @include('tienda._info-prenda', ['enEstudio' => true])
    </div>

    <div id="hp-diseno" role="tabpanel" aria-labelledby="h-diseno" class="herramienta-panel">
    <div class="capas" id="capas"></div>

    <div class="pestanas" role="tablist" aria-label="{{ __('tienda.tu_marca_etiqueta') }}">
        <button type="button" role="tab" id="pestana-logo" aria-selected="true" aria-controls="panel-logo" data-modo="logo">{{ __('tienda.pestana_logo') }}</button>
        <button type="button" role="tab" id="pestana-texto" aria-selected="false" aria-controls="panel-texto" data-modo="texto" tabindex="-1">{{ __('tienda.pestana_texto') }}</button>
    </div>

    <div id="panel-logo" role="tabpanel" aria-labelledby="pestana-logo">
        <label class="soltar" id="soltar" for="archivo-diseno">
            <span class="icono-subir"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 16V4m0 0-5 5m5-5 5 5M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg></span>
            <strong class="boton-soltar">{{ __('tienda.soltar_boton') }}</strong>
            <span class="claro">{{ __('tienda.soltar_arrastra') }}</span>
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

    </div>

    <div id="hp-zona" role="tabpanel" aria-labelledby="h-zona" class="herramienta-panel" hidden>
        <p class="aviso-zona" id="aviso-zona" aria-live="polite"></p>
        <div class="grupo">
            <span>{{ __('tienda.donde') }}</span>
            <div class="opciones" id="zonas-diseno" role="radiogroup" aria-label="{{ __('tienda.donde') }}"></div>
        </div>
    </div>

    <div id="hp-ajustar" role="tabpanel" aria-labelledby="h-ajustar" class="herramienta-panel" hidden>
    <p class="claro" id="ajustador-vacio">{{ __('tienda.ajustar_vacio') }}</p>
    <div class="ajustador" id="ajustador" hidden>
        <h3>{{ __('tienda.ajusta') }}</h3>
        <div class="fila-tamano">
            <label for="tamano-diseno" class="oculto">{{ __('tienda.tamano') }}</label>
            <input type="range" id="tamano-diseno" min="20" max="100" step="1" value="100">
            <output id="medida-diseno" for="tamano-diseno"></output>
        </div>
        <p class="claro" style="font-size: 13px;">{{ __('tienda.arrastra') }}</p>
        <div class="opciones">
            <button type="button" class="boton-pildora borde" id="centrar-diseno">{{ __('tienda.centrar') }}</button>
            <button type="button" class="boton-pildora borde" id="arriba-diseno">{{ __('tienda.subir') }}</button>
        </div>
    </div>
    </div>

    <p class="tu-marca-error" id="tu-marca-error" role="alert" hidden></p>
    <p class="tu-marca-estado" id="tu-marca-estado" aria-live="polite"></p>

    <div class="acciones-marca" id="tu-marca-acciones" hidden>
        <button type="button" class="accion-borrar" id="quitar-diseno"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg><span id="quitar-texto">{{ __('tienda.quitar_diseno') }}</span></button>
        <button type="button" class="accion-reiniciar" id="empezar-de-nuevo"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 4v6h6M20 20v-6h-6"/><path d="M20 10A8 8 0 0 0 6.3 6.3L4 10m16 4-2.3 3.7A8 8 0 0 1 4 14"/></svg><span id="empezar-texto">{{ __('tienda.empezar_de_nuevo') }}</span></button>
    </div>

    <p class="claro" style="font-size: 13px;">{{ __('tienda.privacidad') }}</p>
</section>
