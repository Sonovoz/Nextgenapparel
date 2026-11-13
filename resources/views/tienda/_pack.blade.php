{{-- Tarjeta de un pack (portada y páginas de modo, D203); $pack viene de Portada::packs(). --}}
<article class="pack aparece">
    <div class="pack-foto{{ $pack['imagen'] ?? null ? ' con-imagen' : '' }}">
        @if ($pack['imagen'] ?? null)<img src="{{ $pack['imagen'] }}" alt="" width="1000" height="1200" loading="lazy">
        @elseif ($pack['foto'])<img src="{{ $pack['foto']->url() }}" alt="" width="1000" height="1200" loading="lazy">@endif
    </div>
    <div class="pack-cuerpo">
        <h3>{{ $pack['nombre'] }}</h3>
        @if ($pack['neto'] ?? null)
            {{-- D219: neto + IVA delante y, debajo, el precio final con IVA (el que paga el consumidor). --}}
            <p class="pack-precio">{{ $pack['neto'] }}<small>{{ __('portada.pack_mas_iva') }}</small></p>
            <p class="pack-precio-final">{{ $pack['precio'] }} {{ __('portada.pack_iva') }}</p>
        @else
            <p class="pack-precio">{{ $pack['precio'] }}<small>{{ __('portada.pack_iva') }}</small></p>
        @endif
        <ul class="pack-datos">
            <li>{{ __('portada.pack_prendas', ['n' => $pack['unidades']]) }}</li>
            @if ($pack['zonas'])<li>{{ __('portada.pack_impresion', ['zonas' => $pack['zonas']]) }}</li>@endif
            @if ($pack['descripcion'])<li>{{ $pack['descripcion'] }}</li>@endif
        </ul>
        <a class="boton" href="{{ $pack['url'] }}">{{ __('portada.pack_elegir') }}<span class="oculto"> · {{ $pack['nombre'] }}</span></a>
    </div>
</article>
