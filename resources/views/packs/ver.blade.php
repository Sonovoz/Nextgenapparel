@php $elegido = old('diseno', session('diseno_elegido', $disenos->first()?->uuid)); $origen = old('origen', $disenos->isEmpty() ? 'texto' : 'diseno'); @endphp
<x-tienda :titulo="$pack->nombre.' · Nextgenapparel'" :descripcion="$pack->descripcion ?: __('packs.entradilla')" :alternos="$alternos">
    <section class="carrito-pagina pack-pagina" aria-labelledby="titulo-pack">
        <div class="contenedor carrito-rejilla">
            <div>
                <p class="etiqueta">{{ __('packs.etiqueta') }}</p>
                <h1 class="titulo-cuenta" id="titulo-pack">{{ $pack->nombre }}</h1>
                <p class="pack-precio">{{ $precio }}<small>{{ __('portada.pack_iva') }}</small></p>
                @if ($pack->descripcion)<p class="claro">{{ $pack->descripcion }}</p>@endif
                <ul class="carrito-impresiones">
                    @foreach ($lineas as $l)
                        <li><strong>{{ $l['linea']->unidades }} × {{ $l['linea']->prenda->nombre }}</strong>
                            @if (! $libre)<span>{{ collect($l['tallas'])->map(fn ($t) => $t['nombre'].' × '.$t['fijas'])->implode(' · ') }}</span>@endif</li>
                    @endforeach
                    <li><strong>{{ __('packs.impresion') }}</strong><span>{{ $pack->zonas->pluck('nombre')->implode(', ') }}</span></li>
                </ul>

                @if (session('estado'))<p class="aviso-cuenta correcto" role="status">{{ session('estado') }}</p>@endif
                @if ($errors->any())
                    <div class="aviso-cuenta error" role="alert" tabindex="-1" id="resumen-errores"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                    <script>document.getElementById('resumen-errores').focus();</script>
                @endif

                {{-- 1. Subir un diseño (formulario aparte: vuelve con él elegido). --}}
                <form class="pack-subir" method="POST" action="{{ route('packs.subir', ['pack' => request()->route('pack')]) }}" enctype="multipart/form-data">
                    @csrf
                    <h2>{{ __('packs.subir_titulo') }}</h2>
                    <label for="campo-archivo">{{ __('packs.subir_png', ['mb' => $maxMb]) }}</label>
                    <input type="file" id="campo-archivo" name="archivo" accept="image/png" required>
                    <label class="casilla"><input type="checkbox" name="derechos" value="1" required> {{ __('disenos.derechos_casilla') }}</label>
                    <button type="submit" class="boton">{{ __('packs.subir') }}</button>
                </form>
            </div>

            {{-- 2. Elegir diseño o texto, repartir y añadir. --}}
            <form class="carrito-resumen" method="POST" action="{{ route('packs.anadir', ['pack' => request()->route('pack')]) }}">
                @csrf
                <h2>{{ __('packs.tu_diseno') }}</h2>
                <fieldset class="pack-origen">
                    <legend class="oculto">{{ __('packs.tu_diseno') }}</legend>
                    <label class="casilla"><input type="radio" name="origen" value="diseno" @checked($origen === 'diseno') @disabled($disenos->isEmpty())> {{ __('packs.usar_logo') }}</label>
                    @if ($disenos->isEmpty())
                        <p class="claro">{{ __('packs.sin_disenos') }}</p>
                    @else
                        <div class="pack-disenos">
                            @foreach ($disenos as $d)
                                <label><input type="radio" name="diseno" value="{{ $d->uuid }}" @checked($elegido === $d->uuid)>
                                    <img src="{{ route('disenos.miniatura', ['diseno' => $d->uuid]) }}" alt="{{ $d->nombre_original }}" width="72" height="72" loading="lazy"></label>
                            @endforeach
                        </div>
                    @endif
                    <label class="casilla"><input type="radio" name="origen" value="texto" @checked($origen === 'texto')> {{ __('packs.usar_texto') }}</label>
                    <input type="text" name="texto" value="{{ old('texto') }}" maxlength="60" placeholder="NEXTGEN" aria-label="{{ __('packs.campo_texto') }}" class="campo-pack">
                    <div class="fila-cupon">
                        <select name="fuente" aria-label="{{ __('tienda.tipografia') }}" class="campo-pack">@foreach (range(1, 4) as $n)<option value="{{ $n - 1 }}" @selected((int) old('fuente') === $n - 1)>{{ __('packs.fuente', ['n' => $n]) }}</option>@endforeach</select>
                        <select name="tinta" aria-label="{{ __('tienda.tinta') }}" class="campo-pack">@foreach (\App\Dominio\Carrito\Impresion::TINTAS as $t)<option value="{{ $t }}" @selected(old('tinta', 'auto') === $t)>{{ __('tienda.tinta_'.$t) }}</option>@endforeach</select>
                    </div>
                </fieldset>
                <p class="claro">{{ __('packs.colocacion') }}</p>

                @if ($libre)
                    <h2>{{ __('packs.reparte') }}</h2>
                    @foreach ($lineas as $l)
                        <fieldset class="carrito-tallas">
                            <legend>{{ trans_choice('packs.reparte_linea', $l['linea']->unidades, ['n' => $l['linea']->unidades, 'prenda' => $l['linea']->prenda->nombre]) }}</legend>
                            <label><span>{{ __('packs.color') }}</span>
                                <select name="elecciones[{{ $l['linea']->id }}][color]" class="campo-pack">@foreach ($l['colores'] as $c)<option value="{{ $c->id }}" @selected((int) old('elecciones.'.$l['linea']->id.'.color') === $c->id)>{{ $c->nombre }}</option>@endforeach</select></label>
                            @foreach ($l['tallas'] as $t)
                                <label><span>{{ $t['nombre'] }}</span><input type="number" name="elecciones[{{ $l['linea']->id }}][tallas][{{ $t['id'] }}]" value="{{ old('elecciones.'.$l['linea']->id.'.tallas.'.$t['id'], 0) }}" min="0" max="10000" inputmode="numeric"></label>
                            @endforeach
                        </fieldset>
                    @endforeach
                @endif

                <button type="submit" class="boton principal">{{ __('tienda.anadir_pedido') }}</button>
                <p class="claro">{{ __('packs.sin_cupon') }}</p>
            </form>
        </div>
    </section>
</x-tienda>
