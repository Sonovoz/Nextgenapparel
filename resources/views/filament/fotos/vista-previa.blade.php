@php
    /** @var \App\Models\Foto|null $foto */
    $foto = $getRecord();
    $filas = collect(is_array($zonasFormulario ?? null) ? $zonasFormulario : [])->filter(fn ($z) => is_array($z));
    $nombres = \App\Models\ZonaImpresion::whereIn('id', $filas->pluck('zona_impresion_id')->filter(fn ($id) => is_numeric($id)))->get()->keyBy('id');
    // Valores del formulario (en % de la foto), aunque aún no se hayan guardado. La clave es la de la fila del formulario.
    $zonas = $filas->map(function ($z, $clave) use ($nombres) {
        $zona = is_numeric($z['zona_impresion_id'] ?? null) ? ($nombres[(int) $z['zona_impresion_id']] ?? null) : null;

        return [
            'clave' => (string) $clave,
            'x' => is_numeric($z['x'] ?? null) ? (float) $z['x'] : 0, 'y' => is_numeric($z['y'] ?? null) ? (float) $z['y'] : 0,
            'ancho' => is_numeric($z['ancho'] ?? null) ? (float) $z['ancho'] : 0, 'alto' => is_numeric($z['alto'] ?? null) ? (float) $z['alto'] : 0,
            'nombre' => $zona?->nombre ?? 'Zona',
            'ancho_mm' => (int) ($zona?->ancho_max_mm ?? 0), 'alto_mm' => (int) ($zona?->alto_max_mm ?? 0),
        ];
    })->values();
    $ejemplo = $ejemplo ?? 'cuadrado';
    // Ruta de las zonas en el formulario: la de esta vista previa cambiando su nombre por «zonas».
    $rutaZonas = preg_replace('/vista_previa$/', 'zonas', $getStatePath());
@endphp

