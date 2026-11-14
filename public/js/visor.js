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
        cantidades: {},        // por color: { colorId: { tallaId: unidades } }
        errorSubida: '',       // mensaje si un diseño no se acepta al pasar al nivel 2
        subiendo: false
    };

    // estado.modo, estado.logo, estado.texto… son los del diseño de la zona activa
    // (o un borrador mientras aún no hay zona). Así cada zona lleva su propio diseño.
    function nuevoDiseno() { return { modo: 'logo', logo: null, texto: '', fuente: 0, tinta: 'auto', logoPx: null, logoTipo: null, nombre: '', archivo: null, uuid: null, avisos: null, pppArchivo: null }; }
    var borrador = nuevoDiseno();
    function actual() {
        if (estado.zonaId === null) return borrador;
        if (!estado.disenos[estado.zonaId]) estado.disenos[estado.zonaId] = nuevoDiseno();
        return estado.disenos[estado.zonaId];
    }
    ['modo', 'logo', 'texto', 'fuente', 'tinta', 'logoPx', 'logoTipo', 'nombre', 'archivo', 'uuid', 'avisos', 'pppArchivo'].forEach(function (campo) {
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
                // Si falla (red, CORS), se olvida para poder reintentar en el siguiente pintado.
                img.onerror = function (e) { delete cacheFotos[url]; rechazar(e); };
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
     * El diseño en mm reales (revisión N3 del 5.8, A1): se encaja con la proporción de la
     * zona en mm, no con la del rectángulo dibujado en la foto, que puede ser distinta.
     * Así el tamaño, la calidad y lo que se guarda en el carrito no deforman el diseño.
     */
    function geometriaMm(diseno, zona) {
        var aj = ajusteDe(zona.id);
        var proporcion = diseno.width / diseno.height;
        var w, h;
        if (proporcion > zona.ancho_mm / zona.alto_mm) { w = zona.ancho_mm * aj.escala; h = w / proporcion; }
        else { h = zona.alto_mm * aj.escala; w = h * proporcion; }
        return { x: (zona.ancho_mm - w) * aj.cx, y: (zona.alto_mm - h) * aj.cy, w: w, h: h };
    }

    /**
     * Capa del diseño «impreso» sobre la zona de una foto: un canvas del tamaño
     * de la zona (en píxeles de la foto) que toma las luces, sombras y pliegues
     * de la tela. Si el navegador no deja leer la foto, se devuelve sin fundir.
     */
    function claveCapa(color, cara, zona) {
        var aj = ajusteDe(zona.id);
        return [estado.version, estado.modo, estado.fuente, tintaPara(color), estado.texto, color.caras[cara].url, zona.id, aj.escala, aj.cx, aj.cy].join('|');
    }

    function capaImpresa(color, cara, zona) {
        var datos = color.caras[cara];
        var diseno = disenoPara(color);
        var aj = ajusteDe(zona.id);
        var clave = claveCapa(color, cara, zona);
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
            }, function () {
                // La foto no cargó: sin capa (y sin promesa sin capturar); se reintenta al volver a pintar.
                delete cacheCapas[conDiseno(zona.id, function () { return claveCapa(color, cara, zona); })];
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
        if (!hayDiseno()) actualizarGuia(null); // D237: orienta antes de subir el diseño
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
            var deAhora = zonas.filter(function (o) { return o.id === estado.zonaId; })[0];
            // Solo se mueve a una zona que se pisa con la del diseño activo (pecho ↔ frontal: «más grande o más
            // pequeño»). Una zona independiente (la espalda) es un diseño nuevo ahí: nunca se lleva el de delante.
            var mover = !estado.anadiendo && !ocupada && hayDiseno() && !!deAhora && solapan(z, deAhora);
            if (mover) {
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
            refrescarColeccion();
            // Zona vacía: en el mismo clic, el selector de archivos (el cliente elige dónde y sube).
            if (!hayDiseno()) { elegirHerramienta('diseno'); entrada.click(); }
        });
        grupoZonas.appendChild(b);
    });
    marcarZona(); // las tarjetas con su dibujo y precio desde el principio, antes de subir nada

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

    /**
     * Decisión del paso 1 (D142, D198), en dos partes para que no estorbe:
     * - en el panel, «¿Quieres otro diseño?» con un botón por zona libre;
     * - abajo, fija y fina: qué diseños llevas, el error si lo hay y «Siguiente».
     * La lista de diseños no se repite: las tarjetas de zona ya dicen cuál tiene diseño.
     */
    var disenosVistos = 0;
    var invitaHasta = 0;
    function pintarCapas() {
        var caja = document.getElementById('capas');
        var pie = document.getElementById('capas-pie');
        if (!caja || !pie) return;
        var usadas = zonasConDiseno();
        // Se repinta entero: se recuerda qué control tenía el foco para devolvérselo (WCAG 2.4.3).
        var activo = document.activeElement;
        var foco = activo && (caja.contains(activo) || pie.contains(activo)) ? activo.dataset.foco : null;
        caja.replaceChildren();
        pie.replaceChildren();
        caja.classList.toggle('decision', usadas.length > 0);
        pie.hidden = usadas.length === 0;
        if (usadas.length === 0) return;
        var conDisenos = zonas.filter(function (z) { return usadas.indexOf(z.id) !== -1; });

        var libres = zonas.filter(function (z) { return usadas.indexOf(z.id) === -1 && !zonas.some(function (o) { return usadas.indexOf(o.id) !== -1 && solapan(z, o); }); });
        if (libres.length) {
            caja.appendChild(el('p', 'decision-pregunta', textos.decision_pregunta));
            // Un botón por zona libre: «+ Espalda · +4,24 €». Elige la zona y abre el selector de archivos.
            var otras = el('div', 'decision-zonas');
            libres.forEach(function (z) {
                var mas = el('button', 'boton-pildora borde anadir', '+ ' + z.nombre + (z.precio ? ' · +' + z.precio : ''));
                mas.type = 'button';
                mas.dataset.foco = 'anadir-' + z.id;
                mas.setAttribute('aria-label', texto('anadir_en', { zona: z.nombre }));
                mas.addEventListener('click', function () {
                    var boton = grupoZonas.querySelector('[data-zona="' + z.id + '"]');
                    if (boton) boton.click();
                });
                otras.appendChild(mas);
            });
            caja.appendChild(otras);
        }

        // Abajo solo el botón; la caja aparece solo si hay algo que resolver (un error).
        pie.classList.toggle('con-aviso', !!estado.errorSubida);
        if (estado.errorSubida) {
            var error = el('p', 'error-subida', estado.errorSubida);
            error.setAttribute('role', 'alert');
            pie.appendChild(error);
        }
        var seguir = el('button', 'boton-siguiente' + (estado.subiendo ? '' : ' vivo'), estado.subiendo ? textos.comprobando : textos.siguiente_cantidades);
        // Botón principal destacado: brillo que lo recorre, halo que respira y flecha que avanza (sin animación si el sistema la quita).
        if (!estado.subiendo) seguir.appendChild(el('span', 'flecha-viva', '→'));
        seguir.type = 'button';
        seguir.dataset.foco = 'seguir';
        // aria-disabled y no disabled: un botón desactivado pierde el foco del teclado.
        seguir.setAttribute('aria-disabled', String(estado.subiendo));
        pie.setAttribute('aria-busy', String(estado.subiendo));
        seguir.addEventListener('click', function () { irANivel('cantidades'); });
        seguir.setAttribute('aria-label', textos.siguiente_cantidades + '. ' + (usadas.length === 1
            ? texto('decision_uno', { zona: conDisenos[0].nombre })
            : texto('decision_varios', { n: usadas.length }) + ': ' + conDisenos.map(function (z) { return z.nombre; }).join(', ')));
        // Un diseño nuevo colocado: el botón se ilumina para invitar a seguir con tallas y colores.
        // (dura unos segundos aunque el panel se repinte entretanto).
        if (usadas.length > disenosVistos) invitaHasta = Date.now() + 3300;
        disenosVistos = usadas.length;
        if (Date.now() < invitaHasta) seguir.classList.add('invita');
        pie.appendChild(seguir);
        var volver = foco && (caja.querySelector('[data-foco="' + foco + '"]') || pie.querySelector('[data-foco="' + foco + '"]'));
        if (volver) volver.focus();
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

    /** El error queda escrito junto al archivo y, además, se avisa en el centro de la pantalla (D214). */
    function mostrarError(mensaje, soloTexto) {
        errorMarca.textContent = mensaje || '';
        errorMarca.hidden = !mensaje;
        if (mensaje && !soloTexto) avisarError(mensaje);
    }
    function avisarError(mensaje) {
        if (!window.AvisoTienda) return;
        window.AvisoTienda.mostrar(mensaje, { titulo: textos.aviso_archivo, boton: textos.aviso_otro, alPulsar: function () { entrada.click(); } });
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

    /**
     * ¿Declara el PNG su resolución? Lee el fragmento pHYs (D206), igual que el servidor: true si la declara
     * en ppp, false si no, null si no se sabe (el navegador no puede leerlo): entonces decide el servidor.
     */
    function pppDeclarados(archivo) {
        if (!archivo || !archivo.slice || !window.DataView) return Promise.resolve(null);
        var trozo = archivo.slice(0, 1024 * 1024);
        var leer = trozo.arrayBuffer ? trozo.arrayBuffer() : new Promise(function (ok, mal) {
            var lector = new FileReader();
            lector.onload = function () { ok(lector.result); };
            lector.onerror = mal;
            lector.readAsArrayBuffer(trozo);
        });
        return leer.then(function (buffer) {
            var v = new DataView(buffer), p = 8;
            while (p + 8 <= v.byteLength) {
                var largo = v.getUint32(p), tipo = String.fromCharCode(v.getUint8(p + 4), v.getUint8(p + 5), v.getUint8(p + 6), v.getUint8(p + 7));
                if (tipo === 'IDAT' || tipo === 'IEND') return false;
                // D237: los ppp que declara el archivo (pHYs en píxeles por metro); false si no declara ninguno.
                if (tipo === 'pHYs') return largo === 9 && p + 17 <= v.byteLength ? (v.getUint8(p + 16) === 1 && v.getUint32(p + 8) > 0 ? Math.round(v.getUint32(p + 8) * 0.0254) : false) : null;
                p += 12 + largo;
            }
            return null;
        }, function () { return null; });
    }

    /** @param original  datos del archivo original si llega reducido desde la portada */
    function leerLogo(archivo, original) {
        mostrarError('');
        if (!archivo || estado.subiendo) return; // mientras se sube, el logo no cambia (Gauntlet A1)
        // Solo PNG (D206): el servidor tampoco acepta otro formato. Sin tipo (algunos Android), por la extensión.
        if (!(archivo.type === 'image/png' || (!archivo.type && /\.png$/i.test(archivo.name)))) { mostrarError(textos.error_archivo); return; }
        if (archivo.size > PESO_MAXIMO) { mostrarError(textos.error_grande); return; }
        // D206: sin resolución declarada no se admite; se dice al elegirlo, no al pasar al pedido.
        var aRevisar = original ? (original.archivo || null) : archivo; // desde la portada, el original
        if (textos.exigir_ppp && aRevisar) {
            pppDeclarados(aRevisar).then(function (declara) {
                if (declara === false) { mostrarError(textos.error_sin_ppp); return; }
                cargarLogo(archivo, original, typeof declara === 'number' ? declara : null);
            });
            return;
        }
        pppDeclarados(aRevisar).then(function (declara) { cargarLogo(archivo, original, typeof declara === 'number' ? declara : null); });
    }

    function cargarLogo(archivo, original, pppArchivo) {
        abrirEstudio();
        mostrarCarga();

        var url = URL.createObjectURL(archivo);
        var img = new Image();
        img.onload = function () {
            if (estado.subiendo) { // empezó la subida mientras se leía (Gauntlet N1)
                URL.revokeObjectURL(url);
                ocultarCarga();
                mostrarError(textos.espera_subida, true);
                return;
            }
            var w = img.naturalWidth || 1000, h = img.naturalHeight || 1000;
            var escala = Math.min(1, LADO_MAXIMO / Math.max(w, h));
            var c = document.createElement('canvas');
            c.width = Math.max(1, Math.round(w * escala));
            c.height = Math.max(1, Math.round(h * escala));
            c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
            URL.revokeObjectURL(url);

            var esVectorial = archivo.type === 'image/svg+xml' || (original && original.tipo === 'image/svg+xml');
            var recortado = recortarMargenes(c);
            // Si llega reducido desde la portada, la calidad se mide con los píxeles del original.
            var factor = original && original.ancho ? original.ancho / w : 1;
            // Para el pedido hace falta el archivo original, no la copia reducida de la portada (fase 4).
            ponerLogo({
                lienzo: recortado,
                tipo: esVectorial ? 'vectorial' : tipoDeFondo(c),
                px: { w: Math.round(recortado.width / escala * factor), h: Math.round(recortado.height / escala * factor) },
                nombre: original ? original.nombre : archivo.name,
                archivo: original ? (original.archivo || null) : archivo,
                uuid: null,
                pppArchivo: pppArchivo || null
            });
        };
        img.onerror = function () { URL.revokeObjectURL(url); ocultarCarga(); mostrarError(textos.error_archivo); };
        img.src = url;
    }

    /** Coloca un logo ya preparado en la zona activa (desde un archivo o desde «Tus diseños»). */
    function ponerLogo(logo) {
        estado.logo = logo.lienzo;
        estado.logoTipo = logo.tipo;
        estado.logoPx = logo.px;
        estado.pppArchivo = logo.pppArchivo || null;
        estado.nombre = logo.nombre;
        estado.archivo = logo.archivo;
        estado.uuid = logo.uuid;
        estado.avisos = logo.avisos || null;
        estado.errorSubida = '';
        if (estado.zonaId !== null) delete estado.ajustes[estado.zonaId];
        estado.version++;
        if (estado.modo !== 'logo') pestanas[0].click(); else colocarDiseno();
        elegirHerramienta('diseno'); // colocado: en el mismo paso se ve dónde va y se puede cambiar
        sincronizar();
    }

    entrada.addEventListener('change', function () { leerLogo(entrada.files[0]); entrada.value = ''; });


    /* ---------- Tus diseños subidos (fase 4) ----------
       Los que este visitante ya subió: se colocan sin volver a subirlos (miniatura
       para verlo y los píxeles del servidor para la calidad) y se pueden quitar. */

    var biblioteca = document.getElementById('biblioteca');
    var listaBiblioteca = document.getElementById('biblioteca-lista');

    function peticionJson(url, metodo) {
        var token = document.querySelector('meta[name="csrf-token"]');
        return fetch(url, {
            method: metodo || 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' }
        }).then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); });
    }

    function cargarBiblioteca(despues) {
        if (!biblioteca || !visor.dataset.biblioteca) return;
        peticionJson(visor.dataset.biblioteca).then(function (datos) {
            pintarBiblioteca(datos.disenos || []);
            if (despues) despues();
        }, function () {});
    }

    /** Un diseño quitado del servidor deja de valer en las zonas que lo usaban: habrá que volver a elegir el archivo (Gauntlet A2). */
    function olvidarDiseno(uuid) {
        Object.keys(estado.disenos).forEach(function (id) {
            if (estado.disenos[id].uuid === uuid) { estado.disenos[id].uuid = null; estado.disenos[id].archivo = null; }
        });
        if (borrador.uuid === uuid) { borrador.uuid = null; borrador.archivo = null; }
        pintarCapas();
    }

    function pintarBiblioteca(disenos) {
        listaBiblioteca.replaceChildren();
        biblioteca.hidden = disenos.length === 0;
        disenos.forEach(function (d) {
            var fila = el('li', 'biblioteca-diseno');
            var usar = el('button', 'biblioteca-usar');
            usar.type = 'button';
            usar.setAttribute('aria-label', texto('biblioteca_usar', { nombre: d.nombre }));
            var img = el('img');
            img.src = d.miniatura; img.alt = ''; img.loading = 'lazy';
            usar.appendChild(img);
            usar.appendChild(el('span', '', d.nombre));
            usar.addEventListener('click', function () { usarDeBiblioteca(d); });
            var quitar = el('button', 'biblioteca-quitar', '×');
            quitar.type = 'button';
            quitar.setAttribute('aria-label', texto('biblioteca_quitar', { nombre: d.nombre }));
            quitar.addEventListener('click', function () {
                if (estado.subiendo || !window.confirm(texto('biblioteca_confirmar', { nombre: d.nombre }))) return;
                peticionJson(visor.dataset.borrar.replace('00000000-0000-0000-0000-000000000000', d.uuid), 'DELETE').then(function () {
                    olvidarDiseno(d.uuid);
                    cargarBiblioteca(function () {
                        // El foco va al siguiente diseño o, si no quedan, a «Elegir mi logo».
                        var siguiente = listaBiblioteca.querySelector('.biblioteca-usar');
                        if (siguiente) { siguiente.focus(); return; }
                        soltar.tabIndex = -1; // la zona «Elegir mi logo», visible (el campo de archivo está oculto)
                        soltar.focus();
                    });
                }, function () { mostrarError(textos.error_red); });
            });
            fila.appendChild(usar);
            fila.appendChild(quitar);
            listaBiblioteca.appendChild(fila);
        });
    }

    function usarDeBiblioteca(d) {
        if (estado.subiendo) return;
        mostrarError('');
        abrirEstudio();
        mostrarCarga();
        cargarFoto(d.miniatura).then(function (img) {
            var c = document.createElement('canvas');
            c.width = img.naturalWidth; c.height = img.naturalHeight;
            c.getContext('2d').drawImage(img, 0, 0);
            ponerLogo({ lienzo: c, tipo: d.fondo, px: { w: d.ancho_px, h: d.alto_px }, nombre: d.nombre, archivo: null, uuid: d.uuid, avisos: d.avisos || [] });
        }, function () { ocultarCarga(); mostrarError(textos.error_red); });
    }

    cargarBiblioteca();

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
            ['calidad-ppp', 'calidad-mensaje', 'calidad-consejo', 'calidad-fondo', 'calidad-objetivo', 'calidad-archivo'].forEach(function (id) { document.getElementById(id).textContent = ''; });
            document.getElementById('calidad-relleno').style.width = '0';
        }
        mostrarMejoraIa(null);
        avisoPixelado(null);
        botonNitido.hidden = true;
        if (!zona) { actualizarGuia(null); return; }

        var color = colores[estado.color];
        var g = geometriaMm(disenoPara(color), zona);
        var anchoMm = g.w, altoMm = g.h;
        tamano.value = Math.round(ajusteDe(zona.id).escala * 100);
        // D229: el tamaño real de impresión, grande, y el máximo de la zona.
        document.getElementById('medida-diseno').textContent = cm(anchoMm) + ' × ' + cm(altoMm) + ' cm';
        document.getElementById('medida-zona').textContent = texto('medida_zona', { medida: cm(zona.ancho_mm) + ' × ' + cm(zona.alto_mm) + ' cm' });

        var t = calidad.textos;
        var etiqueta = document.getElementById('calidad-etiqueta');
        var detalle = document.getElementById('calidad-ppp');
        var mensaje = document.getElementById('calidad-mensaje');
        var consejo = document.getElementById('calidad-consejo');
        var fondo = document.getElementById('calidad-fondo');
        fondo.textContent = ''; consejo.textContent = ''; detalle.textContent = '';
        var objetivo = document.getElementById('calidad-objetivo'), delArchivo = document.getElementById('calidad-archivo');
        objetivo.textContent = ''; delArchivo.textContent = '';

        if (estado.modo === 'texto' || estado.logoTipo === 'vectorial') {
            bloqueCalidad.dataset.nivel = 'correcta';
            etiqueta.textContent = t.nivel_correcta;
            mensaje.textContent = estado.modo === 'texto' ? t.texto : t.vectorial;
            document.getElementById('calidad-relleno').style.width = '100%';
            if (estado.logoTipo === 'vectorial' && estado.modo === 'logo') fondo.textContent = t.formato_pedido;
            actualizarGuia('correcta', anchoMm, altoMm, mensaje.textContent);
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
        // D237: el objetivo para esta zona (en cm y en píxeles a la calidad excelente) y lo que dice el archivo.
        var pxObjetivo = Math.ceil(anchoMm / 25.4 * calidad.umbrales.correcto) + ' × ' + Math.ceil(altoMm / 25.4 * calidad.umbrales.correcto) + ' px';
        var infoZona = zonas.filter(function (z) { return z.id === estado.zonaId; })[0];
        objetivo.textContent = texto('ficha_objetivo', { zona: infoZona ? infoZona.nombre : '', medida: cm(anchoMm) + ' × ' + cm(altoMm) + ' cm', px: pxObjetivo, ppp: calidad.umbrales.correcto });
        delArchivo.textContent = texto('ficha_archivo', { px: estado.logoPx.w + ' × ' + estado.logoPx.h + ' px' })
            + (estado.pppArchivo ? ' ' + texto('ficha_declara', { ppp: estado.pppArchivo, medida: cm(estado.logoPx.w / estado.pppArchivo * 25.4) + ' × ' + cm(estado.logoPx.h / estado.pppArchivo * 25.4) + ' cm' }) : '');
        mostrarMejoraIa(n, cm(anchoMm) + ' × ' + cm(altoMm) + ' cm (' + pxObjetivo + ', ' + calidad.umbrales.correcto + ' ppp)');
        // D229: aviso sobre la prenda y botón para dejarlo al mayor tamaño nítido.
        avisoPixelado(n);
        var nitida = escalaNitida(zona, disenoPara(color));
        if ((n === 'justa' || n === 'baja') && nitida !== null) {
            botonNitido.hidden = false;
            botonNitido.textContent = texto('ajustar_nitido', { medida: cm(nitida.anchoMm) + ' cm' });
        }
        if (estado.logoTipo === 'fondo') fondo.textContent = t.fondo;
        if (estado.logoTipo === 'fondo_blanco') fondo.textContent = t.fondo_blanco;
        actualizarGuia(n, anchoMm, altoMm, null, (n === 'justa' || n === 'baja') ? nitida : null);
    }

    /* D237: guía de impresión al lado de la prenda. Antes de subir, orienta (tamaño máximo de la zona y píxeles
       necesarios); con el diseño, compara sus píxeles con los necesarios y, si no llegan, lo dice claro. */
    function actualizarGuia(n, anchoMm, altoMm, mensajeFijo, nitida) {
        var guia = document.getElementById('guia-impresion');
        if (!guia) return;
        var zona = zonaActual();
        if (!zona && n === null) {
            // Antes de elegir zona: orienta con la primera de la cara que se ve.
            var datosCara = (colores[estado.color] || { caras: {} }).caras[estado.cara];
            zona = datosCara && datosCara.zonas.length ? datosCara.zonas[0] : null;
        }
        if (!zona) { guia.hidden = true; return; }
        var info = zonas.filter(function (z) { return z.id === zona.id; })[0];
        var u = calidad.umbrales.correcto, t = calidad.textos;
        if (n === null) { anchoMm = zona.ancho_mm; altoMm = zona.alto_mm; }
        var px = Math.ceil(anchoMm / 25.4 * u) + ' × ' + Math.ceil(altoMm / 25.4 * u) + ' px';
        var conPixeles = n !== null && estado.modo === 'logo' && estado.logoPx && estado.logoTipo !== 'vectorial';
        guia.hidden = false;
        guia.dataset.nivel = n || '';
        document.getElementById('guia-zona').textContent = info ? info.nombre : '';
        document.getElementById('guia-medida-etiqueta').textContent = n === null ? textos.guia_maximo_zona : textos.guia_se_imprime;
        document.getElementById('guia-medida').textContent = cm(anchoMm) + ' × ' + cm(altoMm) + ' cm';
        document.getElementById('guia-necesitas').textContent = px;
        document.getElementById('guia-tienes').textContent = n === null ? textos.guia_sin_subir : (conPixeles ? estado.logoPx.w + ' × ' + estado.logoPx.h + ' px' : '✓');
        document.getElementById('guia-estado').textContent = n === null ? '' : t['nivel_' + n];
        document.getElementById('guia-consejo').textContent = n === null ? texto('guia_antes', { px: px, ppp: u })
            : (mensajeFijo || (n === 'baja' ? texto('guia_pequeno', { px: px }) : (n === 'justa' ? texto('guia_justo', { px: px }) : t['mensaje_' + n])));
        document.getElementById('guia-corto').textContent = n === 'baja' ? textos.guia_pequeno_corto : (n === 'justa' ? textos.guia_justo_corto : '');
        var reduce = document.getElementById('guia-reduce');
        reduce.hidden = !nitida;
        if (nitida) document.getElementById('guia-ajustar').textContent = texto('ajustar_nitido', { medida: cm(nitida.anchoMm) + ' cm' });
    }
    var guiaBoton = document.getElementById('guia-ajustar');
    if (guiaBoton) guiaBoton.addEventListener('click', function () { dejarNitido(); });

    /* Mejorar el diseño con IA (D143): si la calidad no llega, las instrucciones del
       backend con su tamaño de impresión, para copiarlas en su propia herramienta. */
    var mejoraIa = document.getElementById('mejora-ia');
    function mostrarMejoraIa(n, medida) {
        if (!mejoraIa) return;
        var ia = calidad.ia || { niveles: [] };
        var ofrecer = !!n && ia.niveles.indexOf(n) !== -1;
        mejoraIa.hidden = !ofrecer;
        if (ofrecer) document.getElementById('texto-ia').value = ia.texto.split(':medida').join(medida);
    }
    if (mejoraIa) {
        var abrirIa = document.getElementById('abrir-ia');
        abrirIa.addEventListener('click', function () {
            var abierto = abrirIa.getAttribute('aria-expanded') === 'true';
            abrirIa.setAttribute('aria-expanded', String(!abierto));
            document.getElementById('panel-ia').hidden = abierto;
        });
        var copiarIa = document.getElementById('copiar-ia');
        copiarIa.addEventListener('click', function () {
            var campo = document.getElementById('texto-ia');
            var hecho = function () {
                copiarIa.textContent = textos.ia_copiado;
                setTimeout(function () { copiarIa.textContent = textos.ia_copiar; }, 2200);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(campo.value).then(hecho, function () { campo.select(); });
            } else {
                campo.select();
                try { if (document.execCommand('copy')) hecho(); } catch (e) {}
            }
        });
    }

    /* D229: el mayor tamaño al que el diseño sale con calidad excelente (null si ni al mínimo). */
    var botonNitido = document.getElementById('calidad-ajustar');
    var lienzoAviso = document.getElementById('aviso-pixelado');
    function escalaNitida(zona, diseno) {
        if (!estado.logoPx || estado.modo !== 'logo' || estado.logoTipo === 'vectorial') return null;
        var anchoMax = estado.logoPx.w * 25.4 / calidad.umbrales.correcto;
        var proporcion = diseno.width / diseno.height;
        var escala = proporcion > zona.ancho_mm / zona.alto_mm ? anchoMax / zona.ancho_mm : (anchoMax / proporcion) / zona.alto_mm;
        escala = Math.floor(Math.min(1, escala) * 100) / 100;
        if (escala < Number(tamano.min) / 100) return null;
        var aj = ajusteDe(zona.id), antes = aj.escala;
        aj.escala = escala;
        var g = geometriaMm(diseno, zona);
        aj.escala = antes;
        return { escala: escala, anchoMm: g.w };
    }
    function avisoPixelado(n) {
        if (!lienzoAviso) return;
        lienzoAviso.hidden = n !== 'baja' && n !== 'justa';
        lienzoAviso.className = 'aviso-pixelado' + (n === 'justa' ? ' justa' : '');
        lienzoAviso.textContent = n === 'baja' ? textos.pixelado_lienzo : (n === 'justa' ? textos.borroso_lienzo : '');
    }
    function dejarNitido(zonaId) {
        var id = zonaId === undefined ? estado.zonaId : zonaId;
        conDiseno(id, function () {
            var zona = zonaActual();
            var nitida = zona ? escalaNitida(zona, disenoPara(colores[estado.color])) : null;
            if (nitida) ajusteDe(zona.id).escala = nitida.escala;
        });
        actualizarAjustador();
        pintar(); refrescarColeccion();
    }
    if (botonNitido) botonNitido.addEventListener('click', function () { dejarNitido(); });

    /** Calidad de cada zona con diseño, para avisar antes de añadir al pedido (D229). */
    function zonasPixeladas() {
        return zonasConDiseno().map(function (id) {
            return conDiseno(id, function () {
                var zona = zonaActual();
                if (!zona || estado.modo !== 'logo' || !estado.logoPx || estado.logoTipo === 'vectorial') return null;
                var color = colores[estado.color], diseno = disenoPara(color), g = geometriaMm(diseno, zona);
                var valor = Math.min(ppp(estado.logoPx.w, g.w), ppp(estado.logoPx.h, g.h));
                if (nivel(valor) !== 'baja') return null;
                var info = zonas.filter(function (z) { return z.id === id; })[0];
                return { id: id, nombre: info ? info.nombre : '', medida: cm(g.w) + ' × ' + cm(g.h) + ' cm', ppp: valor, nitida: escalaNitida(zona, diseno) };
            });
        }).filter(Boolean);
    }
    var avisadoPixelado = '';

    /* D229: al pasar el ratón por un diseño, sus medidas de impresión y su calidad junto al cursor. */
    function medidasDe(id) {
        return conDiseno(id, function () {
            var zona = zonaActual();
            if (!zona) return null;
            var color = colores[estado.color], g = geometriaMm(disenoPara(color), zona);
            var info = zonas.filter(function (z) { return z.id === id; })[0];
            var texto = (info ? info.nombre + ' · ' : '') + cm(g.w) + ' × ' + cm(g.h) + ' cm';
            if (estado.modo !== 'logo' || !estado.logoPx || estado.logoTipo === 'vectorial') return { texto: texto, nivel: 'correcta' };
            var valor = Math.min(ppp(estado.logoPx.w, g.w), ppp(estado.logoPx.h, g.h)), n = nivel(valor);
            return { texto: texto + ' · ' + calidad.textos['nivel_' + n] + ' (' + calidad.textos.ppp.replace(':ppp', valor) + ')', nivel: n };
        });
    }
    var etiquetaMedidas = document.getElementById('medidas-cursor');
    if (etiquetaMedidas && conRaton) {
        lienzo.addEventListener('pointermove', function (e) {
            var c = e.target.closest && e.target.closest('canvas.movible');
            var m = c && e.pointerType === 'mouse' && !arrastre ? medidasDe(Number(c.dataset.zona)) : null;
            if (!m) { etiquetaMedidas.hidden = true; return; }
            var v = visor.getBoundingClientRect();
            etiquetaMedidas.textContent = m.texto;
            etiquetaMedidas.dataset.nivel = m.nivel;
            etiquetaMedidas.hidden = false;
            var x = Math.min(e.clientX - v.left + 16, v.width - etiquetaMedidas.offsetWidth - 8);
            etiquetaMedidas.style.left = Math.max(8, x) + 'px';
            etiquetaMedidas.style.top = Math.max(8, e.clientY - v.top - etiquetaMedidas.offsetHeight - 14) + 'px';
        });
        lienzo.addEventListener('pointerleave', function () { etiquetaMedidas.hidden = true; });
        lienzo.addEventListener('pointerdown', function () { etiquetaMedidas.hidden = true; });
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
            originalDeLaPortada().then(function (archivo) {
                // El mismo archivo: nombre, tamaño y fecha (dos pestañas con «logo.png» no se mezclan).
                guardado.archivo = archivo && archivo.name === guardado.nombre && archivo.size === guardado.tamano && archivo.lastModified === guardado.modificado ? archivo : null;
                return fetch(guardado.datos);
            }).then(function (r) { return r.blob(); }).then(function (b) {
                leerLogo(new File([b], guardado.nombre || 'logo.png', { type: 'image/png' }), guardado);
            });
        }
    } catch (e) { /* sin sessionStorage: se sube desde la ficha */ }

    /** Archivo original que la portada dejó en este navegador (IndexedDB); nunca sale de él hasta el pedido. */
    function originalDeLaPortada() {
        return new Promise(function (resolver) {
            try {
                var peticion = indexedDB.open('nga', 1);
                peticion.onupgradeneeded = function () { peticion.result.createObjectStore('archivos'); };
                peticion.onerror = function () { resolver(null); };
                peticion.onsuccess = function () {
                    var almacen = peticion.result.transaction('archivos', 'readwrite').objectStore('archivos');
                    var leer = almacen.get('logo');
                    leer.onsuccess = function () { almacen.delete('logo'); resolver(leer.result || null); };
                    leer.onerror = function () { resolver(null); };
                };
            } catch (e) { resolver(null); }
        });
    }

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
        if (typeof pintarAnadir === 'function') pintarAnadir();
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

    /* ---------- Subida de los logos al pasar al pedido (fase 4) ---------- */

    function pendientesDeSubir() {
        return zonasConDiseno().map(function (id) { return estado.disenos[id]; })
            .filter(function (d) { return d.modo === 'logo' && !d.uuid; });
    }

    function fallarSubida(mensaje) {
        estado.subiendo = false;
        estado.errorSubida = mensaje;
        pintarCapas();
        avisarError(mensaje);
        var aviso = document.querySelector('#capas-pie .error-subida');
        if (aviso && aviso.scrollIntoView) aviso.scrollIntoView({ block: 'nearest', behavior: sinMovimiento ? 'auto' : 'smooth' });
        return false;
    }

    /** Sube uno a uno los logos aún sin subir. El servidor decide: sus píxeles útiles sustituyen a los del navegador. */
    function subirDisenos() {
        var pendientes = pendientesDeSubir();
        for (var j = 0; j < pendientes.length; j++) {
            if (!pendientes[j].archivo) return Promise.resolve(fallarSubida(texto('error_original', { nombre: pendientes[j].nombre })));
        }
        for (var i = 0; i < pendientes.length; i++) {
            var d = pendientes[i];
            // Sin tipo (algunos selectores de Android) decide el servidor por la firma del archivo.
            if (d.archivo.type && d.archivo.type !== 'image/png') return Promise.resolve(fallarSubida(textos.error_no_png));
            if (d.archivo.size > Number(visor.dataset.maxBytes || Infinity)) return Promise.resolve(fallarSubida(d.nombre + ': ' + textos.error_pesado));
        }
        estado.subiendo = true;
        estado.errorSubida = '';
        pintarCapas();
        var token = document.querySelector('meta[name="csrf-token"]');

        return pendientes.reduce(function (cadena, d) {
            return cadena.then(function (seguir) {
                if (!seguir) return false;
                var enviado = d.archivo;
                var datos = new FormData();
                datos.append('archivo', d.archivo, d.archivo.name);
                return fetch(visor.dataset.subida, {
                    method: 'POST',
                    body: datos,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' }
                }).then(function (r) {
                    return r.json().catch(function () { return {}; }).then(function (json) {
                        if (r.ok && json.uuid) {
                            // Solo si la zona sigue teniendo el archivo que se envió (Gauntlet A1).
                            if (d.archivo === enviado) {
                                d.uuid = json.uuid;
                                d.logoPx = { w: json.ancho_px, h: json.alto_px };
                                d.avisos = json.avisos || [];
                            }
                            return true;
                        }
                        if (r.status === 419) return fallarSubida(textos.error_caducada);
                        if (r.status === 429) return fallarSubida(textos.error_muchas);
                        if (r.status === 413) return fallarSubida(d.nombre + ': ' + textos.error_pesado);
                        var mensaje = json.message;
                        if (json.errors) mensaje = json.errors[Object.keys(json.errors)[0]][0];
                        if (r.status >= 500 || !mensaje) {
                            // Fallo nuestro, no del archivo. En local (APP_DEBUG) se enseña la causa para arreglarla.
                            return fallarSubida(textos.error_servidor + (textos.depurar && json.message ? ' [' + r.status + ': ' + json.message + ']' : ''));
                        }
                        return fallarSubida(d.nombre + ': ' + mensaje);
                    });
                }, function () { return fallarSubida(textos.error_conexion); });
            });
        }, Promise.resolve(true)).then(function (ok) {
            if (ok) {
                estado.subiendo = false;
                pintarCapas();
                actualizarAjustador();
                cargarBiblioteca();
                pintarAvisosSubida();
            }
            // Si algo cambió mientras tanto, lo que quede pendiente impide seguir, con su motivo.
            if (ok && pendientesDeSubir().length) return fallarSubida(textos.error_cambiado);
            return ok;
        });
    }

    /** Avisos del servidor sobre los logos subidos (perfil de color, fondo), en el nivel 2 (Gauntlet M4). */
    function pintarAvisosSubida() {
        var caja = document.getElementById('avisos-subida');
        if (!caja) return;
        var avisos = [];
        zonasConDiseno().forEach(function (id) {
            var d = estado.disenos[id];
            (d.modo === 'logo' && d.avisos ? d.avisos : []).forEach(function (a) { avisos.push(d.nombre + ': ' + a); });
        });
        caja.replaceChildren();
        avisos.forEach(function (a) { caja.appendChild(el('p', '', a)); });
        caja.hidden = avisos.length === 0;
    }

    // Capa «Procesando» de la tienda (tienda/_procesando): oscurece y dice que estamos trabajando.
    function procesando(texto, detalle) { if (window.Procesando) window.Procesando.mostrar(texto, detalle); }
    function finProcesando() { if (window.Procesando) window.Procesando.ocultar(); }

    function irANivel(nombre) {
        if (nombre !== 'diseno' && zonasConDiseno().length && pendientesDeSubir().length) {
            if (estado.subiendo) return;
            // Subir y analizar los diseños tarda: el cliente lo ve («Preparando tu diseño…»).
            procesando(textos.procesando_diseno, textos.procesando_diseno_detalle);
            subirDisenos().then(function (ok) {
                finProcesando();
                if (ok) mostrarNivel(nombre);
            }, finProcesando);
            return;
        }
        mostrarNivel(nombre);
    }

    function mostrarNivel(nombre) {
        if (nombre !== 'diseno' && zonasConDiseno().length === 0) nombre = 'diseno';
        estado.nivel = nombre;
        var conFoco = document.activeElement;
        ['diseno', 'cantidades', 'resumen'].forEach(function (n) {
            var caja = document.getElementById('nivel-' + n);
            if (caja) caja.hidden = n !== nombre;
        });
        // Si el foco se ha quedado en un nivel oculto, pasa al título del nivel nuevo (WCAG 2.4.3).
        if (conFoco && conFoco !== document.body && !conFoco.offsetParent) {
            var titulo = document.querySelector('#nivel-' + nombre + ' .nivel-titulo');
            if (titulo) { titulo.tabIndex = -1; titulo.focus(); }
        }
        if (nombre === 'cantidades' && estado.lineas.length === 0) anadirLinea(estado.color);
        var panelEstudio = document.getElementById('estudio-panel');
        if (panelEstudio) panelEstudio.scrollTop = 0;
        pintarAvisosSubida();
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
            pieSiguiente.textContent = siguiente[1];
            pieSiguiente.appendChild(el('span', 'flecha-viva', '→'));
            pieSiguiente.classList.add('vivo');
            pieSiguiente.disabled = siguiente[0] === 'resumen' && !(precio && precio.confirmable);
        }
    }
    if (pieSiguiente) pieSiguiente.addEventListener('click', function () { irANivel(pieSiguiente.dataset.ir); });

    /* ---------- Añadir a mi pedido (paso 5.6) ---------- */

    /**
     * El diseño de una zona en mm (D99), con la misma geometría que se ve en el visor:
     * tamaño, borde de arriba bajo el cuello y centro desde el centro de la prenda
     * (negativo = a la izquierda de quien la lleva, que de frente es la derecha de la foto).
     * El servidor comprueba que queda dentro de la zona.
     */
    function impresionEnMm(id) {
        return conDiseno(id, function () {
            var color = colores[estado.color];
            var zona = zonaActual();
            var info = zonas.filter(function (z) { return z.id === id; })[0];
            if (!zona || !info) return null;
            var g = geometriaMm(disenoPara(color), zona);
            var desplazamiento = g.x + g.w / 2 - zona.ancho_mm / 2;
            var d = estado.disenos[id];
            var datos = {
                ancho_mm: Math.round(g.w),
                alto_mm: Math.round(g.h),
                desde_cuello_mm: Math.round((zona.cuello_mm || 0) + g.y),
                desde_centro_mm: Math.round((zona.centro_mm || 0) + (info.cara === 'delantera' ? -desplazamiento : desplazamiento))
            };
            if (d.modo === 'logo') { datos.diseno = d.uuid; } else { datos.texto = d.texto.trim(); datos.fuente = d.fuente; datos.tinta = d.tinta; }
            return datos;
        });
    }

    var botonAnadir = document.getElementById('anadir-pedido');
    var errorAnadir = document.getElementById('anadir-error');
    function pintarAnadir() {
        if (!botonAnadir) return;
        botonAnadir.disabled = !(precio && precio.confirmable) || estado.anadiendoPedido;
    }
    if (botonAnadir) botonAnadir.addEventListener('click', function () {
        // D229: si alguna zona se va a ver pixelada, se avisa una vez (con la opción de ajustarla); al volver a pulsar, sigue.
        var pixeladas = zonasPixeladas();
        var firma = pixeladas.map(function (z) { return z.id + ':' + z.medida; }).join('|');
        if (pixeladas.length && firma !== avisadoPixelado && window.AvisoTienda) {
            avisadoPixelado = firma;
            var z = pixeladas[0];
            window.AvisoTienda.mostrar(texto(z.nitida ? 'pixelado_aviso' : 'pixelado_aviso_sin_nitido', { zona: z.nombre, medida: z.medida, ppp: z.ppp, nitido: z.nitida ? cm(z.nitida.anchoMm) + ' cm' : '' }), {
                titulo: textos.pixelado_titulo,
                boton: z.nitida ? textos.pixelado_ajustar : null,
                alPulsar: z.nitida ? function () { dejarNitido(z.id); } : null
            });
            return;
        }
        var impresiones = {};
        zonasConDiseno().forEach(function (id) { var i = impresionEnMm(id); if (i) impresiones[id] = i; });
        var lineas = estado.lineas.map(function (i) {
            var id = colores[i].id, c = estado.cantidades[id] || {}, tallas = {};
            Object.keys(c).forEach(function (t) { if (c[t] > 0) tallas[t] = c[t]; });
            return { color: id, tallas: tallas };
        }).filter(function (l) { return Object.keys(l.tallas).length; });
        var token = document.querySelector('meta[name="csrf-token"]');
        estado.anadiendoPedido = true;
        errorAnadir.textContent = '';
        botonAnadir.textContent = textos.anadiendo;
        pintarAnadir();
        procesando(textos.procesando_pedido, textos.procesando_pedido_detalle); // sigue hasta que se abre el carrito
        fetch(visor.dataset.anadir, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' },
            body: JSON.stringify({ prenda: Number(visor.dataset.prenda), lineas: lineas, impresiones: impresiones })
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (json) {
                if (r.ok && json.url) { mostrarAnadido(json); return; }
                var mensaje = json.message;
                if (json.errors) mensaje = json.errors[Object.keys(json.errors)[0]][0];
                throw new Error(r.status === 419 ? textos.error_caducada : (r.status === 422 && mensaje ? mensaje : textos.error_anadir));
            });
        }).catch(function (e) {
            finProcesando();
            estado.anadiendoPedido = false;
            errorAnadir.textContent = e && e.message && e.message !== 'Failed to fetch' ? e.message : textos.error_anadir;
            botonAnadir.textContent = textos.anadir_pedido;
            pintarAnadir();
        });
    });

    /* ---------- D213: otra prenda con el mismo diseño ----------
       Tras añadir, una hoja ofrece otras prendas con el diseño ya puesto (vista previa en el navegador);
       al elegir una, la prenda nueva abre con los diseños colocados en las mismas zonas (?con=línea). */

    function leerJson(valor) { try { return JSON.parse(valor || '[]') || []; } catch (e) { return []; } }
    var otrasPrendas = leerJson(visor.dataset.otras);
    var llevados = leerJson(visor.dataset.llevar);
    var urlsDiseno = {};

    /** Zona de destino en otra prenda: la misma (clave); si no la tiene, la primera libre de la misma cara. */
    function zonaDestino(lista, clave, usadas) {
        return lista.filter(function (z) { return clave && z.clave === clave && !usadas[z.clave + z.x]; })[0]
            || lista.filter(function (z) { return !usadas[z.clave + z.x]; })[0] || null;
    }

    function tarjetaOtra(p, linea) {
        var conDiseno = zonasConDiseno().map(function (id) { return zonas.filter(function (z) { return z.id === id; })[0]; }).filter(Boolean);
        var cara = conDiseno.some(function (z) { return z.cara === 'delantera'; }) || !p.caras.trasera ? 'delantera' : 'trasera';
        var datos = p.caras[cara] || p.caras.delantera || p.caras.trasera;
        var li = el('li');
        var a = el('a', 'anadido-otra');
        a.href = p.url + (p.url.indexOf('?') === -1 ? '?' : '&') + 'con=' + encodeURIComponent(linea);
        var foto = el('span', 'anadido-foto');
        if (datos) {
            var img = el('img'); img.src = datos.url; img.alt = ''; img.loading = 'lazy';
            foto.appendChild(img);
            var usadas = {};
            conDiseno.filter(function (z) { return z.cara === cara; }).forEach(function (z) {
                var destino = zonaDestino(datos.zonas, z.rect.clave, usadas);
                var d = estado.disenos[z.id];
                if (!destino || !d || !d.logo) return;
                usadas[destino.clave + destino.x] = true;
                if (!urlsDiseno[z.id]) urlsDiseno[z.id] = d.logo.toDataURL('image/png');
                var capa = el('img', 'anadido-capa'); capa.src = urlsDiseno[z.id]; capa.alt = '';
                capa.style.cssText = 'left:' + destino.x + '%;top:' + destino.y + '%;width:' + destino.ancho + '%;height:' + destino.alto + '%';
                foto.appendChild(capa);
            });
        }
        a.appendChild(foto);
        a.appendChild(el('strong', '', p.nombre));
        if (p.desde) a.appendChild(el('small', '', textos.anadido_desde + ' ' + p.desde));
        li.appendChild(a);
        return li;
    }

    function mostrarAnadido(json) {
        finProcesando();
        var hoja = document.getElementById('anadido');
        if (!hoja) { window.location.href = json.url; return; }
        var unidades = estado.lineas.reduce(function (s, i) { return s + unidadesDe(colores[i].id); }, 0);
        var titulo = document.querySelector('.estudio-titulo');
        document.getElementById('anadido-resumen').textContent = (titulo ? titulo.textContent.trim() + ' · ' : '') + textoUds(unidades);
        var lista = document.getElementById('anadido-otras');
        lista.replaceChildren();
        if (json.linea) otrasPrendas.forEach(function (p) { lista.appendChild(tarjetaOtra(p, json.linea)); });
        document.getElementById('anadido-otras-bloque').hidden = lista.children.length === 0;
        document.getElementById('anadido-pedido').href = json.url;
        hoja.hidden = false;
        void hoja.offsetWidth;
        hoja.classList.add('visible');
        document.getElementById('anadido-titulo').tabIndex = -1;
        document.getElementById('anadido-titulo').focus();
    }

    /** Viene de otra prenda del pedido: coloca sus diseños en las mismas zonas, sin subirlos otra vez. */
    function colocarLlevados(lista) {
        var usadas = {};
        var cadena = Promise.resolve();
        lista.forEach(function (d) {
            var libres = zonas.filter(function (z) { return !usadas[z.id]; });
            var z = libres.filter(function (o) { return d.clave && o.rect.clave === d.clave; })[0]
                || libres.filter(function (o) { return o.cara === d.cara; })[0] || libres[0];
            if (!z) return;
            usadas[z.id] = true;
            cadena = cadena.then(function () {
                return cargarFoto(d.miniatura).then(function (img) {
                    fijarZona(z.id);
                    estado.zonaElegida = true;
                    var c = document.createElement('canvas');
                    c.width = img.naturalWidth; c.height = img.naturalHeight;
                    c.getContext('2d').drawImage(img, 0, 0);
                    ponerLogo({ lienzo: c, tipo: d.fondo, px: { w: d.ancho_px, h: d.alto_px }, nombre: d.nombre, archivo: null, uuid: d.uuid, avisos: d.avisos || [] });
                }, function () {});
            });
        });
        return cadena;
    }

    if (esPagina) {
        document.body.classList.add('estudio-abierto');
        refrescarColeccion();
    }

    pintar();
    if (llevados.length) colocarLlevados(llevados);
})();
