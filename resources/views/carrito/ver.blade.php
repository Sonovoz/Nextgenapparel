@php
    use App\Dominio\Carrito\TipoLinea;
    use App\Dominio\Precios\Formato;
    use App\Http\Controllers\CarritoController;
    $r = $valoracion?->resultado;
    $mm = fn (int $mm) => rtrim(rtrim(number_format($mm / 10, 1, app()->getLocale() === 'en' ? '.' : ',', ''), '0'), ',.');
    // Las mismas fuentes y tintas que el visor (public/js/visor.js).
    $fuentes = ['700 1em "Segoe UI", "Helvetica Neue", Arial, sans-serif', 'italic 400 1em "Iowan Old Style", "Palatino Linotype", Georgia, serif',
        '400 1em Impact, Haettenschweiler, "Arial Narrow Bold", "Arial Black", sans-serif', '400 1em "Segoe Script", "Brush Script MT", "Snell Roundhand", cursive'];
    $tintas = ['negro' => '#16161A', 'blanco' => '#FFFFFF', 'rojo' => '#C8102E', 'dorado' => '#C9A227'];
@endphp
<x-tienda :titulo="__('carrito.titulo').' · Nextgenapparel'" :descripcion="__('carrito.entradilla')" :alternos="$alternos" :seo="['noindex' => true]">
    <section class="carrito-pagina" aria-labelledby="titulo-carrito">
        <div class="contenedor">
            <h1 class="titulo-cuenta" id="titulo-carrito">{{ __('carrito.titulo') }}</h1>
            @if (session('estado'))
                <p class="aviso-cuenta correcto" role="status">{{ session('estado') }}</p>
            @endif
            @if ($errors->any())
                <div class="aviso-cuenta error" role="alert" tabindex="-1" id="resumen-errores">
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
                <script>document.getElementById('resumen-errores').focus();</script>
            @endif

            @if ($valoracion === null || $valoracion->vacio())
                <div class="carrito-vacio">
                    <p>{{ __('carrito.vacio') }}</p>
                    <a class="boton principal" href="{{ route('portada') }}#prendas">{{ __('carrito.ver_prendas') }} <span class="flecha" aria-hidden="true">→</span></a>
                </div>
            @else
                <div class="carrito-rejilla">
                    <ol class="carrito-lineas" aria-label="{{ __('carrito.lineas') }}">
                        @foreach ($lineas as $v)
                            @php
                                $linea = $v['linea'];
                                $esPack = $linea->tipo === TipoLinea::Pack;
                                $nombre = $esPack ? $linea->pack?->nombre : $linea->prenda?->nombre;
                                $unidades = $esPack ? $linea->pack?->unidades() : array_sum($linea->unidadesPorTalla());
                            @endphp
                            <li class="carrito-linea{{ $v['problemas'] ? ' con-problemas' : '' }}" id="linea-{{ $linea->uuid }}">
                                <div class="carrito-maquetas">
                                    @forelse ($v['maqueta'] as $cara)
                                        <figure class="maqueta">
                                            <img src="{{ $cara['url'] }}" alt="{{ $cara['alt'] ?? __('carrito.maqueta_de', ['prenda' => $nombre]) }}" loading="lazy" width="250" height="300">
                                            @foreach ($cara['capas'] as $capa)
                                                @php $i = $capa['impresion']; $estilo = "left:{$capa['left']}%;top:{$capa['top']}%;width:{$capa['width']}%;height:{$capa['height']}%"; @endphp
                                                @if ($i->texto !== null)
                                                    <span class="capa capa-texto" style="{{ $estilo }}" aria-hidden="true"><span style="font:{{ $fuentes[$i->fuente] ?? $fuentes[0] }};font-size:min(90cqh, {{ round(160 / max(1, mb_strlen($i->texto)), 1) }}cqw);color:{{ $tintas[$i->tinta] ?? (\App\Support\Colores::oscuro($linea->color?->codigo_hex) ? '#FFFFFF' : '#16161A') }}">{{ $i->texto }}</span></span>
                                                @elseif ($i->diseno)
                                                    <img class="capa" src="{{ CarritoController::url('miniatura', ['diseno' => $i->diseno->uuid]) }}" alt="" style="{{ $estilo }}" loading="lazy">
                                                @endif
                                            @endforeach
                                        </figure>
                                    @empty
                                        <div class="maqueta sin-foto" aria-hidden="true"></div>
                                    @endforelse
                                </div>

                                <div class="carrito-detalle">
                                    <h2>{{ $nombre ?? __('carrito.no_disponible') }}</h2>
                                    @if (! $esPack && $linea->color)
                                        <p class="claro"><span class="muestra" style="background: {{ $linea->color->codigo_hex }}" aria-hidden="true"></span> {{ $linea->color->nombre }} · {{ trans_choice('carrito.unidades', $unidades, ['n' => $unidades]) }}</p>
                                    @elseif ($esPack)
                                        <p class="claro">{{ __('carrito.pack') }} · {{ trans_choice('carrito.unidades', (int) $unidades, ['n' => (int) $unidades]) }}</p>
                                    @endif

                                    <ul class="carrito-impresiones">
                                        @foreach ($linea->impresiones as $i)
                                            <li>
                                                <strong>{{ $i->zona?->nombre }}</strong>
                                                <span>{{ $i->texto !== null ? '«'.$i->texto.'»' : ($i->diseno?->nombre_original ?? __('carrito.diseno_borrado')) }}</span>
                                                <small>{{ $mm($i->ancho_mm) }} × {{ $mm($i->alto_mm) }} cm</small>
                                                @if ($calidad = $v['calidades'][$i->zona_impresion_id] ?? null)
                                                    <small class="nivel-calidad" data-nivel="{{ $calidad['nivel']->value }}">{{ $calidad['nivel']->etiqueta() }}</small>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>

                                    @if ($v['problemas'])
                                        <ul class="mensajes impedimentos" role="alert">
                                            @foreach ($valoracion->problemas($linea) as $problema)<li>{{ $problema }}</li>@endforeach
                                        </ul>
                                    @endif

                                    @if (! $esPack && $v['tallas'])
                                        <form class="carrito-tallas" method="POST" action="{{ CarritoController::url('tallas', ['uuid' => $linea->uuid]) }}">
                                            @csrf @method('PATCH')
                                            <fieldset>
                                                <legend>{{ __('carrito.cantidades') }}</legend>
                                                @foreach ($v['tallas'] as $talla)
                                                    <label>
                                                        <span>{{ $talla['nombre'] }}</span>
                                                        <input type="number" name="tallas[{{ $talla['id'] }}]" value="{{ old('tallas.'.$talla['id'], $talla['unidades']) }}" min="0" max="10000" inputmode="numeric">
                                                    </label>
                                                @endforeach
                                            </fieldset>
                                            <button type="submit" class="boton-texto">{{ __('carrito.guardar_cantidades') }}<span class="oculto"> · {{ $nombre }}</span></button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ CarritoController::url('quitar', ['uuid' => $linea->uuid]) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="boton-texto peligro">{{ __('carrito.quitar') }}<span class="oculto"> · {{ $nombre }}</span></button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    <aside class="carrito-resumen" aria-labelledby="titulo-resumen">
                        <h2 id="titulo-resumen">{{ __('carrito.resumen') }}</h2>
                        <dl>
                            <div><dt>{{ __('carrito.prendas_impresion') }}</dt><dd>{{ Formato::euros($r->bruto()) }}</dd></div>
                            @if ($r->descuentos() > 0)
                                <div class="descuento"><dt>{{ $r->oferta['nombre'] ?? __('carrito.descuentos') }}{{ $r->cupon ? ' · '.$r->cupon['codigo'] : '' }}</dt><dd>−{{ Formato::euros($r->descuentos()) }}</dd></div>
                            @endif
                            <div><dt>{{ __('carrito.iva') }}</dt><dd>{{ Formato::euros($r->totalImpuestos()) }}</dd></div>
                            <div><dt>{{ __('carrito.envio') }}</dt><dd>{{ __('carrito.envio_al_cerrar') }}</dd></div>
                        </dl>
                        <p class="cuenta-total"><span>{{ __('carrito.total') }}</span><strong>{{ Formato::euros($r->total()) }}</strong></p>
                        <p class="claro">{{ __('carrito.precios_con_iva') }}</p>

                        @if ($r->avisos)
                            <ul class="mensajes avisos">@foreach ($r->textos('avisos') as $aviso)<li>{{ $aviso }}</li>@endforeach</ul>
                        @endif
                        @if ($r->impedimentos && ! $valoracion->vacio())
                            <ul class="mensajes impedimentos">@foreach ($r->textos('impedimentos') as $impedimento)<li>{{ $impedimento }}</li>@endforeach</ul>
                        @endif

                        <form class="carrito-cupon" id="cupon" method="POST" action="{{ CarritoController::url('cupon') }}">
                            @csrf
                            <label for="campo-cupon">{{ __('carrito.cupon') }}</label>
                            <div class="fila-cupon">
                                <input type="text" id="campo-cupon" name="cupon" value="{{ old('cupon', $carrito->cupon) }}" maxlength="50" autocomplete="off" autocapitalize="characters">
                                <button type="submit" class="boton">{{ __('carrito.aplicar') }}</button>
                            </div>
                        </form>
                        @if ($carrito->cupon)
                            <form method="POST" action="{{ CarritoController::url('cupon') }}">
                                @csrf <input type="hidden" name="cupon" value="">
                                <button type="submit" class="boton-texto">{{ __('carrito.quitar_cupon') }}</button>
                            </form>
                        @endif

                        @if ($valoracion->confirmable())
                            <a class="boton principal" href="{{ CarritoController::url('cerrar') }}">{{ __('carrito.cerrar_pedido') }} <span class="flecha" aria-hidden="true">→</span></a>
                        @else
                            <p class="carrito-siguiente">{{ __('carrito.revisa_antes') }}</p>
                        @endif
                        <a class="boton-texto" href="{{ route('portada') }}#prendas">← {{ __('carrito.seguir_comprando') }}</a>
                    </aside>
                </div>
            @endif
        </div>
    </section>
</x-tienda>
