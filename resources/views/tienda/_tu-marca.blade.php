{{-- «Tu marca, aquí»: vista previa del diseño del cliente sobre la prenda (public/js/visor.js). --}}
<section class="tu-marca" id="tu-marca" aria-labelledby="tu-marca-titulo">
    <div>
        <p class="etiqueta">{{ __('tienda.tu_marca_etiqueta') }}</p>
        <h2 id="tu-marca-titulo">{{ __('tienda.tu_marca_titulo') }}</h2>
        <p class="claro" style="margin-top: 8px;">{{ __('tienda.tu_marca_texto') }}</p>
    </div>

    {{-- Niveles del configurador (D142): 1 Diseño · 2 Tallas y colores · 3 Resumen. --}}
    <ol class="niveles" id="niveles" aria-label="{{ __('tienda.niveles') }}">
        <li><button type="button" data-nivel="diseno" aria-current="step"><b>1</b> {{ __('tienda.nivel_diseno') }}</button></li>
        <li><button type="button" data-nivel="cantidades" disabled><b>2</b> {{ __('tienda.nivel_cantidades') }}</button></li>
        <li><button type="button" data-nivel="resumen" disabled><b>3</b> {{ __('tienda.nivel_resumen') }}</button></li>
    </ol>

    <div class="nivel" id="nivel-diseno">
    <p class="claro nivel-ayuda">{{ __('tienda.ayuda_diseno') }}</p>
    {{-- Herramientas del paso 1: Diseño (subir y dónde va) y Ajustar (tamaño y posición, opcional). --}}
    <div class="herramientas" role="tablist" aria-label="{{ __('tienda.estudio') }}">
        <button type="button" role="tab" class="herramienta" id="h-diseno" aria-selected="true" aria-controls="hp-diseno" data-herramienta="diseno">{{ __('tienda.herramienta_diseno') }}</button>
        <button type="button" role="tab" class="herramienta" id="h-ajustar" aria-selected="false" aria-controls="hp-ajustar" data-herramienta="ajustar" tabindex="-1">{{ __('tienda.herramienta_ajustar') }}</button>
        <button type="button" role="tab" class="herramienta" id="h-prenda" aria-selected="false" aria-controls="hp-prenda" data-herramienta="prenda" tabindex="-1">{{ __('tienda.herramienta_prenda') }}</button>
    </div>

    <div id="hp-prenda" role="tabpanel" aria-labelledby="h-prenda" class="herramienta-panel info-prenda" hidden>
        @include('tienda._info-prenda', ['enEstudio' => true])
    </div>

    <div id="hp-diseno" role="tabpanel" aria-labelledby="h-diseno" class="herramienta-panel">

    {{-- «Escribir mi marca» oculto por ahora (D199): el código sigue para una futura actualización; para
         volver a ofrecerlo, quitar «hidden». Sin la pestaña, el diseño siempre es un logo. --}}
    <div class="pestanas" role="tablist" aria-label="{{ __('tienda.tu_marca_etiqueta') }}" hidden>
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
        <input type="file" id="archivo-diseno" class="oculto" accept="image/png,.png">

        {{-- Tus diseños subidos (fase 4): se rellena desde public/js/visor.js. --}}
        <div class="biblioteca" id="biblioteca" hidden>
            <p class="biblioteca-titulo">{{ __('disenos.biblioteca_titulo') }}</p>
            <ul class="biblioteca-lista" id="biblioteca-lista"></ul>
        </div>
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

    {{-- ¿Dónde lo ponemos? (Frontal, Pecho, Espalda…) en el mismo paso, con su dibujo y su precio (D198). --}}
    <div class="grupo donde">
        <span class="donde-titulo">{{ __('tienda.donde') }}</span>
        <p class="aviso-zona" id="aviso-zona" aria-live="polite"></p>
        <div class="opciones" id="zonas-diseno" role="radiogroup" aria-label="{{ __('tienda.donde') }}"></div>
    </div>
    </div>

    <div id="hp-ajustar" role="tabpanel" aria-labelledby="h-ajustar" class="herramienta-panel" hidden>
    <p class="claro" id="ajustador-vacio">{{ __('tienda.ajustar_vacio') }}</p>
    <div class="ajustador" id="ajustador" hidden>
        <h2 class="ajustador-titulo">{{ __('tienda.ajusta') }}</h2>
        {{-- D229: el tamaño real de impresión, grande y claro, con el máximo de la zona. --}}
        <div class="medida-impresion" id="medida-impresion">
            <span class="medida-etiqueta">{{ __('tienda.tamano_impresion') }}</span>
            <output id="medida-diseno" for="tamano-diseno" class="medida-valor"></output>
            <span class="medida-zona" id="medida-zona"></span>
        </div>
        <div class="fila-tamano">
            <label for="tamano-diseno" class="oculto">{{ __('tienda.tamano') }}</label>
            <span class="fila-tamano-extremo" aria-hidden="true">{{ __('tienda.mas_pequeno') }}</span>
            <input type="range" id="tamano-diseno" min="20" max="100" step="1" value="100">
            <span class="fila-tamano-extremo" aria-hidden="true">{{ __('tienda.mas_grande') }}</span>
        </div>
        <p class="claro" style="font-size: 13px;">{{ __('tienda.arrastra') }}</p>
        <div class="opciones">
            <button type="button" class="boton-pildora borde" id="centrar-diseno">{{ __('tienda.centrar') }}</button>
            <button type="button" class="boton-pildora borde" id="arriba-diseno">{{ __('tienda.subir') }}</button>
        </div>
    </div>
    </div>

    {{-- Energía de la calidad, debajo de la herramienta y a la vista mientras se diseña. --}}
    <div class="calidad" id="calidad" role="status" aria-live="polite" data-nivel="">
        <div class="calidad-cabecera"><strong id="calidad-etiqueta">{{ __('tienda.calidad_sin_diseno') }}</strong><span class="claro" id="calidad-ppp"></span></div>
        <div class="calidad-barra" aria-hidden="true"><span id="calidad-relleno"></span></div>
        <p id="calidad-mensaje"></p>
        {{-- D237: ficha de impresión: objetivo de la zona en cm y px, y lo que dice el archivo. --}}
        <p class="calidad-objetivo" id="calidad-objetivo"></p>
        <p class="claro" id="calidad-archivo"></p>
        <p class="claro" id="calidad-consejo"></p>
        {{-- D229: si se va a ver pixelado, un botón lo deja al mayor tamaño que se imprime nítido. --}}
        <button type="button" class="boton-pildora calidad-ajustar" id="calidad-ajustar" hidden></button>
        <p id="calidad-fondo"></p>
    </div>

    {{-- Si la calidad no llega: instrucciones para mejorarlo con su propia IA y volver a subirlo (D143). --}}
    <div class="mejora-ia" id="mejora-ia" hidden>
        <button type="button" class="boton-ia" id="abrir-ia" aria-expanded="false" aria-controls="panel-ia">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8L12 3zM19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15z"/></svg>
            {{ __('tienda.ia_boton') }}
        </button>
        <div class="panel-ia" id="panel-ia" hidden>
            <ol class="pasos-ia">
                <li>{{ __('tienda.ia_paso_1') }}</li>
                <li>{{ __('tienda.ia_paso_2') }}</li>
                <li>{{ __('tienda.ia_paso_3') }}</li>
            </ol>
            <label for="texto-ia" class="oculto">{{ __('tienda.ia_boton') }}</label>
            <textarea id="texto-ia" class="texto-ia" rows="9" readonly></textarea>
            <div class="acciones-ia">
                <button type="button" class="boton-siguiente" id="copiar-ia">{{ __('tienda.ia_copiar') }}</button>
                <label for="archivo-diseno" class="boton-pildora borde subir-ia">{{ __('tienda.ia_subir') }}</label>
            </div>
            <p class="claro aviso-ia">{{ __('tienda.ia_aviso') }}</p>
        </div>
    </div>

    <p class="tu-marca-error" id="tu-marca-error" role="alert" hidden></p>
    <p class="tu-marca-estado" id="tu-marca-estado" aria-live="polite"></p>

    <div class="acciones-marca" id="tu-marca-acciones" hidden>
        <button type="button" class="accion-borrar" id="quitar-diseno"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg><span id="quitar-texto">{{ __('tienda.quitar_diseno') }}</span></button>
        <button type="button" class="accion-reiniciar" id="empezar-de-nuevo"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 4v6h6M20 20v-6h-6"/><path d="M20 10A8 8 0 0 0 6.3 6.3L4 10m16 4-2.3 3.7A8 8 0 0 1 4 14"/></svg><span id="empezar-texto">{{ __('tienda.empezar_de_nuevo') }}</span></button>
    </div>

    <p class="claro" style="font-size: 13px;">{{ __('tienda.privacidad', ['horas' => (int) \App\Models\Ajuste::obtener('disenos.horas_sin_venta')]) }}</p>
    {{-- Tarjeta de decisión: tus diseños con lo que suman y «¿otro diseño o seguimos?»; en el escritorio, fija abajo. --}}
    <div class="capas" id="capas" aria-live="polite"></div>
    {{-- Abajo, fija y fina: qué llevas, el error si lo hay y «Siguiente: tallas y colores». --}}
    <div class="capas-pie" id="capas-pie" aria-live="polite" hidden></div>

    </div>

    <div class="nivel" id="nivel-cantidades" hidden>
        <div>
            <h2 class="nivel-titulo">{{ __('tienda.nivel_cantidades') }}</h2>
            <p class="claro">{{ __('tienda.cantidades_texto') }}</p>
            {{-- Avisos del servidor sobre los logos subidos (perfil de color, fondo). --}}
            <div class="avisos-subida" id="avisos-subida" role="status" hidden></div>
        </div>
        <div class="lineas-color" id="lineas-color"></div>
        <div class="grupo" id="anadir-color">
            <span>{{ __('tienda.anadir_color') }}</span>
            <div class="opciones" id="colores-libres"></div>
        </div>
        <div class="cuenta" id="cuenta" aria-live="polite"></div>
        <div class="nivel-acciones">
            <button type="button" class="boton-texto" data-ir="diseno">← {{ __('tienda.volver_diseno') }}</button>
            <button type="button" class="boton-siguiente" id="ir-resumen" data-ir="resumen" disabled>{{ __('tienda.siguiente_resumen') }} <span aria-hidden="true">→</span></button>
        </div>
    </div>

    <div class="nivel" id="nivel-resumen" hidden>
        <h2 class="nivel-titulo">{{ __('tienda.resumen_titulo') }}</h2>
        <div class="resumen" id="resumen"></div>
        <p class="claro">{{ __('tienda.envio_al_cerrar') }}</p>
        <button type="button" class="boton-siguiente" id="anadir-pedido" disabled>{{ __('tienda.anadir_pedido') }}</button>
        <p class="aviso-zona" id="anadir-error" role="alert"></p>
        <button type="button" class="boton-texto" data-ir="cantidades">← {{ __('tienda.cambiar_cantidades') }}</button>
    </div>
</section>
