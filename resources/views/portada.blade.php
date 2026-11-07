@push('estilos')
    <style>
        .portada { padding-top: 88px; }
        .portada h1 { margin: 0; max-width: 16ch; font-size: clamp(40px, 7vw, 76px); line-height: 1.05; }
        .portada .texto { max-width: 56ch; margin: 28px 0 0; font-size: 18px; color: #3A3A42; }
        .pasos { list-style: none; margin: 64px 0 0; padding: 32px; background: var(--fondo-suave); display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; counter-reset: paso; }
        .pasos li { counter-increment: paso; display: flex; flex-direction: column; gap: 8px; }
        .pasos li::before { content: counter(paso); font-family: var(--serif); font-size: 28px; }
        .prendas { margin-top: 96px; }
        .prendas h2 { font-size: 40px; margin: 0 0 32px; }
    </style>
@endpush

<x-tienda :titulo="__('portada.titulo_pagina')" :descripcion="__('portada.descripcion')" :alternos="$alternos" :seo="$seo">
    <div class="contenedor portada">
        <p class="etiqueta">{{ __('portada.etiqueta') }}</p>
        <h1>{{ __('portada.titular') }}</h1>
        <p class="texto">{{ __('portada.texto') }}</p>
        <ol class="pasos">
            <li>{{ __('portada.paso_1') }}</li>
            <li>{{ __('portada.paso_2') }}</li>
            <li>{{ __('portada.paso_3') }}</li>
        </ol>

        @if ($familias->isNotEmpty())
            <section class="prendas" aria-labelledby="nuestras-prendas">
                <h2 id="nuestras-prendas">{{ __('tienda.nuestras_prendas') }}</h2>
                <div class="rejilla">
                    @foreach ($familias as $item)
                        @include('tienda._tarjeta', [
                            'url' => route('tienda.familia', ['familia' => \App\Dominio\Catalogo\Escaparate::slug($item['familia'], app()->getLocale())]),
                            'titulo' => $item['familia']->nombre,
                            'foto' => $item['foto'],
                            'precio' => null,
                        ])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-tienda>
