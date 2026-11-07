@php $idioma = app()->getLocale(); @endphp
@push('estilos')
    <style>
        .familia h1 { font-size: clamp(36px, 5vw, 56px); margin: 0 0 12px; }
        .familia .intro { max-width: 60ch; color: #3A3A42; margin: 0 0 40px; }
    </style>
@endpush

<x-tienda :titulo="$familia->nombre.' · NextGen Apparel'" :descripcion="$familia->descripcion ?: __('portada.descripcion')" :alternos="$alternos">
    <div class="contenedor familia">
        <nav class="ruta" aria-label="Ruta">
            <a href="{{ route('portada') }}">{{ __('tienda.inicio') }}</a><span aria-hidden="true">/</span><span>{{ $familia->nombre }}</span>
        </nav>
        <h1>{{ $familia->nombre }}</h1>
        @if ($familia->descripcion)
            <p class="intro">{{ $familia->descripcion }}</p>
        @endif

        @if ($prendas->isEmpty())
            <p>{{ __('tienda.sin_prendas') }}</p>
        @else
            <div class="rejilla">
                @foreach ($prendas as $prenda)
                    @include('tienda._tarjeta', [
                        'url' => route('tienda.prenda', ['familia' => \App\Dominio\Catalogo\Escaparate::slug($familia, $idioma), 'prenda' => \App\Dominio\Catalogo\Escaparate::slug($prenda, $idioma)]),
                        'titulo' => $prenda->nombre,
                        'foto' => \App\Dominio\Catalogo\Escaparate::fotoPrincipal($prenda),
                        'precio' => \App\Dominio\Catalogo\Escaparate::precioDesde($prenda),
                    ])
                @endforeach
            </div>
        @endif
    </div>
</x-tienda>
