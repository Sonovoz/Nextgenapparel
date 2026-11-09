<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ $hechos }} de {{ $total }} pasos hechos</x-slot>
        <x-slot name="description">
            Cada paso se comprueba solo con los datos del sistema; al hacerlo, se marca. Lo que va en el .env del servidor (claves de Redsys, alertas, copias externas, OpenAI) solo se comprueba si está: nunca se muestra.
        </x-slot>

        <ol style="list-style: none; margin: 0; padding: 0;">
            @foreach ($pasos as $i => $fila)
                @php($paso = $fila['paso'])
                <li style="display: flex; gap: 0.75rem; align-items: flex-start; padding: 0.75rem 0; @if (! $loop->first) border-top: 1px solid rgba(127, 127, 127, 0.2); @endif">
                    <x-filament::badge :color="$paso->hecho ? 'success' : ($paso->opcional ? 'gray' : 'warning')">
                        {{ $paso->hecho ? 'Hecho' : ($paso->opcional ? 'Opcional' : 'Pendiente') }}
                    </x-filament::badge>
                    <div style="flex: 1;">
                        <strong>{{ $i + 1 }}. {{ $paso->titulo }}</strong>
                        <div style="font-size: 0.875rem; opacity: 0.8;">{{ $paso->como }}</div>
                    </div>
                    @if ($fila['url'])
                        <x-filament::link :href="$fila['url']" size="sm">Ir</x-filament::link>
                    @elseif ($paso->donde === null)
                        <span style="font-size: 0.75rem; opacity: 0.7;">En el .env</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </x-filament::section>
</x-filament-panels::page>
