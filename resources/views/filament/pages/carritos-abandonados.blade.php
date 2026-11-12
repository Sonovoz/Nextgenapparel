<x-filament-panels::page>
    <form wire:submit.prevent>{{ $this->form }}</form>
    @include('filament.partials.guardar-fijo')
    <x-filament::section>
        <x-slot name="heading">Últimos 30 días</x-slot>
        <dl style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; font-size: .875rem;">
            @forelse ($porPaso as $paso => $n)
                <div><dt style="opacity: .7;">Aviso {{ $paso + 1 }}</dt><dd style="margin: .25rem 0 0; font-size: 1.3rem; font-weight: 700;">{{ $n }}</dd></div>
            @empty
                <div><dt style="opacity: .7;">Avisos enviados</dt><dd style="margin: .25rem 0 0; font-size: 1.3rem; font-weight: 700;">0</dd></div>
            @endforelse
            <div><dt style="opacity: .7;">Clientes avisados</dt><dd style="margin: .25rem 0 0; font-size: 1.3rem; font-weight: 700;">{{ $clientes }}</dd></div>
            <div><dt style="opacity: .7;">Compraron en los 7 días siguientes</dt><dd style="margin: .25rem 0 0; font-size: 1.3rem; font-weight: 700;">{{ $recuperados }}</dd></div>
        </dl>
    </x-filament::section>
</x-filament-panels::page>
