<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Pedidos en producción</x-slot>
        <x-slot name="description">Cada diseño se repite tantas veces como prendas lleva su línea. Film de {{ $plan['ancho'] }} mm.</x-slot>
        <dl style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; font-size: .875rem;">
            <div><dt style="opacity: .7;">Pliegos</dt><dd style="margin: .25rem 0 0; font-size: 1.3rem; font-weight: 700;">{{ count($plan['pliegos']) }}</dd></div>
            <div><dt style="opacity: .7;">Diseños colocados</dt><dd style="margin: .25rem 0 0; font-size: 1.3rem; font-weight: 700;">{{ $piezas }}</dd></div>
            <div><dt style="opacity: .7;">Film</dt><dd style="margin: .25rem 0 0; font-size: 1.3rem; font-weight: 700;">{{ number_format($metros, 2, ',', '.') }} m</dd></div>
        </dl>
    </x-filament::section>
    @if ($plan['fuera'] !== [])
        <x-filament::section>
            <x-slot name="heading">No caben en el film</x-slot>
            <ul style="font-size: .875rem;">@foreach ($plan['fuera'] as $id)<li>{{ $id }}</li>@endforeach</ul>
        </x-filament::section>
    @endif
    @if ($plan['textos'] !== [])
        <x-filament::section>
            <x-slot name="heading">Textos para imprimir aparte</x-slot>
            <ul style="font-size: .875rem;">@foreach ($plan['textos'] as $t)<li>{{ $t }}</li>@endforeach</ul>
        </x-filament::section>
    @endif
</x-filament-panels::page>
