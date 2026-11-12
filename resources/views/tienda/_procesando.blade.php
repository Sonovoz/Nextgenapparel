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
