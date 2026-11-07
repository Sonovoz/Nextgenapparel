{{-- Tarjeta de prenda o familia: $url, $titulo, $foto (Foto|null), $precio (string|null) --}}
<a class="tarjeta" href="{{ $url }}">
    <div class="tarjeta-foto">
        @if ($foto)
            <img src="{{ $foto->url() }}" alt="{{ $foto->texto_alternativo ?: $titulo }}" loading="lazy" width="1000" height="1200">
        @else
            <span class="sin-foto">{{ __('tienda.sin_foto') }}</span>
        @endif
    </div>
    <p class="tarjeta-nombre">{{ $titulo }}</p>
    @if ($precio)
        <p class="tarjeta-precio">{{ __('tienda.desde') }} {{ $precio }} {{ __('tienda.por_unidad') }} · {{ __('tienda.iva_incluido') }}</p>
    @endif
</a>
