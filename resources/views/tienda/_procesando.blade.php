{{-- Capa «Procesando» de toda la tienda: oscurece la pantalla mientras algo tarda (subir un diseño, añadir al
     pedido, actualizar el carrito, ir al pago…), para que el cliente vea que estamos trabajando.
     Sale sola al enviar cualquier formulario POST (data-procesando="Texto" para cambiar el texto;
     data-sin-procesando para no mostrarla) y desde JavaScript con Procesando.mostrar(texto, detalle) / ocultar(). --}}
<div class="procesando" id="procesando" role="status" aria-live="assertive" hidden>
    <div class="procesando-caja">
        <span class="procesando-orbita" aria-hidden="true"><i></i></span>
        <p class="procesando-titulo"><span id="procesando-texto">{{ __('tienda.procesando') }}</span><span class="procesando-puntos" aria-hidden="true"><i></i><i></i><i></i></span></p>
        <p class="procesando-detalle" id="procesando-detalle">{{ __('tienda.procesando_detalle') }}</p>
    </div>
</div>
{{-- Aviso de error claro (D214): un problema que impide seguir (un archivo que no vale, un fallo de red) no
     puede quedar en una línea pequeña. Oscurece la pantalla y lo dice en el centro, con qué hacer.
     Desde JavaScript: AvisoTienda.mostrar(mensaje, {titulo, boton, alPulsar}). --}}
<div class="aviso-tienda" id="aviso-tienda" role="alertdialog" aria-modal="true" aria-labelledby="aviso-tienda-titulo" aria-describedby="aviso-tienda-texto" hidden>
    <div class="aviso-tienda-caja">
        <span class="aviso-tienda-icono" aria-hidden="true">!</span>
        <h2 id="aviso-tienda-titulo">{{ __('tienda.aviso_titulo') }}</h2>
        <p id="aviso-tienda-texto"></p>
        <div class="aviso-tienda-botones">
            <button type="button" class="boton principal" id="aviso-tienda-accion" hidden></button>
            <button type="button" class="boton" id="aviso-tienda-cerrar">{{ __('tienda.aviso_cerrar') }}</button>
        </div>
    </div>
</div>
<script>
    (function () {
        var aviso = document.getElementById('aviso-tienda');
        if (!aviso) return;
        var titulo = document.getElementById('aviso-tienda-titulo'), textoAviso = document.getElementById('aviso-tienda-texto');
        var accion = document.getElementById('aviso-tienda-accion'), cerrar = document.getElementById('aviso-tienda-cerrar');
        var tituloBase = titulo.textContent, alPulsar = null, antes = null;
        function ocultar() {
            aviso.classList.remove('visible');
            setTimeout(function () { aviso.hidden = true; }, 250);
            if (antes && antes.focus) antes.focus();
        }
        window.AvisoTienda = {
            mostrar: function (mensaje, opciones) {
                opciones = opciones || {};
                if (window.Procesando) window.Procesando.ocultar();
                antes = document.activeElement;
                titulo.textContent = opciones.titulo || tituloBase;
                textoAviso.textContent = mensaje;
                alPulsar = opciones.alPulsar || null;
                accion.hidden = !opciones.boton;
                accion.textContent = opciones.boton || '';
                aviso.hidden = false;
                void aviso.offsetWidth;
                aviso.classList.add('visible');
                (opciones.boton ? accion : cerrar).focus();
            },
            ocultar: ocultar
        };
        accion.addEventListener('click', function () { var f = alPulsar; ocultar(); if (f) f(); });
        cerrar.addEventListener('click', ocultar);
        aviso.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { e.preventDefault(); ocultar(); }
            if (e.key === 'Tab') { // el foco no sale del aviso
                var botones = [accion, cerrar].filter(function (b) { return !b.hidden; });
                var i = botones.indexOf(document.activeElement);
                e.preventDefault();
                botones[(i + (e.shiftKey ? botones.length - 1 : 1)) % botones.length].focus();
            }
        });
    })();
</script>
<script>
    (function () {
        var capa = document.getElementById('procesando');
        if (!capa) return;
        var texto = document.getElementById('procesando-texto'), detalle = document.getElementById('procesando-detalle');
        var base = texto.textContent, baseDetalle = detalle.textContent, inicio = 0, salida = null;
        var MINIMO = 700; // que no parpadee: si se ve, se ve al menos esto
        window.Procesando = {
            mostrar: function (t, d) {
                clearTimeout(salida);
                texto.textContent = t || base;
                detalle.textContent = d || baseDetalle;
                if (capa.hidden) { capa.hidden = false; inicio = Date.now(); void capa.offsetWidth; }
                capa.classList.add('visible');
                document.body.setAttribute('aria-busy', 'true');
            },
            ocultar: function () {
                var falta = Math.max(0, MINIMO - (Date.now() - inicio));
                clearTimeout(salida);
                salida = setTimeout(function () {
                    capa.classList.remove('visible');
                    document.body.removeAttribute('aria-busy');
                    salida = setTimeout(function () { capa.hidden = true; }, 320);
                }, falta);
            }
        };
        // Cualquier formulario que se envía al servidor (POST) y cambia de página.
        document.addEventListener('submit', function (e) {
            var f = e.target;
            if (e.defaultPrevented || !(f instanceof HTMLFormElement) || f.hasAttribute('data-sin-procesando')) return;
            if ((f.getAttribute('target') || '_self') !== '_self') return;
            if ((f.getAttribute('method') || 'get').toLowerCase() !== 'post' && !f.hasAttribute('data-procesando')) return;
            window.Procesando.mostrar(f.dataset.procesando, f.dataset.procesandoDetalle);
        });
        // Al volver atrás con el navegador, la página vuelve sin la capa.
        window.addEventListener('pageshow', function (e) { if (e.persisted) { capa.classList.remove('visible'); capa.hidden = true; document.body.removeAttribute('aria-busy'); } });
    })();
</script>
