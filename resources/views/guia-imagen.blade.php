@php
    // D249: guía «Prepara tu imagen para imprimir». Página fija, sin base de datos; las cifras salen de Ajustes (ppp óptimos).
    $portada = route('portada');
    $flecha = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>';
    $ppp = \App\Dominio\Disenos\Calidad::umbrales()['correcto'];
    $puntos = fn (float $cm) => number_format((int) ceil($cm / 2.54 * $ppp), 0, ',', '.');
@endphp

<x-tienda :titulo="__('guia_imagen.titulo_pagina')" :descripcion="__('guia_imagen.descripcion')" :alternos="$alternos" :seo="$seo">
<div class="pagina-guia">
    {{-- 1. La idea: en el móvil se ve bien; en la camiseta, no --}}
    <section class="guia-hero" aria-labelledby="titulo-guia">
        <div class="contenedor guia-hero-rejilla">
            <div>
                <p class="etiqueta">{{ __('guia_imagen.etiqueta') }}</p>
                <h1 class="titulo-seccion" id="titulo-guia">{{ __('guia_imagen.titular') }}</h1>
                <p class="entradilla">{{ __('guia_imagen.entradilla') }}</p>
                <p class="guia-acciones">
                    <a class="boton principal" href="#mejorar">{{ __('guia_imagen.ir_consejos') }} <span class="flecha">{!! $flecha !!}</span></a>
                    <a class="boton" href="{{ $portada }}#pasos">{{ __('guia_imagen.ir_comprobar') }}</a>
                </p>
            </div>
            <div class="guia-demo" aria-hidden="true">
                <figure class="guia-movil">
                    <div class="guia-movil-pantalla"><img src="@recurso('img/guia/dragon-nitido.webp')" alt="" width="460" height="460"></div>
                    <figcaption><strong>{{ __('guia_imagen.demo_movil') }}</strong><span>{{ __('guia_imagen.demo_movil_texto') }}</span></figcaption>
                </figure>
                <figure class="guia-tela">
                    <img src="@recurso('img/guia/dragon-pixelado.png')" alt="" width="40" height="40">
                    <figcaption><strong>{{ __('guia_imagen.demo_camiseta') }}</strong><span>{{ __('guia_imagen.demo_camiseta_texto') }}</span></figcaption>
                </figure>
            </div>
        </div>
    </section>

    {{-- 2. Por qué pasa, y cuántos puntitos hacen falta --}}
    <section class="seccion" aria-labelledby="titulo-porque">
        <div class="contenedor guia-dos">
            <div>
                <p class="etiqueta aparece">{{ __('guia_imagen.porque_etiqueta') }}</p>
                <h2 class="titulo-seccion aparece" id="titulo-porque">{{ __('guia_imagen.porque_titulo') }}</h2>
                <p class="entradilla aparece">{{ __('guia_imagen.porque_texto') }}</p>
                <p class="guia-regla aparece">{{ __('guia_imagen.porque_regla', ['por_cm' => (int) round($ppp / 2.54), 'ppp' => $ppp]) }}</p>
            </div>
            <div class="guia-tabla aparece">
                <h3>{{ __('guia_imagen.tabla_titulo') }}</h3>
                <table>
                    <thead><tr><th scope="col">{{ __('guia_imagen.tabla_tamano') }}</th><th scope="col">{{ __('guia_imagen.tabla_puntos') }}</th></tr></thead>
                    <tbody>
                        @foreach ([9, 15, 25, 30] as $i => $cm)
                            <tr>
                                <td><strong>{{ $cm }} cm</strong><small>{{ __('guia_imagen.tabla_ejemplos')[$i] }}</small></td>
                                <td>{{ $puntos($cm) }} px</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p>{{ __('guia_imagen.tabla_nota') }}</p>
            </div>
        </div>
    </section>

    {{-- 3. Qué es un PNG --}}
    <section class="seccion" aria-labelledby="titulo-png">
        <div class="contenedor">
            <p class="etiqueta aparece">{{ __('guia_imagen.png_etiqueta') }}</p>
            <h2 class="titulo-seccion aparece" id="titulo-png">{{ __('guia_imagen.png_titulo') }}</h2>
            <p class="entradilla aparece">{{ __('guia_imagen.png_texto') }}</p>
            <div class="guia-comparar aparece">
                <figure class="guia-camiseta con-fondo">
                    <div class="guia-prenda"><span class="guia-recuadro"><img src="@recurso('img/guia/dragon-nitido.webp')" alt="" width="460" height="460" loading="lazy"></span></div>
                    <figcaption><strong>{{ __('guia_imagen.png_jpg') }}</strong>{{ __('guia_imagen.png_jpg_texto') }}</figcaption>
                </figure>
                <figure class="guia-camiseta sin-fondo">
                    <div class="guia-prenda"><span class="guia-recuadro"><img src="@recurso('img/guia/dragon-transparente.webp')" alt="" width="460" height="460" loading="lazy"></span></div>
                    <figcaption><strong>{{ __('guia_imagen.png_png') }}</strong>{{ __('guia_imagen.png_png_texto') }}</figcaption>
                </figure>
            </div>
            <p class="guia-ojo aparece">{{ __('guia_imagen.png_ojo') }}</p>
        </div>
    </section>

    {{-- 4. Cómo saber cuántos puntitos tiene --}}
    <section class="seccion" aria-labelledby="titulo-medir">
        <div class="contenedor">
            <p class="etiqueta aparece">{{ __('guia_imagen.medir_etiqueta') }}</p>
            <h2 class="titulo-seccion aparece" id="titulo-medir">{{ __('guia_imagen.medir_titulo') }}</h2>
            <ul class="guia-tarjetas cuatro aparece">
                @foreach (__('guia_imagen.medir') as [$sistema, $como])
                    <li><h3>{{ $sistema }}</h3><p>{{ $como }}</p></li>
                @endforeach
            </ul>
            <p class="guia-regla aparece">{{ __('guia_imagen.medir_facil') }}</p>
        </div>
    </section>

    {{-- 5. Errores frecuentes --}}
    <section class="seccion" aria-labelledby="titulo-errores">
        <div class="contenedor">
            <p class="etiqueta aparece">{{ __('guia_imagen.errores_etiqueta') }}</p>
            <h2 class="titulo-seccion aparece" id="titulo-errores">{{ __('guia_imagen.errores_titulo') }}</h2>
            <ol class="guia-tarjetas aparece">
                @foreach (__('guia_imagen.errores') as $i => [$titulo, $texto])
                    <li><span class="guia-numero" aria-hidden="true">0{{ $i + 1 }}</span><h3>{{ $titulo }}</h3><p>{{ $texto }}</p></li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- 6. Paso a paso --}}
    <section class="seccion" id="mejorar" aria-labelledby="titulo-pasos">
        <div class="contenedor">
            <p class="etiqueta aparece">{{ __('guia_imagen.pasos_etiqueta') }}</p>
            <h2 class="titulo-seccion aparece" id="titulo-pasos">{{ __('guia_imagen.pasos_titulo') }}</h2>
            <ol class="guia-pasos aparece">
                @foreach (__('guia_imagen.pasos') as $i => [$titulo, $texto])
                    <li><span class="guia-numero" aria-hidden="true">{{ $i + 1 }}</span><div><h3>{{ $titulo }}</h3><p>{{ str_replace(':ppp', (string) $ppp, $texto) }}</p></div></li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- 7. Herramientas gratis --}}
    <section class="seccion" aria-labelledby="titulo-herramientas">
        <div class="contenedor">
            <p class="etiqueta aparece">{{ __('guia_imagen.herramientas_etiqueta') }}</p>
            <h2 class="titulo-seccion aparece" id="titulo-herramientas">{{ __('guia_imagen.herramientas_titulo') }}</h2>
            <ul class="guia-tarjetas tres aparece">
                @foreach (__('guia_imagen.herramientas') as [$nombre, $url, $texto])
                    <li>
                        <h3>{{ $nombre }}</h3>
                        <p>{{ $texto }}</p>
                        <a href="{{ $url }}" target="_blank" rel="noopener nofollow">{{ __('guia_imagen.abrir', ['nombre' => $nombre]) }} ↗</a>
                    </li>
                @endforeach
            </ul>
            <p class="guia-nota aparece">{{ __('guia_imagen.herramientas_nota') }}</p>
        </div>
    </section>

    {{-- 8. Prompts para la IA (se completan con los cm que elige el cliente; los píxeles los calcula la página) --}}
    <section class="seccion" aria-labelledby="titulo-ia">
        <div class="contenedor">
            <p class="etiqueta aparece">{{ __('guia_imagen.ia_etiqueta') }}</p>
            <h2 class="titulo-seccion aparece" id="titulo-ia">{{ __('guia_imagen.ia_titulo') }}</h2>
            <p class="entradilla aparece">{{ __('guia_imagen.ia_texto') }}</p>
            <div class="guia-ia-panel aparece" id="guia-prompts" data-ppp="{{ $ppp }}" data-copiado="{{ __('guia_imagen.ia_copiado') }}">
                <label class="guia-cm"><span>{{ __('guia_imagen.ia_cm') }}</span><input type="number" id="guia-cm" min="2" max="60" step="0.5" value="25" inputmode="decimal"> <b>cm</b></label>
                <ul class="guia-prompts">
                    @foreach (__('guia_imagen.ia_prompts') as [$titulo, $texto])
                        <li>
                            <h3>{{ $titulo }}</h3>
                            <p class="guia-prompt" data-plantilla="{{ $texto }}">{{ __($texto, ['cm' => 25, 'px' => (int) ceil(25 / 2.54 * $ppp), 'ppp' => $ppp]) }}</p>
                            <button type="button" class="guia-copiar">{{ __('guia_imagen.ia_copiar') }}</button>
                        </li>
                    @endforeach
                </ul>
            </div>
            <p class="guia-nota aparece">{{ __('guia_imagen.ia_nota') }}</p>
        </div>
    </section>

    {{-- 9. Lista rápida y llamada final --}}
    <section class="final" aria-labelledby="titulo-final">
        <div class="contenedor">
            <p class="etiqueta aparece">{{ __('guia_imagen.lista_etiqueta') }}</p>
            <h2 class="titulo-seccion aparece" id="titulo-final">{{ __('guia_imagen.lista_titulo') }}</h2>
            <ul class="guia-lista aparece">
                @foreach (__('guia_imagen.lista') as $punto)
                    <li>{{ $punto }}</li>
                @endforeach
            </ul>
            <p class="entradilla aparece" style="margin-left: auto; margin-right: auto;"><strong>{{ __('guia_imagen.final_titulo') }}</strong> {{ __('guia_imagen.final_texto') }}</p>
            <div class="caminos aparece guia-final">
                <a class="camino principal" href="{{ $portada }}#pasos"><span><strong>{{ __('guia_imagen.final_boton') }}</strong><small>{{ __('portada.cta_coleccion_detalle') }}</small></span><span class="flecha">{!! $flecha !!}</span></a>
            </div>
        </div>
    </section>
