@php
    // D220: un solo lienzo con pasos (diseños, dónde va cada uno, nombre de la colección, color y tallas) y un resumen
    // fijo con el contador. Sin JavaScript sigue funcionando: subir el PNG y añadir son formularios normales.
    // D224: el pack admite hasta $max diseños; «Diseño 1, 2…» siguen el orden de la rejilla.
    $max = max(1, (int) $pack->max_disenos);
    $elegidos = collect(old('disenos', session('disenos_elegidos', array_filter([session('diseno_elegido', $disenos->first()?->uuid)]))))
        ->filter(fn ($u) => $disenos->contains('uuid', $u))->take($max)->values();
    $elegidos = $disenos->pluck('uuid')->filter(fn ($u) => $elegidos->contains($u))->values(); // en el orden de la rejilla
    $elegido = $elegidos->first();
    $paso = 0;
    $disenoUrl = $elegido ? route('disenos.miniatura', ['diseno' => $elegido]) : null;
@endphp
<x-tienda :titulo="$pack->nombre.' · Nextgenapparel'" :descripcion="$pack->descripcion ?: __('packs.entradilla')" :alternos="$alternos">
    <section class="pack-pagina" aria-labelledby="titulo-pack">
        <div class="contenedor">
            <header class="pack-cabecera">
                @if ($imagen)<img class="pack-cabecera-foto" src="{{ $imagen }}" alt="" width="200" height="240">@endif
                <div>
                    <p class="etiqueta">{{ __('packs.etiqueta') }}</p>
                    <h1 class="titulo-cuenta" id="titulo-pack">{{ $pack->nombre }}</h1>
                    @if ($neto ?? null)
                        {{-- D219: neto + IVA delante y, debajo, el precio final con IVA. --}}
                        <p class="pack-precio">{{ $neto }}<small>{{ __('portada.pack_mas_iva') }}</small></p>
                        <p class="pack-precio-final">{{ $precio }} {{ __('portada.pack_iva') }}</p>
                    @else
                        <p class="pack-precio">{{ $precio }}<small>{{ __('portada.pack_iva') }}</small></p>
                    @endif
                    @if ($pack->descripcion)<p class="claro">{{ $pack->descripcion }}</p>@endif
                    <ul class="pack-chips">
                        @foreach ($lineas as $l)<li>{{ $l['linea']->unidades }} × {{ $l['linea']->prenda->nombre }}</li>@endforeach
                        <li>{{ __('packs.impresion') }}: {{ $pack->zonas->pluck('nombre')->implode(', ') }}@if ($lineas->contains(fn ($l) => ! $l['todas'])) {{ __('packs.segun_prenda') }}@endif</li>
                    </ul>
                </div>
            </header>

            @if (session('estado'))<p class="aviso-cuenta correcto" role="status">{{ session('estado') }}</p>@endif
            @if ($errors->any())
                <div class="aviso-cuenta error" role="alert" tabindex="-1" id="resumen-errores"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                <script>document.getElementById('resumen-errores').focus();</script>
            @endif

            <form class="pack-lienzo" id="pack-anadir" method="POST" data-procesando="{{ __('tienda.procesando_pedido') }}" action="{{ route('packs.anadir', ['pack' => request()->route('pack')]) }}">
                @csrf
                <div class="pack-pasos">
                    {{-- 1. Diseños: elegir de los suyos (hasta los que admite el pack) o subir uno nuevo, en el mismo sitio. --}}
                    <section class="pack-paso" aria-labelledby="paso-diseno">
                        <h2 id="paso-diseno"><span class="pack-numero">{{ ++$paso }}</span>{{ $max > 1 ? __('packs.paso_disenos', ['n' => $max]) : __('packs.paso_diseno') }}</h2>
                        <p class="claro">{{ $max > 1 ? __('packs.paso_disenos_ayuda', ['n' => $max]) : __('packs.paso_diseno_ayuda') }}</p>
                        <div class="pack-disenos" @if ($max === 1) role="radiogroup" @endif aria-labelledby="paso-diseno" data-max="{{ $max }}">
                            <label class="pack-subir-tesela">
                                <input type="file" name="archivo" form="pack-subir" accept="image/png" aria-describedby="ayuda-png">
                                <span class="pack-mas" aria-hidden="true">+</span>
                                <span>{{ __('packs.subir') }}</span>
                                <small id="ayuda-png">{{ __('packs.subir_png', ['mb' => $maxMb]) }}</small>
                            </label>
                            @foreach ($disenos as $d)
                                <label class="pack-diseno" title="{{ $d->nombre_original }}">
                                    <input type="{{ $max === 1 ? 'radio' : 'checkbox' }}" name="disenos[]" value="{{ $d->uuid }}" @checked($elegidos->contains($d->uuid))>
                                    <img src="{{ route('disenos.miniatura', ['diseno' => $d->uuid]) }}" alt="{{ $d->nombre_original }}" width="96" height="96" loading="lazy">
                                    <span class="pack-check" aria-hidden="true">{{ $max > 1 ? ($elegidos->search($d->uuid) === false ? '' : $elegidos->search($d->uuid) + 1) : '✓' }}</span>
                                </label>
                            @endforeach
                        </div>
                        <noscript><button type="submit" form="pack-subir" class="boton">{{ __('packs.subir') }}</button></noscript>
                        @include('packs._maqueta', ['donde' => 'paso'])
                    </section>

                    {{-- 2. Dónde va cada diseño: por zona incluida, qué diseño (o ninguno). Se aplica a todo el pack (D224). --}}
                    <section class="pack-paso" aria-labelledby="paso-colocacion">
                        <h2 id="paso-colocacion"><span class="pack-numero">{{ ++$paso }}</span>{{ $max > 1 ? __('packs.paso_colocacion_varios') : __('packs.paso_colocacion') }}</h2>
                        <div class="pack-zonas">
                            @foreach ($pack->zonas as $zona)
                                @php $valor = (int) old('colocacion.'.$zona->id, 1); @endphp
                                @if ($max === 1)
                                    <label class="pack-zona">
                                        <input type="checkbox" name="colocacion[{{ $zona->id }}]" value="1" data-zona="{{ $zona->id }}" data-nombre="{{ $zona->nombre }}" @checked($valor === 1)>
                                        <span class="pack-zona-mini" aria-hidden="true"></span>
                                        <span>{{ $zona->nombre }}</span>
                                    </label>
                                @else
                                    <label class="pack-zona">
                                        <span class="pack-zona-mini" aria-hidden="true"></span>
                                        <span>{{ $zona->nombre }}</span>
                                        <select name="colocacion[{{ $zona->id }}]" data-zona="{{ $zona->id }}" data-nombre="{{ $zona->nombre }}" class="campo-pack">
                                            <option value="0" @selected($valor === 0)>{{ __('packs.sin_imprimir') }}</option>
                                            @foreach (range(1, $max) as $n)<option value="{{ $n }}" @selected($valor === $n)>{{ __('packs.diseno_n', ['n' => $n]) }}</option>@endforeach
                                        </select>
                                    </label>
                                @endif
                            @endforeach
                        </div>
                        <p class="pack-nota">{{ __('packs.colocacion_ayuda') }} {{ __('packs.colocacion') }}</p>
                    </section>

                    {{-- 2. Nombre de la colección (su marca): para reconocerla en el carrito, el pedido y al repetir. --}}
                    <section class="pack-paso" aria-labelledby="paso-coleccion">
                        <h2 id="paso-coleccion"><span class="pack-numero">{{ ++$paso }}</span>{{ __('packs.paso_coleccion') }} <small>{{ __('packs.opcional') }}</small></h2>
                        <label class="oculto" for="campo-coleccion">{{ __('packs.paso_coleccion') }}</label>
                        <input type="text" id="campo-coleccion" name="coleccion" value="{{ old('coleccion') }}" maxlength="60" placeholder="{{ __('packs.coleccion_ejemplo') }}" class="campo-pack" autocomplete="off" aria-describedby="ayuda-coleccion">
                        <p class="pack-nota" id="ayuda-coleccion">{{ __('packs.coleccion_ayuda') }}</p>
                    </section>

                    {{-- 3. Color y tallas de cada prenda del pack. --}}
                    <section class="pack-paso" aria-labelledby="paso-tallas">
                        <h2 id="paso-tallas"><span class="pack-numero">{{ ++$paso }}</span>{{ $libre ? __('packs.paso_tallas') : __('packs.paso_tallas_fijas') }}</h2>
                        @foreach ($lineas as $l)
                            @php
                                $id = $l['linea']->id;
                                $unidades = (int) $l['linea']->unidades;
                                $colorElegido = (int) old('elecciones.'.$id.'.color', $l['colores'][0]['id'] ?? 0);
                                $actual = collect($l['colores'])->firstWhere('id', $colorElegido) ?? ($l['colores'][0] ?? null);
                            @endphp
                            <fieldset class="pack-prenda" data-linea data-unidades="{{ $unidades }}">
                                <legend class="oculto">{{ $l['linea']->prenda->nombre }}</legend>
                                <div class="pack-prenda-cabecera">
                                    @if ($actual['foto'] ?? $l['foto'])<img src="{{ $actual['foto'] ?? $l['foto'] }}" alt="" width="84" height="100" data-foto-prenda>@endif
                                    <div>
                                        <h3>{{ $l['linea']->prenda->nombre }}</h3>
                                        <p class="claro">{{ trans_choice('packs.prendas', $unidades, ['n' => $unidades]) }} · <span data-nombre-color>{{ $actual['nombre'] ?? '' }}</span></p>
                                        @if ($l['zonas'])<p class="pack-zonas-prenda">{{ __('packs.impresion') }}: {{ $l['zonas'] }}</p>@endif
                                    </div>
                                </div>

                                @if ($libre)
                                    <p class="pack-subtitulo">{{ trans_choice('packs.color_elige', count($l['colores'])) }}</p>
                                    <div class="pack-colores">
                                        @foreach ($l['colores'] as $c)
                                            <label class="pack-color" title="{{ $c['nombre'] }}">
                                                <input type="radio" name="elecciones[{{ $id }}][color]" value="{{ $c['id'] }}" data-foto="{{ $c['foto'] }}" data-nombre="{{ $c['nombre'] }}" @checked($c['id'] === ($actual['id'] ?? null))>
                                                <span class="pack-muestra" style="background: {{ $c['hex'] }}" aria-hidden="true"></span>
                                                <span class="pack-color-nombre">{{ $c['nombre'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>

                                    <div class="pack-subtitulo-fila">
                                        <p class="pack-subtitulo">{{ __('packs.tallas_reparte', ['n' => $unidades]) }}</p>
                                        <button type="button" class="pack-enlace" data-repartir hidden>{{ __('packs.repartir_por_mi') }}</button>
                                    </div>
                                    <div class="pack-tallas">
                                        @foreach ($l['tallas'] as $t)
                                            <div class="pack-talla">
                                                <label for="talla-{{ $id }}-{{ $t['id'] }}">{{ $t['nombre'] }}</label>
                                                <div class="pack-contador">
                                                    <button type="button" data-menos aria-label="{{ __('packs.menos', ['talla' => $t['nombre']]) }}" hidden>−</button>
                                                    <input type="number" id="talla-{{ $id }}-{{ $t['id'] }}" name="elecciones[{{ $id }}][tallas][{{ $t['id'] }}]" value="{{ (int) old('elecciones.'.$id.'.tallas.'.$t['id'], 0) }}" min="0" max="{{ $unidades }}" inputmode="numeric">
                                                    <button type="button" data-mas aria-label="{{ __('packs.mas', ['talla' => $t['nombre']]) }}" hidden>+</button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="pack-progreso" aria-live="polite">
                                        <div class="pack-barra"><span data-barra></span></div>
                                        <p><strong data-suma>0</strong> / {{ $unidades }} · <span data-estado></span></p>
                                    </div>
                                @else
                                    <ul class="pack-fijas">
                                        @foreach ($l['tallas'] as $t)<li>{{ $t['nombre'] }} × {{ $t['fijas'] }}</li>@endforeach
                                    </ul>
                                @endif
                            </fieldset>
                        @endforeach
                    </section>
                </div>

                {{-- Resumen fijo: lo que falta, el contador total y el botón. --}}
                <aside class="pack-resumen" aria-label="{{ __('packs.resumen') }}">
                    @include('packs._maqueta', ['donde' => 'resumen'])
                    <p class="etiqueta">{{ __('packs.resumen') }}</p>
                    <h2>{{ $pack->nombre }}</h2>
                    <p class="pack-resumen-precio">{{ $neto ?? $precio }}<small>{{ ($neto ?? null) ? __('portada.pack_mas_iva') : __('portada.pack_iva') }}</small></p>
                    <ol class="pack-checklist">
                        <li data-check="diseno" @class(['hecho' => $elegido !== null])>{{ __('packs.check_diseno') }}</li>
                        <li data-check="coleccion">{{ __('packs.check_coleccion') }}</li>
                        <li data-check="tallas" @class(['hecho' => ! $libre])>{{ $libre ? __('packs.check_tallas') : __('packs.check_tallas_fijas') }}
                            @if ($libre)<span class="pack-total"><span data-total>0</span> / {{ $lineas->sum(fn ($l) => $l['linea']->unidades) }}</span>@endif</li>
                    </ol>
                    <button type="submit" class="boton principal">{{ __('tienda.anadir_pedido') }}</button>
                    <p class="pack-falta" data-falta aria-live="polite"></p>
                    <button type="button" class="pack-ia" data-preguntar="{{ __('packs.ia_pregunta', ['n' => $lineas->sum(fn ($l) => $l['linea']->unidades), 'prenda' => $lineas->first()['linea']->prenda->nombre]) }}" hidden>{{ __('packs.ia_boton') }}</button>
                    <p class="claro pack-nota">{{ __('packs.sin_cupon') }}</p>
                </aside>
            </form>

            {{-- Subir un PNG: formulario aparte (el campo está en el paso 1 con form="pack-subir"); vuelve con él elegido. --}}
            <form id="pack-subir" method="POST" action="{{ route('packs.subir', ['pack' => request()->route('pack')]) }}" enctype="multipart/form-data"
                  data-procesando="{{ __('tienda.procesando_diseno') }}" data-procesando-detalle="{{ __('tienda.procesando_diseno_detalle') }}">@csrf</form>
        </div>
    </section>

    <script>
    (() => {
        const t = @js(['falta' => __('packs.falta'), 'sobra' => __('packs.sobra'), 'listo' => __('packs.listo'), 'sin_diseno' => __('packs.falta_diseno'), 'faltan_tallas' => __('packs.falta_tallas'),
            'sin_zona' => __('packs.ninguna_zona'), 'falta_n' => __('packs.falta_diseno_n', ['n' => ':n', 'zona' => ':zona']), 'max' => __('packs.max_disenos', ['n' => max(1, (int) $pack->max_disenos)])]);
        const formulario = document.getElementById('pack-anadir');
        const lineas = [...formulario.querySelectorAll('[data-linea]')];
        const falta = formulario.querySelector('[data-falta]');
        formulario.querySelectorAll('[data-menos],[data-mas],[data-repartir]').forEach((b) => { b.hidden = false; });

        // Subir: al elegir el archivo se envía solo (con la capa «Procesando»).
        const archivo = formulario.querySelector('input[name=archivo]');
        archivo?.addEventListener('change', () => {
            if (!archivo.files.length) return;
            // D224: al volver de subir, siguen marcados los que ya tenía elegidos.
            const subir = document.getElementById('pack-subir');
            subir.querySelectorAll('input[name="mantener[]"]').forEach((i) => i.remove());
            elegidos().forEach((d) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'mantener[]'; i.value = d.uuid; subir.append(i); });
            subir.requestSubmit();
        });

        // D224: diseños elegidos (Diseño 1, 2… en el orden de la rejilla) y qué diseño lleva cada zona.
        const rejilla = formulario.querySelector('.pack-disenos'), maximo = +rejilla.dataset.max;
        const elegidos = () => [...formulario.querySelectorAll('input[name="disenos[]"]:checked')].map((i) => ({ uuid: i.value, src: i.parentElement.querySelector('img').src, input: i }));
        const zonas = () => [...formulario.querySelectorAll('[data-zona]')].map((c) => ({ id: c.dataset.zona, n: c.type === 'checkbox' ? (c.checked ? 1 : 0) : +c.value, campo: c }));
        const disenoDeZona = (id) => { const z = zonas().find((x) => x.id === String(id)); return z && z.n ? (elegidos()[z.n - 1]?.src ?? null) : null; };
        const numerar = () => {
            const lista = elegidos();
            if (maximo > 1) formulario.querySelectorAll('.pack-diseno').forEach((l) => {
                const i = lista.findIndex((d) => d.input === l.querySelector('input'));
                l.querySelector('.pack-check').textContent = i < 0 ? '' : i + 1;
            });
            zonas().forEach((z) => {
                const mini = z.campo.closest('.pack-zona').querySelector('.pack-zona-mini'), src = z.n ? lista[z.n - 1]?.src : null;
                mini.style.backgroundImage = src ? `url("${src}")` : '';
                mini.classList.toggle('vacia', !src);
            });
        };
        rejilla.addEventListener('change', (e) => {
            if (maximo > 1 && e.target.checked && elegidos().length > maximo) {
                e.target.checked = false;
                window.AvisoTienda?.mostrar(t.max);
            }
        });

        const estadoLinea = (l) => {
            const objetivo = +l.dataset.unidades;
            const suma = [...l.querySelectorAll('input[type=number]')].reduce((s, i) => s + Math.max(0, parseInt(i.value, 10) || 0), 0);
            l.querySelector('[data-suma]').textContent = suma;
            l.querySelector('[data-barra]').style.width = Math.min(100, suma / objetivo * 100) + '%';
            const resto = objetivo - suma;
            l.querySelector('[data-estado]').textContent = resto === 0 ? t.listo : (resto > 0 ? t.falta : t.sobra).replace(':n', Math.abs(resto));
            l.classList.toggle('completo', resto === 0);
            l.classList.toggle('sobra', resto < 0);
            return { suma, resto };
        };
        const actualizar = () => {
            let total = 0, pendientes = 0;
            lineas.forEach((l) => { const e = estadoLinea(l); total += e.suma; pendientes += e.resto !== 0 ? 1 : 0; });
            const totalNodo = formulario.querySelector('[data-total]');
            if (totalNodo) totalNodo.textContent = total;
            numerar();
            const lista = elegidos(), puestas = zonas().filter((z) => z.n > 0), huerfana = puestas.find((z) => !lista[z.n - 1]);
            const problemaDiseno = !lista.length ? t.sin_diseno : (!puestas.length ? t.sin_zona
                : (huerfana ? t.falta_n.replace(':n', huerfana.n).replace(':zona', huerfana.campo.dataset.nombre) : ''));
            const diseno = !problemaDiseno;
            formulario.querySelector('[data-check=diseno]').classList.toggle('hecho', diseno);
            if (lineas.length) formulario.querySelector('[data-check=tallas]').classList.toggle('hecho', pendientes === 0);
            formulario.querySelector('[data-check=coleccion]').classList.toggle('hecho', formulario.coleccion.value.trim() !== '');
            const mensaje = problemaDiseno || (pendientes ? t.faltan_tallas : '');
            falta.textContent = mensaje;
            return mensaje;
        };

        lineas.forEach((l) => {
            const campos = [...l.querySelectorAll('input[type=number]')];
            l.addEventListener('click', (e) => {
                const b = e.target.closest('[data-menos],[data-mas]');
                if (!b) return;
                const campo = b.parentElement.querySelector('input');
                campo.value = Math.max(0, (parseInt(campo.value, 10) || 0) + (b.hasAttribute('data-mas') ? 1 : -1));
                actualizar();
            });
            // Repartir automáticamente: más en las tallas centrales (curva normal), que suma justo las prendas del pack.
            l.querySelector('[data-repartir]')?.addEventListener('click', () => {
                const n = campos.length, objetivo = +l.dataset.unidades, centro = (n - 1) / 2, sigma = Math.max(1, n / 3.2);
                const pesos = campos.map((_, i) => Math.exp(-((i - centro) ** 2) / (2 * sigma * sigma)));
                const suma = pesos.reduce((a, b) => a + b, 0);
                const exactos = pesos.map((p) => p / suma * objetivo), base = exactos.map(Math.floor);
                let quedan = objetivo - base.reduce((a, b) => a + b, 0);
                exactos.map((x, i) => [x - base[i], i]).sort((a, b) => b[0] - a[0]).forEach(([, i]) => { if (quedan-- > 0) base[i]++; });
                campos.forEach((c, i) => { c.value = base[i]; });
                actualizar();
            });
            // Color: cambia la foto y el nombre.
            l.querySelectorAll('input[type=radio]').forEach((r) => r.addEventListener('change', () => {
                const foto = l.querySelector('[data-foto-prenda]');
                if (foto && r.dataset.foto) foto.src = r.dataset.foto;
                l.querySelector('[data-nombre-color]').textContent = r.dataset.nombre;
            }));
        });
        formulario.addEventListener('input', actualizar);
        formulario.addEventListener('change', actualizar);
        formulario.addEventListener('submit', (e) => {
            const mensaje = actualizar();
            if (!mensaje) return;
            e.preventDefault();
            const destino = !elegidos().length ? document.getElementById('paso-diseno')
                : (formulario.querySelector('[data-check=diseno]').classList.contains('hecho') ? lineas.find((l) => !l.classList.contains('completo')) : document.getElementById('paso-colocacion'));
            destino?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            window.AvisoTienda?.mostrar(mensaje);
        });
        actualizar();

        // D222: maqueta viva. Redibuja las prendas con el diseño elegido y el color de cada prenda.
        const maquetas = @js($lineas->mapWithKeys(fn ($l) => [$l['linea']->id => ['maqueta' => $l['maqueta'], 'color' => $l['colores'][0]['id'] ?? null, 'nombre' => $l['linea']->prenda->nombre]]));
        const notas = @js(['con' => __('packs.maqueta_nota'), 'sin' => __('packs.maqueta_sin_diseno')]);
        const dibujar = (nuevo) => {
            const diseno = elegidos().length > 0 && zonas().some((z) => z.n > 0 && elegidos()[z.n - 1]);
            const figuras = Object.entries(maquetas).flatMap(([id, l]) => {
                const marcado = formulario.querySelector(`input[name="elecciones[${id}][color]"]:checked`);
                const caras = l.maqueta[marcado ? marcado.value : (l.color ?? Object.keys(l.maqueta)[0])] || [];
                return caras.map((c) => {
                    const f = document.createElement('figure'); f.className = 'pack-maqueta-cara';
                    const foto = new Image(); foto.src = c.url; foto.alt = l.nombre; f.append(foto);
                    if (diseno) c.zonas.forEach((z) => {
                        const src = disenoDeZona(z.id);
                        if (!src) return;
                        const zona = document.createElement('span'); zona.className = 'pack-maqueta-zona' + (nuevo ? ' revela' : '');
                        Object.assign(zona.style, { left: z.x + '%', top: z.y + '%', width: z.ancho + '%', height: z.alto + '%' });
                        const img = new Image(); img.src = src; img.alt = ''; zona.append(img); f.append(zona);
                    });
                    return f;
                });
            });
            document.querySelectorAll('[data-maqueta]').forEach((m) => {
                m.querySelector('[data-maqueta-prendas]').replaceChildren(...figuras.map((f) => f.cloneNode(true)));
                m.querySelector('[data-maqueta-nota]').textContent = diseno ? notas.con : notas.sin;
                m.classList.toggle('con-diseno', !!diseno);
                m.classList.toggle('vacia', !figuras.length); // ese color aún no tiene foto: no se deja un hueco
            });
        };
        formulario.addEventListener('change', (e) => {
            if (e.target.name === 'disenos[]' || e.target.hasAttribute('data-zona')) dibujar(true);
            else if (/^elecciones\[\d+\]\[color\]$/.test(e.target.name)) dibujar(false);
        });
        dibujar(false);

        // IA (si el asistente está disponible para este cliente): abre el chat con una pregunta sobre las tallas.
        const ia = formulario.querySelector('[data-preguntar]'), asistente = document.getElementById('asistente');
        if (ia && asistente) {
            ia.hidden = false;
            ia.addEventListener('click', () => {
                asistente.querySelector('.asistente-panel').hidden && asistente.querySelector('.asistente-abrir').click();
                const campo = asistente.querySelector('input[name=texto]');
                campo.value = ia.dataset.preguntar;
                campo.focus();
            });
        }
    })();
    </script>
</x-tienda>
