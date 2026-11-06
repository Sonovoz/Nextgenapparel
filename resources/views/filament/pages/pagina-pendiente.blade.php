<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Disponible en la {{ $disponibleEn }}
        </x-slot>

        <p>{{ $descripcion }}</p>

        @if ($equivalenteWooCommerce)
            <p style="margin-top: 0.75rem; opacity: 0.75;">
                En WooCommerce: {{ $equivalenteWooCommerce }}.
            </p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