@if ($foto)
    {{-- Editor visual de zonas: arrastrar para mover, esquina para cambiar el tamaño (con la proporción real de la zona),
         línea de centro que atrae, medidas en cm y flechas del teclado. Rellena las casillas de abajo al soltar. --}}
    <div wire:key="zonas-{{ md5(json_encode($zonas)) }}"
         x-data="{
            zonas: @js($zonas),
            ruta: @js($rutaZonas),
            activa: 0,
            arrastre: null,
            iman: false,
            r(v) { return Math.round(v * 10) / 10 },
            limitar(z) {
                z.ancho = Math.max(2, Math.min(100, z.ancho));
                if (z.ancho_mm > 0 && z.alto_mm > 0) z.alto = z.ancho * 5 * z.alto_mm / (z.ancho_mm * 6);
                if (z.alto > 100) { z.alto = 100; z.ancho = z.alto * 6 * z.ancho_mm / (z.alto_mm * 5); }
                z.x = Math.max(0, Math.min(100 - z.ancho, z.x));
                z.y = Math.max(0, Math.min(100 - z.alto, z.y));
                // Imán al centro de la prenda: si el centro de la zona queda a menos de 1,5 % del eje, se centra.
                this.iman = Math.abs(z.x + z.ancho / 2 - 50) < 1.5;
                if (this.iman) z.x = 50 - z.ancho / 2;
            },
            empezar(e, i, modo) {
                this.activa = i;
                const caja = this.$refs.foto.getBoundingClientRect(), z = this.zonas[i];
                this.arrastre = { i, modo, x: e.clientX, y: e.clientY, caja, inicio: { ...z } };
                e.target.setPointerCapture?.(e.pointerId);
            },
            mover(e) {
                const a = this.arrastre; if (!a) return;
                const dx = (e.clientX - a.x) / a.caja.width * 100, dy = (e.clientY - a.y) / a.caja.height * 100, z = this.zonas[a.i];
                if (a.modo === 'mover') { z.x = a.inicio.x + dx; z.y = a.inicio.y + dy; z.ancho = a.inicio.ancho; z.alto = a.inicio.alto; }
                else { z.x = a.inicio.x; z.y = a.inicio.y; z.ancho = a.inicio.ancho + dx; }
                this.limitar(z);
            },
            soltar() { if (!this.arrastre) return; const i = this.arrastre.i; this.arrastre = null; this.guardar(i); },
            teclas(e) {
                const z = this.zonas[this.activa]; if (!z) return;
                const paso = e.shiftKey ? 1 : 0.1;
                const mapa = { ArrowLeft: [-paso, 0], ArrowRight: [paso, 0], ArrowUp: [0, -paso], ArrowDown: [0, paso] };
                if (!mapa[e.key]) return;
                e.preventDefault(); z.x += mapa[e.key][0]; z.y += mapa[e.key][1]; this.limitar(z);
                clearTimeout(this.espera); this.espera = setTimeout(() => this.guardar(this.activa), 500);
            },
            centrar(i) { const z = this.zonas[i]; z.x = 50 - z.ancho / 2; this.limitar(z); this.guardar(i); },
            guardar(i) {
                const z = this.zonas[i], base = this.ruta + '.' + z.clave + '.';
                this.$wire.$set(base + 'x', this.r(z.x), false);
                this.$wire.$set(base + 'ancho', this.r(z.ancho), false);
                this.$wire.$set(base + 'alto', this.r(z.alto), false);
                this.$wire.$set(base + 'y', this.r(z.y), true);
            },
            cm(z) { return z.ancho_mm ? (z.ancho_mm / 10).toLocaleString('es-ES') + ' × ' + (z.alto_mm / 10).toLocaleString('es-ES') + ' cm' : '' },
         }"
         @pointermove.window="mover($event)" @pointerup.window="soltar()" @keydown="teclas($event)"
         style="display: flex; flex-wrap: wrap; gap: 1rem 1.5rem; align-items: flex-start;">
        <div style="flex: 1 1 360px; max-width: 520px;">
            <p style="font-size: .875rem; font-weight: 500; margin-bottom: .35rem;">Coloca las zonas sobre la foto</p>
            <p style="font-size: .75rem; opacity: .7; margin: 0 0 .6rem;">Arrastra para mover · esquina para el tamaño · flechas para afinar (Mayús: más rápido). La línea azul es el centro de la prenda.</p>
            <div x-ref="foto" tabindex="0" style="position: relative; width: 100%; aspect-ratio: 5 / 6; background: #F3F2EF; border-radius: .5rem; overflow: hidden; touch-action: none; outline: none;">
                <img src="{{ $foto->url() }}" alt="" draggable="false" style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; user-select: none; pointer-events: none;">
                {{-- Guías: centro de la prenda (más fuerte cuando una zona queda centrada) y tercios. --}}
                <div aria-hidden="true" style="position: absolute; top: 0; bottom: 0; left: 50%; width: 0; border-left: 1px dashed #2D4BFF; transition: opacity .15s;" :style="{ opacity: iman ? 1 : .45, borderLeftStyle: iman ? 'solid' : 'dashed' }"></div>
                <div aria-hidden="true" style="position: absolute; left: 0; right: 0; top: 33.33%; border-top: 1px dotted rgba(45, 75, 255, .25);"></div>
                <div aria-hidden="true" style="position: absolute; left: 0; right: 0; top: 66.66%; border-top: 1px dotted rgba(45, 75, 255, .25);"></div>

                <template x-for="(z, i) in zonas" :key="z.clave">
                    <div @pointerdown.prevent="empezar($event, i, 'mover')"
                         :style="{ left: z.x + '%', top: z.y + '%', width: z.ancho + '%', height: z.alto + '%', zIndex: i === activa ? 3 : 2,
                                   outline: i === activa ? '2px solid #E11D48' : '1px dashed #E11D48', background: i === activa ? 'rgba(225, 29, 72, .06)' : 'transparent' }"
                         style="position: absolute; cursor: move;">
                        {{-- Mismo criterio que el visor de la tienda: encajado, centrado y arriba (D99). --}}
                        <svg data-ejemplo="{{ $ejemplo }}" style="position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none;" preserveAspectRatio="xMidYMin meet"
                             @switch($ejemplo)
                                 @case('horizontal') viewBox="0 0 300 70" @break
                                 @case('vertical') viewBox="0 0 100 140" @break
                                 @default viewBox="0 0 100 100"
                             @endswitch
                             aria-hidden="true">
                            @switch($ejemplo)
                                @case('horizontal')
                                    <rect x="0" y="0" width="300" height="70" rx="6" fill="#1E3A8A" opacity=".85"/>
                                    <text x="150" y="47" text-anchor="middle" font-family="Arial, sans-serif" font-weight="700" font-size="38" fill="#F97316">TU MARCA</text>
                                    @break
                                @case('vertical')
                                    <rect x="0" y="0" width="100" height="140" rx="4" fill="#1E3A8A" opacity=".85"/>
                                    <circle cx="50" cy="48" r="28" fill="#F97316"/>
                                    <text x="50" y="110" text-anchor="middle" font-family="Arial, sans-serif" font-weight="700" font-size="17" fill="#FFFFFF">TU MARCA</text>
                                    @break
                                @default
                                    <circle cx="50" cy="50" r="46" fill="#1E3A8A" opacity=".85"/>
                                    <circle cx="50" cy="50" r="30" fill="#F97316"/>
                                    <text x="50" y="57" text-anchor="middle" font-family="Arial, sans-serif" font-weight="700" font-size="18" fill="#FFFFFF">TU</text>
                            @endswitch
                        </svg>
                        <span style="position: absolute; left: 0; top: -1.15rem; font-size: .65rem; line-height: 1; padding: 2px 5px; border-radius: 3px; background: #E11D48; color: #fff; white-space: nowrap; pointer-events: none;"
                              x-text="z.nombre + (cm(z) ? ' · ' + cm(z) : '')"></span>
                        {{-- Esquina para cambiar el tamaño (el alto sigue la proporción real de la zona). --}}
                        <span @pointerdown.prevent.stop="empezar($event, i, 'tamano')" title="Arrastra para cambiar el tamaño"
                              style="position: absolute; right: -7px; bottom: -7px; width: 14px; height: 14px; border-radius: 3px; background: #fff; border: 2px solid #E11D48; cursor: nwse-resize; touch-action: none;"></span>
                    </div>
                </template>
            </div>
        </div>

        <div style="flex: 1 1 200px; font-size: .8rem;">
            <template x-for="(z, i) in zonas" :key="'dato-' + z.clave">
                <div @click="activa = i; $refs.foto.focus()" :style="{ borderColor: i === activa ? '#E11D48' : 'rgba(127,127,127,.25)' }"
                     style="border: 1px solid; border-radius: .5rem; padding: .5rem .65rem; margin-bottom: .5rem; cursor: pointer;">
                    <p style="margin: 0 0 .25rem; font-weight: 600;" x-text="z.nombre"></p>
                    <p style="margin: 0; opacity: .75;" x-text="'Izquierda ' + r(z.x) + ' % · arriba ' + r(z.y) + ' %'"></p>
                    <p style="margin: 0 0 .35rem; opacity: .75;" x-text="'Tamaño ' + r(z.ancho) + ' × ' + r(z.alto) + ' %' + (cm(z) ? ' (' + cm(z) + ')' : '')"></p>
                    <button type="button" @click.stop="centrar(i)" style="font-size: .75rem; padding: .2rem .55rem; border-radius: .4rem; border: 1px solid rgba(127,127,127,.4); background: transparent; cursor: pointer;">Centrar en la prenda</button>
                </div>
            </template>
            <p style="opacity: .65; margin: .25rem 0 0;">Al soltar, las casillas de abajo se rellenan solas. Pulsa «Guardar» para que se vea en la tienda.</p>
        </div>
    </div>
@endif