</div>

@push('scripts')
<script>
    // Prompts con los cm del cliente: los píxeles los calcula la página (cm ÷ 2,54 × ppp), no la IA.
    (function () {
        var panel = document.getElementById('guia-prompts');
        if (!panel) return;
        var ppp = Number(panel.dataset.ppp) || 300;
        var campo = document.getElementById('guia-cm');
        function rellenar() {
            var cm = Math.min(60, Math.max(2, Number(String(campo.value).replace(',', '.')) || 25));
            var cmTexto = String(Math.round(cm * 10) / 10).replace('.', ',');
            var px = Math.ceil(cm / 2.54 * ppp);
            panel.querySelectorAll('.guia-prompt').forEach(function (p) {
                p.textContent = p.dataset.plantilla.split(':cm').join(cmTexto).split(':px').join(px).split(':ppp').join(ppp);
            });
        }
        campo.addEventListener('input', rellenar);
        panel.querySelectorAll('.guia-copiar').forEach(function (b) {
            b.addEventListener('click', function () {
                var texto = b.parentNode.querySelector('.guia-prompt').textContent;
                var hecho = function () { var antes = b.textContent; b.textContent = panel.dataset.copiado; setTimeout(function () { b.textContent = antes; }, 1600); };
                if (navigator.clipboard) navigator.clipboard.writeText(texto).then(hecho, function () {});
            });
        });
    })();
</script>
@endpush
</x-tienda>
