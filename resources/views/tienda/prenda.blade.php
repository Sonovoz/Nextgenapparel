@php
    use App\Dominio\Catalogo\Escaparate;
    $idioma = app()->getLocale();
    $numero = fn (int $mm) => rtrim(rtrim(number_format($mm / 10, 1, $idioma === 'en' ? '.' : ',', ''), '0'), ',.');
    $cm = fn (?int $mm) => $mm === null ? '—' : $numero($mm).' cm';
    $primerColor = $colores[0] ?? null;
    $fotoInicial = $primerColor['caras']['delantera'] ?? $primerColor['caras']['trasera'] ?? null;
@endphp

@push('estilos')
    <style>
        .ficha { display: flex; flex-wrap: wrap; gap: 48px 64px; align-items: flex-start; }
        .galeria { flex: 1 1 520px; min-width: 0; }
        .visor { position: relative; background: var(--fondo-suave); border-radius: 4px; aspect-ratio: 5 / 6; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .visor-lienzo { position: relative; width: 86%; }
        .visor-lienzo img { width: 100%; height: auto; }
        .zona-marca { position: absolute; border: 2px dashed currentColor; background: color-mix(in srgb, currentColor 8%, transparent); }
        .zona-marca span { position: absolute; left: 0; top: -20px; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; white-space: nowrap; }
        .visor-controles { position: absolute; top: 16px; left: 16px; right: 16px; display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
        .segmento { display: flex; background: #fff; border-radius: 999px; padding: 4px; gap: 2px; }
        .boton-pildora { min-height: 36px; padding: 0 16px; border: 0; border-radius: 999px; cursor: pointer; font: inherit; font-size: 13px; background: transparent; color: var(--tinta); }
        .boton-pildora[aria-pressed="true"] { background: var(--tinta); color: #fff; }
        .boton-borde { min-height: 44px; padding: 0 16px; border-radius: 999px; border: 1px solid var(--tinta); background: #fff; cursor: pointer; font: inherit; font-size: 13px; }
        .boton-borde[aria-pressed="true"] { background: var(--tinta); color: #fff; }
        .visor-nota { position: absolute; left: 16px; bottom: 12px; margin: 0; font-size: 12px; color: var(--tinta-suave); }
        .info { flex: 1 1 360px; max-width: 480px; min-width: 0; display: flex; flex-direction: column; gap: 28px; }
        .info h1 { margin: 0; font-size: clamp(34px, 4vw, 44px); line-height: 1.1; font-weight: 300; }
        .precio { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; margin-top: 8px; }
        .precio strong { font-family: var(--serif); font-weight: 400; font-size: 32px; }
        .suave { color: var(--tinta-suave); font-size: 14px; margin: 0; }
        .muestras { display: flex; flex-wrap: wrap; gap: 6px; }
        .muestra { width: 44px; height: 44px; border-radius: 999px; border: 1px solid transparent; background: transparent; padding: 0; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .muestra[aria-checked="true"] { border-color: var(--tinta); }
        .muestra span { width: 32px; height: 32px; border-radius: 999px; box-shadow: inset 0 0 0 1px rgba(22,22,26,.14); }
        .chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .chip { min-width: 52px; height: 40px; border: 1px solid #DAD7D0; display: flex; align-items: center; justify-content: center; font-size: 13px; padding: 0 8px; }
        .zonas { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 8px; }
        .zona-tarjeta { border: 1px solid #DAD7D0; padding: 12px; display: flex; flex-direction: column; gap: 2px; }
        .cta { min-height: 56px; background: var(--tinta); color: #fff; border: 0; font: inherit; font-size: 15px; font-weight: 500; cursor: not-allowed; opacity: .85; }
        .pasos-ficha { list-style: none; margin: 0; padding: 20px; background: #F7F6F3; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; font-size: 13px; line-height: 1.4; counter-reset: paso; }
        .pasos-ficha li { counter-increment: paso; display: flex; flex-direction: column; gap: 6px; }
        .pasos-ficha li::before { content: counter(paso); font-family: var(--serif); font-size: 22px; }
        details { border-bottom: 1px solid var(--linea); }
        details:first-of-type { border-top: 1px solid var(--linea); }
        summary { min-height: 56px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; list-style: none; }
        summary::-webkit-details-marker { display: none; }
        summary::after { content: '+'; font-size: 20px; }
        details[open] summary::after { content: '−'; }
        details ul, details table { margin: 0 0 20px; font-size: 14px; color: #3A3A42; }
        table { border-collapse: collapse; width: 100%; }
        th, td { text-align: left; padding: 6px 8px 6px 0; border-bottom: 1px solid var(--linea); }
        @media (max-width: 600px) { .pasos-ficha { grid-template-columns: 1fr; } }
    </style>
@endpush

@push('scripts')
    <script>
        // Visor: color, cara y zonas de impresión sobre la maqueta (sin librerías).
        (function () {
            var visor = document.getElementById('visor');
            if (!visor) return;
            var colores = JSON.parse(visor.dataset.colores || '[]');
            var estado = { color: 0, cara: 'delantera', zonas: false };
            var lienzo = document.getElementById('lienzo');

            function pintar() {
                var color = colores[estado.color];
                if (!color) return;
                var datos = color.caras[estado.cara];
                lienzo.innerHTML = '';
                if (datos) {
                    var img = document.createElement('img');
                    img.src = datos.url; img.alt = datos.alt || color.nombre; img.width = 1000; img.height = 1200;
                    lienzo.appendChild(img);
                    if (estado.zonas) {
                        var tinta = /^#(0|1|2|3)/i.test(color.hex) ? '#FFFFFF' : '#16161A';
                        datos.zonas.forEach(function (z) {
                            var marca = document.createElement('div');
                            marca.className = 'zona-marca';
                            marca.style.cssText = 'left:' + z.x + '%;top:' + z.y + '%;width:' + z.ancho + '%;height:' + z.alto + '%;color:' + tinta;
                            var etiqueta = document.createElement('span'); etiqueta.textContent = z.nombre || '';
                            marca.appendChild(etiqueta); lienzo.appendChild(marca);
                        });
                    }
                } else {
                    var vacio = document.createElement('p'); vacio.className = 'sin-foto'; vacio.textContent = @json(__('tienda.sin_foto'));
                    lienzo.appendChild(vacio);
                }
                document.getElementById('nombre-color').textContent = color.nombre;
                document.querySelectorAll('.muestra').forEach(function (b) { b.setAttribute('aria-checked', String(Number(b.dataset.color) === estado.color)); });
                document.querySelectorAll('[data-cara]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.cara === estado.cara)); });
                document.getElementById('ver-zonas').setAttribute('aria-pressed', String(estado.zonas));
            }

            document.querySelectorAll('.muestra').forEach(function (b) { b.addEventListener('click', function () { estado.color = Number(b.dataset.color); pintar(); }); });
            document.querySelectorAll('[data-cara]').forEach(function (b) { b.addEventListener('click', function () { estado.cara = b.dataset.cara; pintar(); }); });
            document.getElementById('ver-zonas').addEventListener('click', function () { estado.zonas = !estado.zonas; pintar(); });
        })();
    </script>
@endpush

<x-tienda :titulo="($prenda->seo_titulo ?: $prenda->nombre).' · NextGen Apparel'" :descripcion="$prenda->seo_descripcion ?: \Illuminate\Support\Str::limit((string) $prenda->descripcion, 155)" :alternos="$alternos">
    <div class="contenedor">
        <nav class="ruta" aria-label="Ruta">
            <a href="{{ route('portada') }}">{{ __('tienda.inicio') }}</a><span aria-hidden="true">/</span>
            <a href="{{ route('tienda.familia', ['familia' => Escaparate::slug($familia, $idioma)]) }}">{{ $familia->nombre }}</a><span aria-hidden="true">/</span>
            <span>{{ $prenda->nombre }}</span>
        </nav>

        <div class="ficha">
            <section class="galeria" aria-label="{{ $prenda->nombre }}">
                <div class="visor" id="visor" data-colores='@json($colores)'>
                    <div class="visor-controles">
                        <div class="segmento" role="group" aria-label="{{ __('tienda.delantera') }} / {{ __('tienda.trasera') }}">
                            <button type="button" class="boton-pildora" data-cara="delantera" aria-pressed="true">{{ __('tienda.delantera') }}</button>
                            <button type="button" class="boton-pildora" data-cara="trasera" aria-pressed="false">{{ __('tienda.trasera') }}</button>
                        </div>
                        <button type="button" class="boton-borde" id="ver-zonas" aria-pressed="false">{{ __('tienda.ver_zonas') }}</button>
                    </div>
                    <div class="visor-lienzo" id="lienzo">
                        @if ($fotoInicial)
                            <img id="foto" src="{{ $fotoInicial['url'] }}" alt="{{ $fotoInicial['alt'] ?? $prenda->nombre }}" width="1000" height="1200">
                        @else
                            <p class="sin-foto" id="foto-vacia">{{ __('tienda.sin_foto') }}</p>
                        @endif
                    </div>
                    <p class="visor-nota">{{ __('tienda.vista_orientativa') }}</p>
                </div>
            </section>

            <section class="info" aria-label="{{ __('tienda.detalles') }}">
                <div>
                    <p class="etiqueta">{{ $familia->nombre }} · DTF</p>
                    <h1>{{ $prenda->nombre }}</h1>
                    @if ($precio)
                        <div class="precio">
                            <span class="suave">{{ __('tienda.desde') }}</span>
                            <strong>{{ $precio }}</strong>
                            <span class="suave">{{ __('tienda.por_unidad') }} · {{ __('tienda.iva_incluido') }}</span>
                        </div>
                    @endif
                    @if ($prenda->descripcion)
                        <p style="margin: 16px 0 0; color: #3A3A42;">{{ $prenda->descripcion }}</p>
                    @endif
                </div>

                @if (count($colores) > 0)
                    <div>
                        <p class="suave" style="margin-bottom: 10px;">{{ __('tienda.color') }} · <strong id="nombre-color" style="color: var(--tinta); font-weight: 500;">{{ $primerColor['nombre'] }}</strong></p>
                        <div class="muestras" role="radiogroup" aria-label="{{ __('tienda.color') }}">
                            @foreach ($colores as $i => $color)
                                <button type="button" class="muestra" role="radio" aria-checked="{{ $i === 0 ? 'true' : 'false' }}" aria-label="{{ $color['nombre'] }}" data-color="{{ $i }}">
                                    <span style="background: {{ $color['hex'] }};"></span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <p class="suave" style="margin-bottom: 10px;">{{ __('tienda.tallas_disponibles') }}</p>
                    <div class="chips">
                        @foreach ($tallas as $talla)
                            <span class="chip">{{ $talla }}</span>
                        @endforeach
                    </div>
                    <p class="suave" style="margin-top: 10px; font-size: 13px;">{{ __('tienda.tallas_nota') }}</p>
                </div>

                @if ($prenda->zonas->isNotEmpty())
                    <div>
                        <p class="suave" style="margin-bottom: 10px;">{{ __('tienda.donde_imprimimos') }}</p>
                        <div class="zonas">
                            @foreach ($prenda->zonas as $zona)
                                <div class="zona-tarjeta">
                                    <span style="font-size: 14px; font-weight: 500;">{{ $zona->nombre }}</span>
                                    <span class="suave" style="font-size: 12px;">{{ __('tienda.hasta', ['medida' => $numero($zona->ancho_max_mm).' × '.$numero($zona->alto_max_mm).' cm']) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <p class="suave" style="margin-top: 10px; font-size: 13px;">{{ __('tienda.zonas_nota') }}</p>
                    </div>
                @endif

                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <button type="button" class="cta" disabled aria-describedby="proximamente">{{ __('tienda.personalizar') }}</button>
                    <p class="suave" id="proximamente" style="font-size: 13px;">{{ __('tienda.proximamente') }} {{ __('tienda.minimo') }}</p>
                </div>

                <ol class="pasos-ficha">
                    <li>{{ __('tienda.paso_1') }}</li>
                    <li>{{ __('tienda.paso_2') }}</li>
                    <li>{{ __('tienda.paso_3') }}</li>
                </ol>

                <div>
                    <details>
                        <summary>{{ __('tienda.detalles') }}</summary>
                        <ul>
                            @if ($prenda->composicion)<li>{{ __('tienda.composicion') }}: {{ $prenda->composicion }}</li>@endif
                            @if ($prenda->gramaje)<li>{{ __('tienda.gramaje') }}: {{ $prenda->gramaje }} g/m²</li>@endif
                            @if ($prenda->detalles)<li>{{ $prenda->detalles }}</li>@endif
                        </ul>
                    </details>
                    @if ($medidas->isNotEmpty())
                        <details>
                            <summary>{{ __('tienda.guia_tallas') }}</summary>
                            <table>
                                <thead><tr><th scope="col">{{ __('tienda.talla') }}</th><th scope="col">{{ __('tienda.largo') }}</th><th scope="col">{{ __('tienda.ancho') }}</th><th scope="col">{{ __('tienda.manga') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($medidas as $medida)
                                        <tr><th scope="row">{{ $medida->talla->nombre }}</th><td>{{ $cm($medida->largo_mm) }}</td><td>{{ $cm($medida->ancho_mm) }}</td><td>{{ $cm($medida->manga_mm) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </details>
                    @endif
                    <details>
                        <summary>{{ __('tienda.zonas_calidad') }}</summary>
                        <ul><li>{{ __('tienda.calidad_texto') }}</li></ul>
                    </details>
                    <details>
                        <summary>{{ __('tienda.plazos') }}</summary>
                        <ul><li>{{ __('tienda.plazo_produccion', ['dias' => $familia->plazo_base_dias]) }}</li><li>{{ __('tienda.plazo_envio') }}</li></ul>
                    </details>
                    <details>
                        <summary>{{ __('tienda.garantia') }}</summary>
                        <ul><li>{{ __('tienda.garantia_desistimiento') }}</li><li>{{ __('tienda.garantia_defecto') }}</li></ul>
                    </details>
                </div>

                <p class="suave" style="font-size: 13px;">{{ __('tienda.pago_seguro') }}</p>
            </section>
        </div>
    </div>
</x-tienda>
