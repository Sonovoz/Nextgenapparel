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
        disenos: {},           // un diseño por zona: { zonaId: {modo, logo, texto, fuente, tinta, logoPx, logoTipo, nombre} }
        anadiendo: false,      // eligiendo la zona de un diseño nuevo
        zonaId: null,          // zona activa: la del diseño que se edita
        zonaElegida: false,    // el cliente eligió zona: no se cambia sola
        lupa: false,           // en el estudio, la lupa se activa a mano
        ajustes: {},           // por zona: { escala (0,2–1), cx, cy (0–1 del hueco libre) }
        version: 0,            // cambia con cada diseño nuevo (invalida la caché)
        nivel: 'diseno',       // diseno · cantidades · resumen (D142)
        lineas: [],            // índices de los colores del pedido, en orden
        cantidades: {}         // por color: { colorId: { tallaId: unidades } }
    };

    // estado.modo, estado.logo, estado.texto… son los del diseño de la zona activa
    // (o un borrador mientras aún no hay zona). Así cada zona lleva su propio diseño.
    function nuevoDiseno() { return { modo: 'logo', logo: null, texto: '', fuente: 0, tinta: 'auto', logoPx: null, logoTipo: null, nombre: '' }; }
    var borrador = nuevoDiseno();
    function actual() {
        if (estado.zonaId === null) return borrador;
        if (!estado.disenos[estado.zonaId]) estado.disenos[estado.zonaId] = nuevoDiseno();
        return estado.disenos[estado.zonaId];
    }
    ['modo', 'logo', 'texto', 'fuente', 'tinta', 'logoPx', 'logoTipo', 'nombre'].forEach(function (campo) {
        Object.defineProperty(estado, campo, { get: function () { return actual()[campo]; }, set: function (v) { actual()[campo] = v; } });
    });
    function disenoValido(d) { return !!d && (d.modo === 'logo' ? !!d.logo : d.texto.trim() !== ''); }
    function zonasConDiseno() {
        return Object.keys(estado.disenos).map(Number).filter(function (id) { return disenoValido(estado.disenos[id]); });
    }
    /** Ejecuta fn como si la zona activa fuera zonaId (para pintar los diseños de otras zonas). */
    function conDiseno(zonaId, fn) {
        var antes = estado.zonaId;
        estado.zonaId = zonaId;
        try { return fn(); } finally { estado.zonaId = antes; }
    }
    /** Activa una zona. Si se estaba preparando un diseño sin zona, pasa a esa zona. */
    function fijarZona(id) {
        if (estado.zonaId === null && disenoValido(borrador) && !disenoValido(estado.disenos[id])) {
            estado.disenos[id] = borrador;
            borrador = nuevoDiseno();
        }
        estado.zonaId = id;
    }

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
                        lista.push({ id: z.id, nombre: z.nombre, cara: cara, area: z.ancho * z.alto, precio: z.precio || '', rect: z });
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

        datos.zonas.forEach(function (zona) {
            if (!disenoValido(estado.disenos[zona.id])) return;
            var activa = zona.id === estado.zonaId;
            if (activa && opciones.movible) {
                var guia = el('div', 'zona-guia');
                guia.style.cssText = 'left:' + zona.x + '%;top:' + zona.y + '%;width:' + zona.ancho + '%;height:' + zona.alto + '%';
                escena.appendChild(guia);
            }
            conDiseno(zona.id, function () { return capaImpresa(color, cara, zona); }).then(function (capa) {
                var c = el('canvas', 'diseno' + (activa && opciones.estampar && !sinMovimiento ? ' estampar' : '') + (opciones.movible ? ' movible' : '') + (activa ? ' activo' : ''));
                if (opciones.movible) { c.dataset.zona = zona.id; c.dataset.color = indiceColor; }
                c.width = capa.width; c.height = capa.height;
                c.getContext('2d').drawImage(capa, 0, 0);
                c.setAttribute('aria-hidden', 'true');
                c.style.cssText = 'left:' + zona.x + '%;top:' + zona.y + '%;width:' + zona.ancho + '%;height:' + zona.alto + '%';
                escena.appendChild(c);
                if (activa && opciones.movible) ocultarCarga();
            });
        });

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
        document.querySelectorAll('.piel').forEach(function (b) { b.setAttribute('aria-pressed', String(Number(b.dataset.color) === estado.color)); });
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
    var estudio = document.getElementById('estudio');
    var enEstudio = function () { return !!estudio && estudio.classList.contains('activo'); };
    var lupaActiva = function () { return !enEstudio() || estado.lupa; };
    var lupaSucia = true;
    var AUMENTO = 2.4;

    if (lupa && conRaton) {
        lienzo.addEventListener('pointermove', function (e) {
            if (e.pointerType !== 'mouse' || !lienzo.querySelector('img')) return;
            if (arrastre || !lupaActiva()) { lupa.hidden = true; return; }
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

    // Zonas para elegir
    var grupoZonas = document.getElementById('zonas-diseno');
    zonas.forEach(function (z) {
        var b = el('button', 'zona-opcion', z.nombre);
        b.type = 'button';
        b.setAttribute('role', 'radio');
        b.setAttribute('aria-checked', 'false');
        b.dataset.zona = z.id;
        b.addEventListener('click', function () {
            if (b.disabled) return;
            apagarMarcas();
            var ocupada = disenoValido(estado.disenos[z.id]);
            if (!estado.anadiendo && !ocupada && hayDiseno() && estado.zonaId !== null) {
                // Mover el diseño actual a esta zona.
                estado.disenos[z.id] = estado.disenos[estado.zonaId];
                delete estado.disenos[estado.zonaId];
                delete estado.ajustes[z.id];
                estado.zonaId = z.id;
                estado.version++;
            } else {
                fijarZona(z.id); // editar el de esta zona o empezar uno nuevo aquí
            }
            estado.anadiendo = false;
            estado.zonaElegida = true;
            sincronizar();
            if (z.cara !== estado.cara) { estado.cara = z.cara; estampar = true; pintar('giro'); }
            else { estampar = true; pintar(); }
            if (!hayDiseno()) elegirHerramienta('diseno');
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

    /** ¿Se pisan dos zonas en la prenda? (misma cara y rectángulos que se cruzan) */
    function solapan(a, b) {
        if (a.id === b.id || a.cara !== b.cara) return false;
        var r = a.rect, q = b.rect;
        return r.x < q.x + q.ancho && q.x < r.x + r.ancho && r.y < q.y + q.alto && q.y < r.y + r.alto;
    }

    function marcarZona() {
        var usadas = zonasConDiseno();
        grupoZonas.querySelectorAll('[data-zona]').forEach(function (b) {
            var z = zonas.filter(function (o) { return o.id === Number(b.dataset.zona); })[0];
            // Bloqueada si se pisa con otra zona que ya tiene diseño (salvo la que se está moviendo).
            var choca = zonas.filter(function (o) {
                return usadas.indexOf(o.id) !== -1 && (estado.anadiendo || o.id !== estado.zonaId) && solapan(z, o);
            })[0];
            var activa = z.id === estado.zonaId && !estado.anadiendo && hayDiseno();
            var conDisenoAqui = usadas.indexOf(z.id) !== -1;
            b.disabled = !!choca;
            b.setAttribute('aria-checked', String(activa));
            b.dataset.estado = choca ? 'bloqueada' : (activa ? 'editando' : (conDisenoAqui ? 'con-diseno' : 'libre'));

            // Miniatura de la prenda (en el color elegido) con la zona marcada.
            var color = colores[estado.color].caras[z.cara] ? colores[estado.color] : colores.filter(function (c) { return c.caras[z.cara]; })[0];
            var mini = el('span', 'zona-mini');
            if (color) {
                var foto = el('img'); foto.src = color.caras[z.cara].url; foto.alt = ''; foto.loading = 'lazy';
                mini.appendChild(foto);
            }
            var marca = el('span', 'zona-mini-marca');
            marca.style.cssText = 'left:' + z.rect.x + '%;top:' + z.rect.y + '%;width:' + z.rect.ancho + '%;height:' + z.rect.alto + '%';
            mini.appendChild(marca);

            var info = el('span', 'zona-info');
            info.appendChild(el('strong', '', z.nombre));
            var medida = z.rect.ancho_mm ? (z.rect.ancho_mm / 10) + ' × ' + (z.rect.alto_mm / 10) + ' cm' : '';
            info.appendChild(el('small', '', [medida, z.precio ? '+' + z.precio + ' ' + textos.por_prenda : ''].filter(Boolean).join(' · ')));
            info.appendChild(el('em', 'zona-estado', choca ? texto('solapa_corto', { otra: choca.nombre })
                : (activa ? textos.zona_editando : (conDisenoAqui ? textos.zona_con_diseno : textos.zona_libre))));
            b.setAttribute('aria-label', z.nombre + '. ' + info.lastChild.textContent);
            b.replaceChildren(mini, info);
        });
        var aviso = document.getElementById('aviso-zona');
        if (aviso) aviso.textContent = estado.anadiendo ? textos.elige_zona_nuevo : '';
    }

    /** Tarjeta de decisión (D142): tus diseños con lo que suma cada uno, y «¿otro o seguimos?». */
    function pintarCapas() {
        var caja = document.getElementById('capas');
        if (!caja) return;
        var usadas = zonasConDiseno();
        caja.replaceChildren();
        caja.classList.toggle('decision', usadas.length > 0);
        if (usadas.length === 0) return;
        var conDisenos = zonas.filter(function (z) { return usadas.indexOf(z.id) !== -1; });
        caja.appendChild(el('p', 'capas-titulo', usadas.length === 1
            ? texto('decision_uno', { zona: conDisenos[0].nombre }) : texto('decision_varios', { n: usadas.length })));
        conDisenos.forEach(function (z) {
            var d = estado.disenos[z.id];
            var b = el('button', 'capa-diseno');
            b.type = 'button';
            b.setAttribute('aria-pressed', String(z.id === estado.zonaId));
            b.appendChild(el('strong', '', z.nombre));
            b.appendChild(el('span', '', d.modo === 'logo' ? (d.nombre || textos.pestana_logo) : '«' + d.texto.trim() + '»'));
            if (z.precio) b.appendChild(el('small', '', '+' + z.precio + textos.por_unidad));
            b.addEventListener('click', function () {
                fijarZona(z.id);
                estado.anadiendo = false;
                sincronizar();
                if (z.cara !== estado.cara) { estado.cara = z.cara; pintar('giro'); } else { pintar(); }
            });
            caja.appendChild(b);
        });
        var libres = zonas.filter(function (z) { return usadas.indexOf(z.id) === -1 && !zonas.some(function (o) { return usadas.indexOf(o.id) !== -1 && solapan(z, o); }); });
        var botones = el('div', 'decision-botones');
        if (libres.length) {
            caja.appendChild(el('p', 'decision-pregunta', textos.decision_pregunta));
            var mas = el('button', 'boton-pildora borde anadir', '+ ' + textos.anadir_diseno);
            mas.type = 'button';
            mas.addEventListener('click', function () {
                estado.anadiendo = true;
                marcarZona();
                elegirHerramienta('zona');
            });
            botones.appendChild(mas);
        }
        var seguir = el('button', 'boton-siguiente', textos.siguiente_cantidades + ' →');
        seguir.type = 'button';
        seguir.addEventListener('click', function () { irANivel('cantidades'); });
        botones.appendChild(seguir);
        caja.appendChild(botones);
    }

    /** Lleva los controles del panel al diseño de la zona activa. */
    function sincronizar() {
        pestanas.forEach(function (o) {
            var activa = o.dataset.modo === estado.modo;
            o.setAttribute('aria-selected', String(activa));
            o.tabIndex = activa ? 0 : -1;
            document.getElementById(o.getAttribute('aria-controls')).hidden = !activa;
        });
        campoTexto.value = estado.texto;
        document.querySelectorAll('#fuentes [role="radio"]').forEach(function (b) { b.setAttribute('aria-checked', String(Number(b.dataset.valor) === estado.fuente)); });
        document.querySelectorAll('#tintas [role="radio"]').forEach(function (b) { b.setAttribute('aria-checked', String(b.dataset.valor === estado.tinta)); });
        soltar.classList.toggle('con-logo', !!estado.logo);
        document.getElementById('nombre-logo').textContent = estado.logo ? estado.nombre : '';
        marcarZona();
        pintarCapas();
        avisarListo();
    }

    /** Zona de partida: el pecho para un logo; la más grande de delante para un texto. */
    function zonaPorDefecto() {
        if (estado.zonaElegida || zonas.length === 0) return;
        var delanteras = zonas.filter(function (z) { return z.cara === 'delantera'; });
        var candidatas = delanteras.length ? delanteras : zonas;
        var elegida = candidatas.reduce(function (a, b) {
            return estado.modo === 'logo' ? (b.area < a.area ? b : a) : (b.area > a.area ? b : a);
        });
        fijarZona(elegida.id);
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
        marcarZona();
        pintarCapas();
        avisarListo();
        if (hayDiseno()) {
            abrirEstudio();
            setTimeout(mostrarPista, 700);
        }
        refrescarColeccion();
    }

    function avisarListo() {
        actualizarAjustador();
        pedirPrecio();
        // Botones de borrar: visibles con algún diseño; «Quitar» nombra su zona.
        acciones.hidden = zonasConDiseno().length === 0;
        var zonaActiva = zonas.filter(function (o) { return o.id === estado.zonaId; })[0];
        document.getElementById('quitar-texto').textContent = zonaActiva && hayDiseno() ? texto('quitar_diseno_zona', { zona: zonaActiva.nombre }) : textos.quitar_diseno;
        document.getElementById('quitar-diseno').hidden = !hayDiseno();
        if (!hayDiseno()) { estadoMarca.textContent = ''; return; }
        var color = colores[estado.color];
        var n = zonasConDiseno().length;
        estadoMarca.replaceChildren(
            el('strong', '', texto('listo', { prenda: textos.prenda, color: color.nombre })),
            document.createTextNode(' ' + (n > 1 ? texto('listo_varios', { n: n }) : textos.listo_2))
        );
    }

    // Pestañas: mi logo / escribir mi marca
    var pestanas = panel.querySelectorAll('.pestanas [role="tab"]');
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

    /* Animación de carga: «Estampando tu diseño…» con un cabezal de luz que barre la
       prenda. Dura al menos MINIMO_CARGA para que no parpadee con imágenes pequeñas. */
    var MINIMO_CARGA = 650;
    var cargando = document.getElementById('cargando');
    var inicioCarga = 0;
    var esperaCarga = null;
    function mostrarCarga() {
        if (!cargando) return;
        inicioCarga = Date.now();
        cargando.hidden = false;
        cargando.classList.remove('saliendo');
        clearTimeout(esperaCarga);
        esperaCarga = setTimeout(ocultarCarga, 8000); // por si algo falla, nunca se queda colgada
    }
    function ocultarCarga() {
        if (!cargando || cargando.hidden) return;
        var falta = Math.max(0, MINIMO_CARGA - (Date.now() - inicioCarga));
        clearTimeout(esperaCarga);
        setTimeout(function () {
            cargando.classList.add('saliendo');
            setTimeout(function () { cargando.hidden = true; cargando.classList.remove('saliendo'); }, 380);
        }, falta);
    }

    /** @param original  datos del archivo original si llega reducido desde la portada */
    function leerLogo(archivo, original) {
        mostrarError('');
        if (!archivo) return;
        if (!/^image\/(png|jpeg|webp|svg\+xml|gif)$/.test(archivo.type)) { mostrarError(textos.error_archivo); return; }
        if (archivo.size > PESO_MAXIMO) { mostrarError(textos.error_grande); return; }
        abrirEstudio();
        mostrarCarga();

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
            estado.nombre = original ? original.nombre : archivo.name;
            if (estado.zonaId !== null) delete estado.ajustes[estado.zonaId];
            estado.version++;
            if (estado.modo !== 'logo') pestanas[0].click(); else colocarDiseno();
            elegirHerramienta('ajustar'); // el logo ya está: toca colocarlo
            sincronizar();
        };
        img.onerror = function () { URL.revokeObjectURL(url); ocultarCarga(); mostrarError(textos.error_archivo); };
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
        if (estado.zonaId !== null) { delete estado.disenos[estado.zonaId]; delete estado.ajustes[estado.zonaId]; } else { borrador = nuevoDiseno(); }
        estado.version++;
        var resto = zonasConDiseno();
        if (resto.length) { fijarZona(resto[0]); } else { estado.zonaElegida = false; }
        sincronizar();
        var z = zonas.filter(function (o) { return o.id === estado.zonaId; })[0];
        if (z && z.cara !== estado.cara) { estado.cara = z.cara; pintar('giro'); } else { pintar(); }
        refrescarColeccion();
    });

    // Empezar de nuevo: borra todos los diseños (pide un segundo toque para confirmar).
    var empezar = document.getElementById('empezar-de-nuevo');
    var esperaConfirmar = null;
    empezar.addEventListener('click', function () {
        if (!empezar.classList.contains('confirmar')) {
            empezar.classList.add('confirmar');
            document.getElementById('empezar-texto').textContent = textos.confirmar_empezar;
            clearTimeout(esperaConfirmar);
            esperaConfirmar = setTimeout(function () {
                empezar.classList.remove('confirmar');
                document.getElementById('empezar-texto').textContent = textos.empezar_de_nuevo;
            }, 3500);
            return;
        }
        clearTimeout(esperaConfirmar);
        empezar.classList.remove('confirmar');
        document.getElementById('empezar-texto').textContent = textos.empezar_de_nuevo;
        estado.disenos = {};
        estado.ajustes = {};
        borrador = nuevoDiseno();
        estado.zonaId = null;
        estado.zonaElegida = false;
        estado.anadiendo = false;
        estado.version++;
        sincronizar();
        elegirHerramienta('diseno');
        if (estado.cara !== 'delantera') { estado.cara = 'delantera'; pintar('giro'); } else { pintar(); }
        refrescarColeccion();
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
        var vacio = document.getElementById('ajustador-vacio');
        if (vacio) vacio.hidden = !!zona;
        if (!hayDiseno()) {
            bloqueCalidad.dataset.nivel = '';
            document.getElementById('calidad-etiqueta').textContent = textos.calidad_sin_diseno;
            ['calidad-ppp', 'calidad-mensaje', 'calidad-consejo', 'calidad-fondo'].forEach(function (id) { document.getElementById(id).textContent = ''; });
            document.getElementById('calidad-relleno').style.width = '0';
        }
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
            document.getElementById('calidad-relleno').style.width = '100%';
            if (estado.logoTipo === 'vectorial' && estado.modo === 'logo') fondo.textContent = t.formato_pedido;
            return;
        }

        var valor = Math.min(ppp(estado.logoPx.w, anchoMm), ppp(estado.logoPx.h, altoMm));
        var n = nivel(valor);
        document.getElementById('calidad-relleno').style.width = Math.min(100, Math.round(valor / calidad.umbrales.correcto * 100)) + '%';
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
        if (!c.classList || !c.classList.contains('movible') || !enEstudio() || estado.lupa) return;
        var zona = zonaActual();
        if (!zona || String(zona.id) !== c.dataset.zona) {
            // Pulsar otro de tus diseños sobre la prenda lo elige para editarlo.
            fijarZona(Number(c.dataset.zona));
            estado.anadiendo = false;
            sincronizar();
            pintar();
            return;
        }
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
            });
        }
    } catch (e) { /* sin sessionStorage: se sube desde la ficha */ }

    /* ---------- Tu colección ---------- */

    function caraDelDiseno() {
        var z = zonas.filter(function (o) { return o.id === estado.zonaId; })[0];
        return z ? z.cara : 'delantera';
    }

    /** «Skins»: la prenda en cada color, con el diseño puesto. Pulsar una elige el color. */
    function refrescarColeccion() {
        var lista = document.getElementById('pieles-lista');
        if (!lista || !enEstudio()) return;
        var cara = hayDiseno() ? caraDelDiseno() : estado.cara;
        lista.replaceChildren();
        colores.forEach(function (color, i) {
            var piel = el('button', 'piel');
            piel.type = 'button';
            piel.dataset.color = i;
            piel.setAttribute('aria-pressed', String(i === estado.color));
            piel.setAttribute('aria-label', color.nombre);
            var marco = el('span', 'marco');
            var escena = el('span', 'visor-lienzo');
            escena.appendChild(construirEscena(i, color.caras[cara] ? cara : 'delantera', { decorativa: true }));
            marco.appendChild(escena);
            piel.appendChild(marco);
            piel.appendChild(el('span', '', color.nombre));
            piel.addEventListener('click', function () {
                estado.color = i;
                if (hayDiseno() && color.caras[cara]) estado.cara = cara;
                pintar('fundido');
                avisarListo();
            });
            lista.appendChild(piel);
        });
        document.getElementById('coleccion-texto').textContent = hayDiseno()
            ? texto('coleccion_texto', { n: colores.length, prenda: textos.prenda }) : '';
    }

    /* ---------- Estudio: entrar, salir y mandos ---------- */

    var cerrarBoton = document.getElementById('cerrar-estudio');
    var botonLupa = document.getElementById('boton-lupa');
    var desdeHistorial = false;

    // En la página de producto, el configurador ES la página: siempre abierto.
    var esPagina = !!estudio && estudio.classList.contains('pagina');

    function abrirEstudio() {
        if (!estudio || enEstudio()) return;
        estudio.classList.add('activo');
        document.body.classList.add('estudio-abierto');
        try { history.pushState({ estudio: true }, ''); } catch (e) {}
        lupaSucia = true;
        pintar();
        refrescarColeccion();
        cerrarBoton.focus({ preventScroll: true });
    }

    function cerrarEstudio() {
        if (esPagina) { window.location.href = estudio.dataset.salir; return; }
        if (!enEstudio()) return;
        estudio.classList.remove('activo', 'con-lupa');
        document.body.classList.remove('estudio-abierto');
        estado.lupa = false;
        botonLupa.setAttribute('aria-pressed', 'false');
        lupa.hidden = true;
        lupaSucia = true;
        pintar();
        if (!desdeHistorial) { try { if (history.state && history.state.estudio) history.back(); } catch (e) {} }
        desdeHistorial = false;
        var abrir = document.querySelector('[data-abrir-estudio]');
        if (abrir) abrir.focus({ preventScroll: true });
    }

    document.querySelectorAll('[data-abrir-estudio]').forEach(function (b) { b.addEventListener('click', abrirEstudio); });
    cerrarBoton.addEventListener('click', cerrarEstudio);
    window.addEventListener('popstate', function () { if (enEstudio()) { desdeHistorial = true; cerrarEstudio(); } });
    document.querySelectorAll('[data-girar]').forEach(function (b) {
        b.addEventListener('click', function () { cambiarCara(estado.cara === 'delantera' ? 'trasera' : 'delantera'); });
    });
    function alternarLupa() {
        estado.lupa = !estado.lupa;
        botonLupa.setAttribute('aria-pressed', String(estado.lupa));
        estudio.classList.toggle('con-lupa', estado.lupa);
        if (!estado.lupa) lupa.hidden = true;
    }
    botonLupa.addEventListener('click', alternarLupa);

    // Herramientas: Diseño · Zona · Ajustar
    var herramientas = panel.querySelectorAll('.herramientas [role="tab"]');
    function elegirHerramienta(nombre) {
        herramientas.forEach(function (h) {
            var activa = h.dataset.herramienta === nombre;
            h.setAttribute('aria-selected', String(activa));
            h.tabIndex = activa ? 0 : -1;
            document.getElementById(h.getAttribute('aria-controls')).hidden = !activa;
        });
    }
    herramientas.forEach(function (h) { h.addEventListener('click', function () { elegirHerramienta(h.dataset.herramienta); }); });

    // Pista animada la primera vez que el diseño cae sobre la prenda.
    var pistaMostrada = false;
    function mostrarPista() {
        var pista = document.getElementById('pista');
        if (pistaMostrada || !pista || !enEstudio()) return;
        pistaMostrada = true;
        pista.classList.remove('visible');
        void pista.offsetWidth;
        pista.classList.add('visible');
    }

    // Mandos de teclado, como en una consola (solo dentro del estudio y fuera de los campos de texto).
    document.addEventListener('keydown', function (e) {
        if (!enEstudio()) return;
        if (e.key === 'Escape') { if (!esPagina) cerrarEstudio(); return; }
        var etiqueta = (e.target.tagName || '').toLowerCase();
        if (etiqueta === 'input' || etiqueta === 'textarea' || e.ctrlKey || e.metaKey || e.altKey) return;
        var zona = hayDiseno() ? zonaActual() : null;
        var aj = zona ? ajusteDe(zona.id) : null;
        var paso = e.shiftKey ? 0.1 : 0.025;
        var tecla = e.key.toLowerCase();
        var manejado = true;
        if (aj && e.key === 'ArrowLeft') aj.cx = Math.max(0, aj.cx - paso);
        else if (aj && e.key === 'ArrowRight') aj.cx = Math.min(1, aj.cx + paso);
        else if (aj && e.key === 'ArrowUp') aj.cy = Math.max(0, aj.cy - paso);
        else if (aj && e.key === 'ArrowDown') aj.cy = Math.min(1, aj.cy + paso);
        else if (aj && (e.key === '+' || e.key === '=')) aj.escala = Math.min(1, Math.round((aj.escala + 0.05) * 100) / 100);
        else if (aj && (e.key === '-' || e.key === '_')) aj.escala = Math.max(0.2, Math.round((aj.escala - 0.05) * 100) / 100);
        else if (tecla === 'f') { cambiarCara(estado.cara === 'delantera' ? 'trasera' : 'delantera'); return e.preventDefault(); }
        else if (tecla === 'c') { estado.color = (estado.color + 1) % colores.length; pintar('fundido'); avisarListo(); return e.preventDefault(); }
        else if (tecla === 'l') { alternarLupa(); return e.preventDefault(); }
        else manejado = false;
        if (!manejado) return;
        e.preventDefault();
        mostrarGuia();
        pintar();
        actualizarAjustador();
        clearTimeout(esperaTeclas);
        esperaTeclas = setTimeout(refrescarColeccion, 250);
    });
    var esperaTeclas = null;


    /* ---------- Niveles: 1 Diseño · 2 Tallas y colores · 3 Resumen (D142) ----------
       El precio SIEMPRE lo calcula el servidor (motor de precios); aquí solo se muestra. */

    var urlPrecio = visor.dataset.precio;
    var botonesNivel = document.querySelectorAll('#niveles [data-nivel]');
    var precioBarra = {
        etiqueta: document.getElementById('precio-etiqueta'),
        importe: document.getElementById('precio-importe'),
        detalle: document.getElementById('precio-detalle')
    };
    var precioInicial = precioBarra.importe ? {
        etiqueta: precioBarra.etiqueta.textContent, importe: precioBarra.importe.textContent, detalle: precioBarra.detalle.textContent
    } : null;
    var pie = document.getElementById('estudio-pie');
    var pieSiguiente = document.getElementById('pie-siguiente');
    var precio = null;          // última respuesta del servidor
    var clavePrecio = '';
    var esperaPrecio = null;
    var peticion = null;

    function textoUds(n) { return texto(n === 1 ? 'ud' : 'uds', { n: n }); }
    function textoDisenos(n) { return n === 1 ? textos.un_diseno : texto('n_disenos', { n: n }); }
    function unidadesDe(colorId) {
        var c = estado.cantidades[colorId] || {};
        return Object.keys(c).reduce(function (s, t) { return s + c[t]; }, 0);
    }

    function consultaPrecio() {
        var q = new URLSearchParams();
        q.append('color', colores[estado.color].id);
        zonasConDiseno().forEach(function (id) { q.append('zonas[]', id); });
        estado.lineas.forEach(function (i) {
            var id = colores[i].id;
            var c = estado.cantidades[id] || {};
            Object.keys(c).forEach(function (t) { if (c[t] > 0) q.append('cantidades[' + id + '][' + t + ']', c[t]); });
        });
        return q.toString();
    }

    function pedirPrecio() {
        if (!urlPrecio || !colores.length) return;
        if (zonasConDiseno().length === 0 && estado.nivel !== 'diseno') irANivel('diseno');
        var clave = consultaPrecio();
        if (clave === clavePrecio) return;
        clearTimeout(esperaPrecio);
        esperaPrecio = setTimeout(function () {
            clavePrecio = clave;
            if (peticion) peticion.abort();
            peticion = window.AbortController ? new AbortController() : null;
            if (estudio) estudio.classList.add('calculando');
            fetch(urlPrecio + '?' + clave, { headers: { Accept: 'application/json' }, signal: peticion ? peticion.signal : undefined })
                .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
                .then(function (datos) { precio = datos; pintarPrecio(); })
                .catch(function (e) { if (e.name !== 'AbortError') clavePrecio = ''; })
                .finally(function () { if (estudio) estudio.classList.remove('calculando'); });
        }, 160);
    }

    /** Arriba (y en el pie del móvil): «Desde», «Tu prenda» o el total, según lo configurado. */
    function resumenPrecio() {
        var n = zonasConDiseno().length;
        if (!n || !precio || precio.disenos !== n || (precio.por_unidad === null && precio.total === null)) return null;
        if (precio.unidades > 0 && precio.total) {
            return { etiqueta: textos.total, importe: precio.total, detalle: textoUds(precio.unidades) + ' · ' + precio.por_unidad + textos.por_unidad + ' · ' + textos.iva_incluido };
        }
        if (precio.por_unidad === null) return null;
        return { etiqueta: precio.varia ? textos.tu_prenda_desde : textos.tu_prenda, importe: precio.por_unidad, detalle: textos.por_unidad + ' · ' + textoDisenos(n) + ' · ' + textos.iva_incluido };
    }

    function pintarPrecio() {
        var r = resumenPrecio();
        if (precioBarra.importe) {
            var v = r || precioInicial;
            var cambia = precioBarra.importe.textContent !== v.importe;
            precioBarra.etiqueta.textContent = v.etiqueta;
            precioBarra.importe.textContent = v.importe;
            precioBarra.detalle.textContent = v.detalle;
            if (cambia && !sinMovimiento) { precioBarra.importe.classList.remove('latido'); void precioBarra.importe.offsetWidth; precioBarra.importe.classList.add('latido'); }
        }
        pintarNiveles();
        pintarCuenta();
        pintarResumen();
        pintarPie(r);
    }

    function pintarNiveles() {
        var hay = zonasConDiseno().length > 0;
        var listo = hay && !!precio && precio.confirmable && precio.disenos === zonasConDiseno().length;
        botonesNivel.forEach(function (b) {
            var n = b.dataset.nivel;
            b.disabled = n === 'cantidades' ? !hay : (n === 'resumen' ? !listo : false);
            if (n === estado.nivel) b.setAttribute('aria-current', 'step'); else b.removeAttribute('aria-current');
        });
        var ir = document.getElementById('ir-resumen');
        if (ir) ir.disabled = !listo;
        if (estado.nivel === 'resumen' && !listo) irANivel('cantidades');
    }

    function irANivel(nombre) {
        if (nombre !== 'diseno' && zonasConDiseno().length === 0) nombre = 'diseno';
        estado.nivel = nombre;
        ['diseno', 'cantidades', 'resumen'].forEach(function (n) {
            var caja = document.getElementById('nivel-' + n);
            if (caja) caja.hidden = n !== nombre;
        });
        if (nombre === 'cantidades' && estado.lineas.length === 0) anadirLinea(estado.color);
        var panelEstudio = document.getElementById('estudio-panel');
        if (panelEstudio) panelEstudio.scrollTop = 0;
        pintarLineas();
        pintarPrecio();
        pedirPrecio();
    }

    botonesNivel.forEach(function (b) { b.addEventListener('click', function () { irANivel(b.dataset.nivel); }); });
    document.querySelectorAll('[data-ir]').forEach(function (b) { b.addEventListener('click', function () { irANivel(b.dataset.ir); }); });

    /* Tallas y colores: una línea por color, con las mismas zonas y diseños. */
    function anadirLinea(i) {
        if (estado.lineas.indexOf(i) === -1) estado.lineas.push(i);
        estado.cantidades[colores[i].id] = estado.cantidades[colores[i].id] || {};
    }

    function verColor(i) {
        if (estado.color === i) return;
        estado.color = i;
        var cara = hayDiseno() ? caraDelDiseno() : estado.cara;
        if (colores[i].caras[cara]) estado.cara = cara;
        pintar('fundido');
        avisarListo();
    }

    function muestraColor(color) {
        var m = el('span', 'punto-color');
        m.style.background = color.hex || '#888';
        return m;
    }

    function pintarLineas() {
        var caja = document.getElementById('lineas-color');
        if (!caja) return;
        caja.replaceChildren();
        estado.lineas.forEach(function (i) {
            var color = colores[i];
            var cant = estado.cantidades[color.id];
            var fila = el('div', 'linea-color');
            fila.classList.toggle('viendo', i === estado.color);
            var cabeza = el('div', 'linea-cabeza');
            var ver = el('button', 'linea-ver');
            ver.type = 'button';
            ver.setAttribute('aria-label', texto('ver_en_prenda', { color: color.nombre }));
            ver.appendChild(muestraColor(color));
            ver.appendChild(el('strong', '', color.nombre));
            var cuenta = el('span', 'linea-unidades', textoUds(unidadesDe(color.id)));
            ver.appendChild(cuenta);
            ver.addEventListener('click', function () { verColor(i); pintarLineas(); });
            cabeza.appendChild(ver);
            if (estado.lineas.length > 1) {
                var quitar = el('button', 'linea-quitar', '×');
                quitar.type = 'button';
                quitar.setAttribute('aria-label', texto('quitar_color', { color: color.nombre }));
                quitar.addEventListener('click', function () {
                    estado.lineas.splice(estado.lineas.indexOf(i), 1);
                    delete estado.cantidades[color.id];
                    if (estado.color === i) verColor(estado.lineas[0]);
                    pintarLineas();
                    pedirPrecio();
                });
                cabeza.appendChild(quitar);
            }
            fila.appendChild(cabeza);
            var rejilla = el('div', 'tallas-rejilla');
            color.tallas.forEach(function (t) {
                var celda = el('div', 'talla-celda');
                var id = 'cantidad-' + color.id + '-' + t.id;
                var etiqueta = el('label', 'talla-nombre', t.nombre);
                etiqueta.htmlFor = id;
                var campo = document.createElement('input');
                campo.type = 'number';
                campo.id = id;
                campo.inputMode = 'numeric';
                campo.min = 0;
                campo.max = 10000;
                campo.step = 1;
                campo.value = cant[t.id] || 0;
                var fijar = function (n) {
                    n = Math.max(0, Math.min(10000, Math.floor(Number(n) || 0)));
                    if (n > 0) cant[t.id] = n; else delete cant[t.id];
                    campo.value = n;
                    celda.classList.toggle('con-unidades', n > 0);
                    cuenta.textContent = textoUds(unidadesDe(color.id));
                    pedirPrecio();
                };
                celda.classList.toggle('con-unidades', (cant[t.id] || 0) > 0);
                var menos = el('button', 'talla-boton', '−');
                menos.type = 'button';
                menos.setAttribute('aria-label', texto('una_menos', { talla: t.nombre }));
                menos.addEventListener('click', function () { fijar((cant[t.id] || 0) - 1); });
                var mas = el('button', 'talla-boton', '+');
                mas.type = 'button';
                mas.setAttribute('aria-label', texto('una_mas', { talla: t.nombre }));
                mas.addEventListener('click', function () { fijar((cant[t.id] || 0) + 1); });
                campo.addEventListener('input', function () { if (campo.value !== '') fijar(campo.value); });
                campo.addEventListener('change', function () { fijar(campo.value); });
                campo.addEventListener('focus', function () { campo.select(); });
                var mando = el('div', 'talla-mando');
                mando.appendChild(menos);
                mando.appendChild(campo);
                mando.appendChild(mas);
                celda.appendChild(etiqueta);
                celda.appendChild(mando);
                rejilla.appendChild(celda);
            });
            fila.appendChild(rejilla);
            caja.appendChild(fila);
        });

        var libres = document.getElementById('colores-libres');
        if (!libres) return;
        libres.replaceChildren();
        colores.forEach(function (color, i) {
            if (estado.lineas.indexOf(i) !== -1 || !color.tallas.length) return;
            var b = el('button', 'boton-pildora borde color-libre');
            b.type = 'button';
            b.appendChild(muestraColor(color));
            b.appendChild(document.createTextNode('+ ' + color.nombre));
            b.addEventListener('click', function () { anadirLinea(i); verColor(i); pintarLineas(); pedirPrecio(); });
            libres.appendChild(b);
        });
        document.getElementById('anadir-color').hidden = libres.children.length === 0;
    }

    function listaMensajes(clase, mensajes) {
        var ul = el('ul', 'mensajes ' + clase);
        mensajes.forEach(function (m) { ul.appendChild(el('li', '', m)); });
        return ul;
    }

    function pintarCuenta() {
        var caja = document.getElementById('cuenta');
        if (!caja || estado.nivel !== 'cantidades') return;
        caja.replaceChildren();
        var unidades = estado.lineas.reduce(function (s, i) { return s + unidadesDe(colores[i].id); }, 0);
        if (unidades === 0 || !precio || precio.unidades === 0) {
            caja.appendChild(el('p', 'claro', textos.elige_cantidades));
            return;
        }
        if (precio.total) {
            var total = el('p', 'cuenta-total');
            total.appendChild(el('span', '', textos.total));
            total.appendChild(el('strong', '', precio.total));
            caja.appendChild(total);
            caja.appendChild(el('p', 'claro', textoUds(precio.unidades) + ' · ' + texto('media', { precio: precio.por_unidad }) + ' · ' + textos.iva_incluido));
        }
        if (precio.avisos.length) caja.appendChild(listaMensajes('avisos', precio.avisos));
        if (precio.impedimentos.length) caja.appendChild(listaMensajes('impedimentos', precio.impedimentos));
    }

    function pintarResumen() {
        var caja = document.getElementById('resumen');
        if (!caja || estado.nivel !== 'resumen' || !precio) return;
        caja.replaceChildren();
        caja.appendChild(el('h3', '', textos.resumen_disenos));
        var ul = el('ul', 'resumen-lista');
        zonas.filter(function (z) { return zonasConDiseno().indexOf(z.id) !== -1; }).forEach(function (z) {
            var d = estado.disenos[z.id];
            var li = el('li');
            li.appendChild(el('strong', '', z.nombre));
            li.appendChild(el('span', '', d.modo === 'logo' ? (d.nombre || textos.pestana_logo) : '«' + d.texto.trim() + '»'));
            if (z.precio) li.appendChild(el('small', '', '+' + z.precio + textos.por_unidad));
            ul.appendChild(li);
        });
        caja.appendChild(ul);
        caja.appendChild(el('h3', '', textos.resumen_prendas));
        var prendas = el('ul', 'resumen-lista');
        estado.lineas.forEach(function (i) {
            var color = colores[i];
            var c = estado.cantidades[color.id] || {};
            var partes = color.tallas.filter(function (t) { return c[t.id] > 0; }).map(function (t) { return t.nombre + ' × ' + c[t.id]; });
            if (!partes.length) return;
            var li = el('li');
            var nombre = el('strong');
            nombre.appendChild(muestraColor(color));
            nombre.appendChild(document.createTextNode(textos.prenda + ' · ' + color.nombre));
            li.appendChild(nombre);
            li.appendChild(el('span', '', partes.join(' · ')));
            li.appendChild(el('small', '', textoUds(unidadesDe(color.id))));
            prendas.appendChild(li);
        });
        caja.appendChild(prendas);
        if (precio.avisos.length) caja.appendChild(listaMensajes('avisos', precio.avisos));
        var total = el('p', 'cuenta-total');
        total.appendChild(el('span', '', textos.resumen_total));
        total.appendChild(el('strong', '', precio.total || '—'));
        caja.appendChild(total);
        caja.appendChild(el('p', 'claro', textoUds(precio.unidades) + ' · ' + texto('media', { precio: precio.por_unidad })));
    }

    /** Móvil: barra fija con el precio y el siguiente paso. */
    function pintarPie(r) {
        if (!pie) return;
        var hay = zonasConDiseno().length > 0;
        pie.hidden = !hay;
        if (!hay) return;
        r = r || resumenPrecio();
        document.getElementById('pie-importe').textContent = r ? r.importe : textos.calculando;
        document.getElementById('pie-detalle').textContent = r ? r.etiqueta + ' · ' + r.detalle : '';
        var siguiente = { diseno: ['cantidades', textos.siguiente_cantidades], cantidades: ['resumen', textos.siguiente_resumen], resumen: null }[estado.nivel];
        pieSiguiente.hidden = !siguiente;
        if (siguiente) {
            pieSiguiente.dataset.ir = siguiente[0];
            pieSiguiente.textContent = siguiente[1] + ' →';
            pieSiguiente.disabled = siguiente[0] === 'resumen' && !(precio && precio.confirmable);
        }
    }
    if (pieSiguiente) pieSiguiente.addEventListener('click', function () { irANivel(pieSiguiente.dataset.ir); });

    if (esPagina) {
        document.body.classList.add('estudio-abierto');
        refrescarColeccion();
    }

    pintar();
})();
