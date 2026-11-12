<x-filament-panels::page>
    <form wire:submit="callMountedAction">
        {{ $this->form }}
    </form>
    @include('filament.partials.guardar-fijo')
</x-filament-panels::page>
