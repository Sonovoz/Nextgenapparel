{{-- «Guardar cambios» siempre a la vista en los ajustes: fijo abajo, aunque la página sea larga. Lo mismo que el botón de arriba. --}}
<div style="position: sticky; bottom: 1rem; z-index: 20; display: flex; justify-content: flex-end; margin-top: 1.5rem; pointer-events: none;">
    <div class="fi-section" style="padding: .6rem; border-radius: .75rem; box-shadow: 0 10px 30px rgba(0, 0, 0, .25); pointer-events: auto;">
        <x-filament::button wire:click="mountAction('guardar')" icon="heroicon-o-check" wire:loading.attr="disabled" wire:target="mountAction">
            Guardar cambios
        </x-filament::button>
    </div>
</div>
