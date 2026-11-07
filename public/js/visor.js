/*
 * Visor de prenda de la tienda (sin librerías).
 *
 * - Color, cara (con giro) y zonas de impresión sobre la maqueta.
 * - «Tu marca, aquí»: el cliente suelta su logo o escribe su marca y lo ve
 *   impreso en la zona elegida. El diseño se funde con la tela: toma sus
 *   sombras y se deforma ligeramente con sus pliegues, usando la propia foto
 *   como mapa. Todo ocurre en el navegador: no se sube ni se guarda nada.
 * - «Tu colección»: el diseño en todos los colores de la prenda.
 * - Lupa al pasar el ratón y deslizar para girar en el móvil.
 *
 * Es solo una vista previa: no calcula precios ni valida calidad (eso lo
 * hacen el motor de precios y el análisis del diseño en el servidor).
 */
(function () {
    'use strict';

    var visor = document.getElementById('visor');
    if (!visor) return;

    var colores = JSON.parse(visor.dataset.colores || '[]');
    var textos = JSON.parse(visor.dataset.textos || '{}');
    var calidad = JSON.parse(visor.dataset.calidad || '{}');
    var lienzo = document.getElementById('lienzo');
    var sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var conRaton = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    var FUENTES = [
        { css: '700 {t}px "Segoe UI", "Helvetica Neue", Arial, sans-serif', mayusculas: true, espacio: 0.08 },
        { css: 'italic 400 {t}px "Iowan Old Style", "Palatino Linotype", Georgia, serif', mayusculas: false, espacio: 0 },
        { css: '400 {t}px Impact, Haettenschweiler, "Arial Narrow Bold", "Arial Black", sans-serif', mayusculas: true, espacio: 0.02 },
        { css: '400 {t}px "Segoe Script", "Brush Script MT", "Snell Roundhand", cursive', mayusculas: false, espacio: 0 }
    ];
    var TINTAS = { negro: '#16161A', blanco: '#FFFFFF', rojo: '#C8102E', dorado: '#C9A227' };
    var LADO_MAXIMO = 1600; // px: los logos enormes se reducen para que la vista previa sea ágil
    var PESO_MAXIMO = 20 * 1024 * 1024;
    var RESOLUCION = 2;

    var estado = {
        color: 0,
        cara: 'delantera',
        zonas: false,
        modo: 'logo',          // 'logo' | 'texto'
        logo: null,            // canvas con el logo del cliente
        texto: '',
        fuente: 0,
        tinta: 'auto',
        zonaId: null,
        zonaElegida: false,    // el cliente eligió zona: no se cambia sola
        coleccion: false,
        ajustes: {},           // por zona: { escala (0,2–1), cx, cy (0–1 del hueco libre) }
        logoPx: null,          // píxeles reales del logo (sin márgenes transparentes)
        logoTipo: null,        // 'transparente' | 'fondo' | 'fondo_blanco' | 'vectorial'
        version: 0             // cambia con cada diseño nuevo (invalida la caché)
    };

    var cacheFotos = {};
    var cacheCapas = {};

    /* ---------- Utilidades ---------- */

    function el(etiqueta, clase, texto) {
        var nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    }

    function luminancia(hex) {
        var m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex || '');
        if (!m) return 1;
        return (0.299 * parseInt(m[1], 16) + 0.587 * parseInt(m[2], 16) + 0.114 * parseInt(m[3], 16)) / 255;
    }

    function esOscuro(hex) { return luminancia(hex) < 0.45; }

    function texto(clave, valores) {
        var t = textos[clave] || '';
        Object.keys(valores || {}).forEach(function (k) { t = t.split(':' + k).join(valores[k]); });
        return t;
    }

    function cargarFoto(url) {
        if (!cacheFotos[url]) {
            cacheFotos[url] = new Promise(function (resolver, rechazar) {
                var img = new Image();
                img.crossOrigin = 'anonymous'; // preparado para servir las fotos desde un almacenamiento externo
                img.onload = function () { resolver(img); };
                img.onerror = rechazar;
                img.src = url;
            });
        }
        return cacheFotos[url];
    }

    /** Todas las zonas de la prenda (sin repetir), primero las delanteras. */
    function todasLasZonas() {
        var vistas = {};
        var lista = [];
        ['delantera', 'trasera'].forEach(function (cara) {
            colores.forEach(function (color) {
                var datos = color.caras[cara];
                (datos ? datos.zonas : []).forEach(function (z) {
                    if (z.id && !vistas[z.id]) {
                        vistas[z.id] = true;
                        lista.push({ id: z.id, nombre: z.nombre, cara: cara, area: z.ancho * z.alto });
                    }
                });
            });
        });
        return lista;
    }

    function zonaEn(color, cara) {
        var datos = color.caras[cara];
        if (!datos || estado.zonaId === null) return null;
        for (var i = 0; i < datos.zonas.length; i++) {
            if (datos.zonas[i].id === estado.zonaId) return datos.zonas[i];
        }
        return null;
    }

    function hayDiseno() {
        return estado.modo === 'logo' ? !!estado.logo : estado.texto.trim() !== '';
    }

    function tintaPara(color) {
        if (estado.tinta !== 'auto') return TINTAS[estado.tinta];
        return esOscuro(color.hex) ? TINTAS.blanco : TINTAS.negro;
    }

    /** Diseño del cliente como canvas (el texto depende de la tinta y, en automático, del color). */
    function disenoPara(color) {
        if (estado.modo === 'logo') return estado.logo;

        var fuente = FUENTES[estado.fuente];
        var cadena = fuente.mayusculas ? estado.texto.trim().toUpperCase() : estado.texto.trim();
        var tamano = 200;
        var c = document.createElement('canvas');
        var ctx = c.getContext('2d');
        ctx.font = fuente.css.replace('{t}', tamano);
        if ('letterSpacing' in ctx) ctx.letterSpacing = (fuente.espacio * tamano) + 'px';
        var ancho = Math.ceil(ctx.measureText(cadena).width) + tamano * 0.4;
        c.width = Math.max(ancho, 10);
        c.height = Math.ceil(tamano * 1.45);
        ctx = c.getContext('2d');
        ctx.font = fuente.css.replace('{t}', tamano);
        if ('letterSpacing' in ctx) ctx.letterSpacing = (fuente.espacio * tamano) + 'px';
        ctx.fillStyle = tintaPara(color);
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(cadena, c.width / 2, c.height / 2);
        return recortarMargenes(c);
    }

    function ajusteDe(zonaId) {
        if (!estado.ajustes[zonaId]) estado.ajustes[zonaId] = { escala: 1, cx: 0.5, cy: 0 };
        return estado.ajustes[zonaId];
    }

    /** Posición y tamaño del diseño dentro de la zona, en fracciones de la zona (0–1). */
    function geometria(diseno, zona) {
        var aj = ajusteDe(zona.id);
        // Proporción de la zona en la foto (ancho/alto en % de una foto 5:6).
        var proporcionZona = (zona.ancho * 5) / (zona.alto * 6);
        var proporcionDiseno = diseno.width / diseno.height;
        var w, h;
        if (proporcionDiseno > proporcionZona) { w = 1; h = proporcionZona / proporcionDiseno; }
        else { h = 1; w = proporcionDiseno / proporcionZona; }
        w *= aj.escala; h *= aj.escala;
        return { x: (1 - w) * aj.cx, y: (1 - h) * aj.cy, w: w, h: h };
    }

    /**
     * Capa del diseño «impreso» sobre la zona de una foto: un canvas del tamaño
     * de la zona (en píxeles de la foto) que toma las luces, sombras y pliegues
     * de la tela. Si el navegador no deja leer la foto, se devuelve sin fundir.
     */
    function capaImpresa(color, cara, zona) {
        var datos = color.caras[cara];
        var diseno = disenoPara(color);
        var aj = ajusteDe(zona.id);
        var clave = [estado.version, estado.modo, estado.fuente, tintaPara(color), estado.texto, datos.url, zona.id, aj.escala, aj.cx, aj.cy].join('|');
        if (cacheCapas[clave]) return cacheCapas[clave];

        cacheCapas[clave] = cargarFoto(datos.url).then(function (foto) {
            var W = foto.naturalWidth, H = foto.naturalHeight;
            var zx = Math.round(zona.x / 100 * W), zy = Math.round(zona.y / 100 * H);
            var zwFoto = Math.max(1, Math.round(zona.ancho / 100 * W)), zhFoto = Math.max(1, Math.round(zona.alto / 100 * H));
            // Al doble de la foto: el diseño se mantiene nítido en la lupa.
            var zw = zwFoto * RESOLUCION, zh = zhFoto * RESOLUCION;

            // 1. El diseño, encajado en la zona: centrado; arriba si la zona es alta.
            var d = document.createElement('canvas');
            d.width = zw; d.height = zh;
            var dctx = d.getContext('2d');
            // Por defecto, centrado en horizontal y arriba del todo: las impresiones
            // se colocan midiendo desde el cuello (D99). El ajustador lo cambia.
            var g = geometria(diseno, zona);
            dctx.drawImage(diseno, g.x * zw, g.y * zh, g.w * zw, g.h * zh);

            // 2. La tela de esa zona.
            var t = document.createElement('canvas');
            t.width = zw; t.height = zh;
            var tctx = t.getContext('2d');
            tctx.imageSmoothingQuality = 'high';
            tctx.drawImage(foto, zx, zy, zwFoto, zhFoto, 0, 0, zw, zh);

            var tela, dis;
            try {
                tela = tctx.getImageData(0, 0, zw, zh).data;
                dis = dctx.getImageData(0, 0, zw, zh).data;
            } catch (e) {
                return d; // foto de otro origen sin permiso de lectura: diseño sin fundir
            }

            // Luminosidad media de la tela (solo donde hay prenda).
            var lum = new Float32Array(zw * zh);
            var suma = 0, n = 0;
            for (var p = 0, i = 0; p < lum.length; p++, i += 4) {
                lum[p] = 0.299 * tela[i] + 0.587 * tela[i + 1] + 0.114 * tela[i + 2];
                if (tela[i + 3] > 128) { suma += lum[p]; n++; }
            }
            if (n === 0) return d;
            var media = Math.max(suma / n, 12);

            // 3. Mapa de pliegues: la luz de la tela suavizada, para que el diseño
            //    siga los pliegues y no el grano (que en tejidos oscuros es ruido).
            var radio = Math.max(2, Math.round(zw * 0.012));
            var suave = desenfocar(lum, zw, zh, radio);

            // 4. Sombreado y deformación: cada píxel toma la luz de la tela y se
            //    desplaza un poco según sus pliegues; el grano se nota apenas.
            var salida = dctx.createImageData(zw, zh);
            var o = salida.data;
            var desplazamiento = zw * 0.01;
            for (var py = 0; py < zh; py++) {
                for (var px = 0; px < zw; px++) {
                    var q = py * zw + px;
                    var k = q * 4;
                    if (tela[k + 3] === 0) continue;
                    var pliegue = Math.min(Math.max(suave[q] / media, 0.6), 1.3) - 1;
                    var grano = Math.min(Math.max((lum[q] - suave[q]) / Math.max(media, 60), -0.08), 0.08);
                    var sx = Math.round(px + pliegue * desplazamiento);
                    var sy = Math.round(py + pliegue * desplazamiento * 0.6);
                    if (sx < 0 || sy < 0 || sx >= zw || sy >= zh) continue;
                    var s = (sy * zw + sx) * 4;
                    if (dis[s + 3] === 0) continue;
                    var luz = Math.min(Math.max(1 + pliegue * 0.75 + grano, 0.55), 1.25);
                    o[k] = Math.min(255, dis[s] * luz);
                    o[k + 1] = Math.min(255, dis[s + 1] * luz);
                    o[k + 2] = Math.min(255, dis[s + 2] * luz);
                    o[k + 3] = dis[s + 3] * (tela[k + 3] / 255) * 0.96; // tinta, no pegatina
                }
            }
            dctx.putImageData(salida, 0, 0);
            return d;
        });

        return cacheCapas[clave];
    }

    /** Desenfoque de caja (horizontal y vertical) con sumas acumuladas: rápido en cualquier tamaño. */
    function desenfocar(valores, ancho, alto, radio) {
        var tmp = new Float32Array(valores.length);
        var out = new Float32Array(valores.length);
        var x, y, a, b, acumulado;
        for (y = 0; y < alto; y++) {
            acumulado = 0;
            var fila = y * ancho;
            for (x = -radio; x <= radio; x++) acumulado += valores[fila + Math.min(Math.max(x, 0), ancho - 1)];
            for (x = 0; x < ancho; x++) {
                tmp[fila + x] = acumulado / (2 * radio + 1);
                a = Math.min(x + radio + 1, ancho - 1); b = Math.max(x - radio, 0);
                acumulado += valores[fila + a] - valores[fila + b];
            }
        }
        for (x = 0; x < ancho; x++) {
            acumulado = 0;
            for (y = -radio; y <= radio; y++) acumulado += tmp[Math.min(Math.max(y, 0), alto - 1) * ancho + x];
            for (y = 0; y < alto; y++) {
                out[y * ancho + x] = acumulado / (2 * radio + 1);
                a = Math.min(y + radio + 1, alto - 1); b = Math.max(y - radio, 0);
                acumulado += tmp[a * ancho + x] - tmp[b * ancho + x];
            }
        }
        return out;
    }

    /**
     * Quita los márgenes transparentes (o casi) del logo: lo que cuenta para
     * colocarlo es lo que se imprime, no el lienzo del archivo.
     */
    function recortarMargenes(c) {
        var datos;
        try { datos = c.getContext('2d').getImageData(0, 0, c.width, c.height).data; } catch (e) { return c; }
        var minX = c.width, minY = c.height, maxX = -1, maxY = -1;
        for (var y = 0; y < c.height; y++) {
            for (var x = 0; x < c.width; x++) {
                if (datos[(y * c.width + x) * 4 + 3] > 8) {
                    if (x < minX) minX = x;
                    if (x > maxX) maxX = x;
                    if (y < minY) minY = y;
                    if (y > maxY) maxY = y;
                }
            }
        }
        if (maxX < 0) return c; // totalmente transparente: se deja tal cual
        var w = maxX - minX + 1, h = maxY - minY + 1;
        if (w === c.width && h === c.height) return c;
        var r = document.createElement('canvas');
        r.width = w; r.height = h;
        r.getContext('2d').drawImage(c, minX, minY, w, h, 0, 0, w, h);
        return r;
    }

    /** ¿Tiene el logo fondo transparente, un fondo opaco o un fondo blanco? */
    function tipoDeFondo(c) {
        var d;
        try { d = c.getContext('2d').getImageData(0, 0, c.width, c.height).data; } catch (e) { return 'transparente'; }
        var transparentes = 0;
        for (var i = 3; i < d.length; i += 16) if (d[i] < 250) transparentes++;
        if (transparentes / (d.length / 16) > 0.002) return 'transparente';
        // Opaco: ¿el borde es blanco?
        var blancos = 0, total = 0;
        function mirar(x, y) { var k = (y * c.width + x) * 4; total++; if (d[k] > 235 && d[k + 1] > 235 && d[k + 2] > 235) blancos++; }
        for (var x = 0; x < c.width; x += 4) { mirar(x, 0); mirar(x, c.height - 1); }
        for (var y = 0; y < c.height; y += 4) { mirar(0, y); mirar(c.width - 1, y); }
        return blancos / total > 0.9 ? 'fondo_blanco' : 'fondo';
    }

    /* ---------- Escena: foto + zonas + diseño ---------- */

    function construirEscena(indiceColor, cara, opciones) {
        opciones = opciones || {};
        var color = colores[indiceColor];
        var datos = color && color.caras[cara];
        var escena = el('div', 'capa');

        if (!datos) {
            escena.appendChild(el('p', 'sin-foto', textos.sin_foto));
            return escena;
        }

        var img = el('img');
        img.src = datos.url; img.alt = opciones.decorativa ? '' : (datos.alt || color.nombre);
        img.width = 1000; img.height = 1200; img.draggable = false;
        escena.appendChild(img);

        if (opciones.marcas) {
            var tinta = esOscuro(color.hex) ? '#FFFFFF' : '#16161A';
            datos.zonas.forEach(function (z) {
                var marca = el('div', 'zona-marca' + (hayDiseno() ? ' con-diseno' : '') + (z.id === estado.zonaId && hayDiseno() ? ' elegida' : ''));
                marca.style.cssText = 'left:' + z.x + '%;top:' + z.y + '%;width:' + z.ancho + '%;height:' + z.alto + '%;color:' + tinta;
                marca.appendChild(el('span', '', z.nombre || ''));
                escena.appendChild(marca);
            });
        }

        var zona = hayDiseno() ? zonaEn(color, cara) : null;
        if (zona && opciones.movible) {
            var guia = el('div', 'zona-guia');
            guia.style.cssText = 'left:' + zona.x + '%;top:' + zona.y + '%;width:' + zona.ancho + '%;height:' + zona.alto + '%';
            escena.appendChild(guia);
        }
        if (zona) {
            capaImpresa(color, cara, zona).then(function (capa) {
                var c = el('canvas', 'diseno' + (opciones.estampar && !sinMovimiento ? ' estampar' : '') + (opciones.movible ? ' movible' : ''));
                if (opciones.movible) { c.dataset.zona = zona.id; c.dataset.color = indiceColor; }
                c.width = capa.width; c.height = capa.height;
                c.getContext('2d').drawImage(capa, 0, 0);
                c.setAttribute('aria-hidden', 'true');
                c.style.cssText = 'left:' + zona.x + '%;top:' + zona.y + '%;width:' + zona.ancho + '%;height:' + zona.alto + '%';
                escena.appendChild(c);
            });
        }

        return escena;
    }

    var estampar = false;

    function pintar(transicion) {
        var color = colores[estado.color];
        if (!color) return;

        var nueva = construirEscena(estado.color, estado.cara, { marcas: estado.zonas, estampar: estampar, movible: true });
        estampar = false;

        var cambiar = function () {
            if (transicion === 'fundido' && !sinMovimiento && lienzo.firstChild) {
                nueva.classList.add('entrando');
                lienzo.appendChild(nueva);
                var viejas = Array.prototype.slice.call(lienzo.children, 0, -1);
                requestAnimationFrame(function () { nueva.classList.remove('entrando'); });
                setTimeout(function () { viejas.forEach(function (v) { v.remove(); }); }, 360);
            } else {
                lienzo.replaceChildren(nueva);
            }
            lupaSucia = true;
        };

        if (transicion === 'giro' && !sinMovimiento) {
            lienzo.classList.add('girando');
            setTimeout(function () {
                cambiar();
                lienzo.classList.remove('girando');
                lienzo.classList.add('volviendo');
                void lienzo.offsetWidth;
                lienzo.classList.remove('volviendo');
            }, 190);
        } else {
            cambiar();
        }

        document.getElementById('nombre-color').textContent = color.nombre;
        document.querySelectorAll('.muestra').forEach(function (b) { b.setAttribute('aria-checked', String(Number(b.dataset.color) === estado.color)); });
        document.querySelectorAll('[data-cara]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.cara === estado.cara)); });
        document.getElementById('ver-zonas').setAttribute('aria-pressed', String(estado.zonas));
    }

    function cambiarCara(cara) {
        if (cara === estado.cara) return;
        estado.cara = cara;
        pintar('giro');
    }

    /* ---------- Controles básicos ---------- */

    document.querySelectorAll('.muestra').forEach(function (b) {
        b.addEventListener('click', function () { estado.color = Number(b.dataset.color); pintar('fundido'); avisarListo(); });
    });
    document.querySelectorAll('[data-cara]').forEach(function (b) {
        b.addEventListener('click', function () { cambiarCara(b.dataset.cara); });
    });
    document.getElementById('ver-zonas').addEventListener('click', function () { estado.zonas = !estado.zonas; pintar(); });

    // Deslizar para girar (móvil): horizontal y decidido; el desplazamiento vertical sigue funcionando.
    var inicio = null;
    lienzo.addEventListener('pointerdown', function (e) {
        if (e.pointerType !== 'mouse' && !e.target.classList.contains('movible')) inicio = { x: e.clientX, y: e.clientY };
    });
    lienzo.addEventListener('pointerup', function (e) {
        if (!inicio) return;
        var dx = e.clientX - inicio.x, dy = e.clientY - inicio.y;
        inicio = null;
        if (Math.abs(dx) > 50 && Math.abs(dy) < 40) cambiarCara(estado.cara === 'delantera' ? 'trasera' : 'delantera');
    });
    lienzo.addEventListener('pointercancel', function () { inicio = null; });

    /* ---------- Lupa (ratón) ---------- */

    var lupa = document.getElementById('lupa');
    var arrastre = null;
    var lupaSucia = true;
    var AUMENTO = 2.4;

    if (lupa && conRaton) {
        lienzo.addEventListener('pointermove', function (e) {
            if (e.pointerType !== 'mouse' || !lienzo.querySelector('img')) return;
            if (arrastre || e.target.classList.contains('movible')) { lupa.hidden = true; return; }
            var r = lienzo.getBoundingClientRect();
            var v = visor.getBoundingClientRect();
            if (lupaSucia) {
                var copia = construirEscena(estado.color, estado.cara, { decorativa: true });
                copia.style.width = r.width * AUMENTO + 'px';
                copia.style.height = r.height * AUMENTO + 'px';
                copia.style.inset = 'auto';
                lupa.replaceChildren(copia);
                lupaSucia = false;
            }
            var x = e.clientX - r.left, y = e.clientY - r.top;
            var radio = lupa.offsetWidth / 2 || 85;
            lupa.hidden = false;
            lupa.style.left = (e.clientX - v.left - radio) + 'px';
            lupa.style.top = (e.clientY - v.top - radio) + 'px';
            lupa.firstChild.style.left = (radio - x * AUMENTO) + 'px';
            lupa.firstChild.style.top = (radio - y * AUMENTO) + 'px';
        });
        lienzo.addEventListener('pointerleave', function () { lupa.hidden = true; });
    }

    /* ---------- Tu marca, aquí ---------- */

    var panel = document.getElementById('tu-marca');
    if (!panel) { pintar(); return; }

    var zonas = todasLasZonas();
    var estadoMarca = document.getElementById('tu-marca-estado');
    var errorMarca = document.getElementById('tu-marca-error');
    var acciones = document.getElementById('tu-marca-acciones');
    var botonColeccion = document.getElementById('ver-coleccion');
    var coleccion = document.getElementById('coleccion');

    // Zonas para elegir
    var grupoZonas = document.getElementById('zonas-diseno');
    zonas.forEach(function (z) {
        var b = el('button', 'boton-pildora borde', z.nombre);
        b.type = 'button';
        b.setAttribute('role', 'radio');
        b.setAttribute('aria-checked', 'false');
        b.dataset.zona = z.id;
        b.addEventListener('click', function () {
            apagarMarcas();
            estado.zonaId = z.id;
            estado.zonaElegida = true;
            marcarZona();
            if (z.cara !== estado.cara) { estado.cara = z.cara; estampar = true; pintar('giro'); }
            else { estampar = true; pintar(); }
            avisarListo();
            refrescarColeccion();
        });
        grupoZonas.appendChild(b);
    });

    /** Con un diseño puesto, las marcas de zona estorban: se apagan solas. */
    function apagarMarcas() { estado.zonas = false; }

    /** Muestra un momento la guía de la zona mientras se ajusta el diseño. */
    var esperaGuia = null;
    function mostrarGuia() {
        lienzo.classList.add('ajustando');
        clearTimeout(esperaGuia);
        esperaGuia = setTimeout(function () { if (!arrastre) lienzo.classList.remove('ajustando'); }, 1200);
    }

    function marcarZona() {
        grupoZonas.querySelectorAll('[data-zona]').forEach(function (b) {
            b.setAttribute('aria-checked', String(Number(b.dataset.zona) === estado.zonaId));
        });
    }

    /** Zona de partida: el pecho para un logo; la más grande de delante para un texto. */
    function zonaPorDefecto() {
        if (estado.zonaElegida || zonas.length === 0) return;
        var delanteras = zonas.filter(function (z) { return z.cara === 'delantera'; });
        var candidatas = delanteras.length ? delanteras : zonas;
        var elegida = candidatas.reduce(function (a, b) {
            return estado.modo === 'logo' ? (b.area < a.area ? b : a) : (b.area > a.area ? b : a);
        });
        estado.zonaId = elegida.id;
        marcarZona();
        return elegida;
    }

    function colocarDiseno() {
        apagarMarcas();
        var zona = zonaPorDefecto();
        var cara = zona ? zona.cara : null;
        if (!cara) {
            var actual = zonas.filter(function (z) { return z.id === estado.zonaId; })[0];
            cara = actual ? actual.cara : estado.cara;
        }
        estampar = true;
        if (cara !== estado.cara) { estado.cara = cara; pintar('giro'); } else { pintar(); }
        acciones.hidden = !hayDiseno();
        avisarListo();
        refrescarColeccion();
    }

    function avisarListo() {
        actualizarAjustador();
        if (!hayDiseno()) { estadoMarca.textContent = ''; return; }
        var color = colores[estado.color];
        estadoMarca.replaceChildren(
            el('strong', '', texto('listo', { prenda: textos.prenda, color: color.nombre })),
            document.createTextNode(' ' + textos.listo_2)
        );
    }

    // Pestañas: mi logo / escribir mi marca
    var pestanas = panel.querySelectorAll('[role="tab"]');
    pestanas.forEach(function (p) {
        p.addEventListener('click', function () {
            estado.modo = p.dataset.modo;
            pestanas.forEach(function (o) {
                var activa = o === p;
                o.setAttribute('aria-selected', String(activa));
                o.tabIndex = activa ? 0 : -1;
                document.getElementById(o.getAttribute('aria-controls')).hidden = !activa;
            });
            estado.version++;
            if (hayDiseno()) colocarDiseno(); else { acciones.hidden = true; avisarListo(); pintar(); refrescarColeccion(); }
        });
    });

    // Logo: elegir o soltar
    var entrada = document.getElementById('archivo-diseno');
    var soltar = document.getElementById('soltar');

    function mostrarError(mensaje) {
        errorMarca.textContent = mensaje || '';
        errorMarca.hidden = !mensaje;
    }

    /** @param original  datos del archivo original si llega reducido desde la portada */
    function leerLogo(archivo, original) {
        mostrarError('');
        if (!archivo) return;
        if (!/^image\/(png|jpeg|webp|svg\+xml|gif)$/.test(archivo.type)) { mostrarError(textos.error_archivo); return; }
        if (archivo.size > PESO_MAXIMO) { mostrarError(textos.error_grande); return; }

        var url = URL.createObjectURL(archivo);
        var img = new Image();
        img.onload = function () {
            var w = img.naturalWidth || 1000, h = img.naturalHeight || 1000;
            var escala = Math.min(1, LADO_MAXIMO / Math.max(w, h));
            var c = document.createElement('canvas');
            c.width = Math.max(1, Math.round(w * escala));
            c.height = Math.max(1, Math.round(h * escala));
            c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
            URL.revokeObjectURL(url);

            var esVectorial = archivo.type === 'image/svg+xml' || (original && original.tipo === 'image/svg+xml');
            estado.logoTipo = esVectorial ? 'vectorial' : tipoDeFondo(c);
            estado.logo = recortarMargenes(c);
            // Si llega reducido desde la portada, la calidad se mide con los píxeles del original.
            var factor = original && original.ancho ? original.ancho / w : 1;
            estado.logoPx = { w: Math.round(estado.logo.width / escala * factor), h: Math.round(estado.logo.height / escala * factor) };
            estado.ajustes = {};
            estado.version++;
            if (estado.modo !== 'logo') pestanas[0].click(); else colocarDiseno();
            soltar.classList.add('con-logo');
            document.getElementById('nombre-logo').textContent = original ? original.nombre : archivo.name;
        };
        img.onerror = function () { URL.revokeObjectURL(url); mostrarError(textos.error_archivo); };
        img.src = url;
    }

    entrada.addEventListener('change', function () { leerLogo(entrada.files[0]); entrada.value = ''; });

    [soltar, visor].forEach(function (destino) {
        destino.addEventListener('dragover', function (e) {
            if (!e.dataTransfer || Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') === -1) return;
            e.preventDefault();
            visor.classList.add('soltando');
            soltar.classList.add('soltando');
        });
        destino.addEventListener('dragleave', function (e) {
            if (destino.contains(e.relatedTarget)) return;
            visor.classList.remove('soltando');
            soltar.classList.remove('soltando');
        });
        destino.addEventListener('drop', function (e) {
            e.preventDefault();
            visor.classList.remove('soltando');
            soltar.classList.remove('soltando');
            leerLogo(e.dataTransfer.files[0]);
        });
    });

    // Texto de marca
    var campoTexto = document.getElementById('texto-marca');
    var espera = null;
    campoTexto.addEventListener('input', function () {
        estado.texto = campoTexto.value;
        clearTimeout(espera);
        espera = setTimeout(function () {
            estado.version++;
            if (hayDiseno()) colocarDiseno(); else { acciones.hidden = true; avisarListo(); pintar(); refrescarColeccion(); }
        }, 180);
    });

    function grupoRadio(id, alElegir) {
        var botones = document.querySelectorAll('#' + id + ' [role="radio"]');
        botones.forEach(function (b) {
            b.addEventListener('click', function () {
                botones.forEach(function (o) { o.setAttribute('aria-checked', String(o === b)); });
                alElegir(b.dataset.valor);
                estado.version++;
                if (hayDiseno()) { estampar = true; pintar(); refrescarColeccion(); }
            });
        });
    }
    grupoRadio('fuentes', function (v) { estado.fuente = Number(v); });
    grupoRadio('tintas', function (v) { estado.tinta = v; });

    // Quitar el diseño
    document.getElementById('quitar-diseno').addEventListener('click', function () {
        estado.logo = null;
        estado.logoPx = null;
        estado.logoTipo = null;
        estado.ajustes = {};
        estado.texto = '';
        campoTexto.value = '';
        estado.zonaElegida = false;
        estado.version++;
        soltar.classList.remove('con-logo');
        acciones.hidden = true;
        estado.coleccion = false;
        coleccion.hidden = true;
        botonColeccion.setAttribute('aria-expanded', 'false');
        botonColeccion.textContent = textos.ver_coleccion;
        avisarListo();
        pintar();
    });

    /* ---------- Ajustador y calidad ---------- */

    var ajustador = document.getElementById('ajustador');
    var tamano = document.getElementById('tamano-diseno');
    var bloqueCalidad = document.getElementById('calidad');

    function zonaActual() {
        var color = colores[estado.color];
        var z = zonaEn(color, estado.cara);
        if (z) return z;
        var caras = ['delantera', 'trasera'];
        for (var i = 0; i < caras.length; i++) { z = zonaEn(color, caras[i]); if (z) return z; }
        return null;
    }

    function cm(mm) {
        return (Math.round(mm) / 10).toFixed(1).replace('.', textos.decimal || ',').replace(/[.,]0$/, '');
    }

    /** Misma fórmula y umbrales que App\Dominio\Disenos\Calidad (ficha § 7). */
    function ppp(px, mm) { return Math.round(px * 25.4 / mm); }
    function nivel(valor) {
        var u = calidad.umbrales;
        if (valor >= u.correcto) return 'correcta';
        if (valor >= u.aceptable) return 'buena';
        if (valor >= u.minimo) return 'justa';
        return 'baja';
    }

    function actualizarAjustador() {
        var zona = hayDiseno() ? zonaActual() : null;
        ajustador.hidden = !zona;
        if (!zona) return;

        var color = colores[estado.color];
        var g = geometria(disenoPara(color), zona);
        var anchoMm = zona.ancho_mm * g.w, altoMm = zona.alto_mm * g.h;
        tamano.value = Math.round(ajusteDe(zona.id).escala * 100);
        document.getElementById('medida-diseno').textContent = texto('medida_impresion', { medida: cm(anchoMm) + ' × ' + cm(altoMm) + ' cm' });

        var t = calidad.textos;
        var etiqueta = document.getElementById('calidad-etiqueta');
        var detalle = document.getElementById('calidad-ppp');
        var mensaje = document.getElementById('calidad-mensaje');
        var consejo = document.getElementById('calidad-consejo');
        var fondo = document.getElementById('calidad-fondo');
        fondo.textContent = ''; consejo.textContent = ''; detalle.textContent = '';

        if (estado.modo === 'texto' || estado.logoTipo === 'vectorial') {
            bloqueCalidad.dataset.nivel = 'correcta';
            etiqueta.textContent = t.nivel_correcta;
            mensaje.textContent = estado.modo === 'texto' ? t.texto : t.vectorial;
            if (estado.logoTipo === 'vectorial' && estado.modo === 'logo') fondo.textContent = t.formato_pedido;
            return;
        }

        var valor = Math.min(ppp(estado.logoPx.w, anchoMm), ppp(estado.logoPx.h, altoMm));
        var n = nivel(valor);
        bloqueCalidad.dataset.nivel = n;
        etiqueta.textContent = t['nivel_' + n];
        detalle.textContent = t.ppp.replace(':ppp', valor);
        mensaje.textContent = t['mensaje_' + n];
        var maximoMm = Math.min(estado.logoPx.w * 25.4 / calidad.umbrales.correcto, zona.ancho_mm);
        if (n === 'correcta' || n === 'buena') {
            consejo.textContent = t.consejo.replace(':medida', cm(Math.min(estado.logoPx.w * 25.4 / calidad.umbrales.correcto, 99999)) + ' cm');
            if (maximoMm >= zona.ancho_mm && n === 'correcta') consejo.textContent = '';
        } else {
            consejo.textContent = t.consejo_mejorar.replace(':px', Math.ceil(anchoMm / 25.4 * calidad.umbrales.correcto));
        }
        if (estado.logoTipo === 'fondo') fondo.textContent = t.fondo;
        if (estado.logoTipo === 'fondo_blanco') fondo.textContent = t.fondo_blanco;
    }

    var esperaTamano = null;
    tamano.addEventListener('input', function () {
        var zona = zonaActual();
        if (!zona) return;
        ajusteDe(zona.id).escala = Number(tamano.value) / 100;
        mostrarGuia();
        actualizarAjustador();
        clearTimeout(esperaTamano);
        esperaTamano = setTimeout(function () { pintar(); refrescarColeccion(); }, 60);
    });

    function moverA(cambios) {
        var zona = zonaActual();
        if (!zona) return;
        var aj = ajusteDe(zona.id);
        Object.keys(cambios).forEach(function (k) { aj[k] = cambios[k]; });
        mostrarGuia();
        pintar(); actualizarAjustador(); refrescarColeccion();
    }
    document.getElementById('centrar-diseno').addEventListener('click', function () { moverA({ cx: 0.5 }); });
    document.getElementById('arriba-diseno').addEventListener('click', function () { moverA({ cy: 0 }); });

    // Arrastrar el diseño sobre la prenda (ratón o dedo), sin salir de su zona.
    lienzo.addEventListener('pointerdown', function (e) {
        var c = e.target;
        if (!c.classList || !c.classList.contains('movible')) return;
        var zona = zonaActual();
        if (!zona || String(zona.id) !== c.dataset.zona) return;
        e.preventDefault();
        c.setPointerCapture(e.pointerId);
        var g = geometria(disenoPara(colores[estado.color]), zona);
        var r = c.getBoundingClientRect();
        var aj = ajusteDe(zona.id);
        arrastre = { c: c, x: e.clientX, y: e.clientY, cx: aj.cx, cy: aj.cy, libreX: (1 - g.w) * r.width, libreY: (1 - g.h) * r.height, aj: aj };
        c.classList.add('arrastrando');
        lienzo.classList.add('ajustando');
    });
    lienzo.addEventListener('pointermove', function (e) {
        if (!arrastre) return;
        var a = arrastre;
        var limitar = function (v) { return Math.min(1, Math.max(0, v)); };
        var cx = a.libreX > 1 ? limitar(a.cx + (e.clientX - a.x) / a.libreX) : a.cx;
        var cy = a.libreY > 1 ? limitar(a.cy + (e.clientY - a.y) / a.libreY) : a.cy;
        a.c.style.transform = 'translate(' + ((cx - a.cx) * a.libreX) + 'px,' + ((cy - a.cy) * a.libreY) + 'px)';
        a.nuevo = { cx: cx, cy: cy };
    });
    function soltarArrastre() {
        if (!arrastre) return;
        var a = arrastre;
        arrastre = null;
        mostrarGuia();
        if (a.nuevo) { a.aj.cx = a.nuevo.cx; a.aj.cy = a.nuevo.cy; pintar(); refrescarColeccion(); }
        else a.c.classList.remove('arrastrando');
    }
    lienzo.addEventListener('pointerup', soltarArrastre);
    lienzo.addEventListener('pointercancel', soltarArrastre);

    /* ---------- Logo traído desde la portada (paso 1) ---------- */

    try {
        var guardado = JSON.parse(sessionStorage.getItem('nga.logo') || 'null');
        if (guardado && guardado.datos) {
            sessionStorage.removeItem('nga.logo');
            fetch(guardado.datos).then(function (r) { return r.blob(); }).then(function (b) {
                leerLogo(new File([b], guardado.nombre || 'logo.png', { type: 'image/png' }), guardado);
                visor.scrollIntoView({ behavior: sinMovimiento ? 'auto' : 'smooth', block: 'start' });
            });
        }
    } catch (e) { /* sin sessionStorage: se sube desde la ficha */ }

    /* ---------- Tu colección ---------- */

    function caraDelDiseno() {
        var z = zonas.filter(function (o) { return o.id === estado.zonaId; })[0];
        return z ? z.cara : 'delantera';
    }

    function refrescarColeccion() {
        if (!estado.coleccion) return;
        var rejilla = document.getElementById('coleccion-rejilla');
        var cara = caraDelDiseno();
        rejilla.replaceChildren();
        colores.forEach(function (color, i) {
            var tarjeta = el('button', 'coleccion-tarjeta');
            tarjeta.type = 'button';
            tarjeta.setAttribute('aria-label', texto('ver_en_grande', { color: color.nombre }));
            if (!sinMovimiento) tarjeta.style.animationDelay = (i * 90) + 'ms';
            var marco = el('div', 'coleccion-foto');
            var escena = el('div', 'visor-lienzo');
            escena.appendChild(construirEscena(i, color.caras[cara] ? cara : 'delantera', { decorativa: true }));
            marco.appendChild(escena);
            tarjeta.appendChild(marco);
            tarjeta.appendChild(el('span', 'coleccion-nombre', color.nombre));
            tarjeta.addEventListener('click', function () {
                estado.color = i;
                estado.cara = color.caras[cara] ? cara : 'delantera';
                pintar('fundido');
                avisarListo();
                visor.scrollIntoView({ behavior: sinMovimiento ? 'auto' : 'smooth', block: 'center' });
            });
            rejilla.appendChild(tarjeta);
        });
        document.getElementById('coleccion-texto').textContent = texto('coleccion_texto', { n: colores.length, prenda: textos.prenda });
    }

    botonColeccion.addEventListener('click', function () {
        estado.coleccion = !estado.coleccion;
        coleccion.hidden = !estado.coleccion;
        botonColeccion.setAttribute('aria-expanded', String(estado.coleccion));
        botonColeccion.textContent = estado.coleccion ? textos.ocultar_coleccion : textos.ver_coleccion;
        if (estado.coleccion) {
            refrescarColeccion();
            coleccion.scrollIntoView({ behavior: sinMovimiento ? 'auto' : 'smooth', block: 'start' });
        }
    });

    pintar();
})();
