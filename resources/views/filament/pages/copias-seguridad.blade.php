<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Configuración</x-slot>

        <dl style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; font-size: 0.875rem;">
            <div>
                <dt style="opacity: 0.7;">Cuándo</dt>
                <dd style="margin: 0.25rem 0 0;">Cada día a las 01:30. Prueba de restauración los domingos a las 04:00.</dd>
            </div>
            <div>
                <dt style="opacity: 0.7;">Qué se guarda</dt>
                <dd style="margin: 0.25rem 0 0;">Base de datos y archivos subidos (diseños, PDF, imágenes).</dd>
            </div>
            <div>
                <dt style="opacity: 0.7;">Cuántas se conservan</dt>
                <dd style="margin: 0.25rem 0 0;">7 diarias, 4 semanales y 12 mensuales.</dd>
            </div>
            <div>
                <dt style="opacity: 0.7;">Cifrado</dt>
                <dd style="margin: 0.25rem 0 0;">{{ $cifradas ? 'Sí (contraseña en el .env del servidor).' : 'No (en el servidor es obligatorio).' }}</dd>
            </div>
            <div>
                <dt style="opacity: 0.7;">Copia fuera del servidor</dt>
                <dd style="margin: 0.25rem 0 0;">{{ $externa ? 'Activada (OVH Object Storage).' : 'Desactivada hasta contratar OVH Object Storage.' }}</dd>
            </div>
            <div>
                <dt style="opacity: 0.7;">Última prueba de restauración</dt>
                <dd style="margin: 0.25rem 0 0;">
                    @if ($prueba)
                        <x-filament::badge :color="$prueba['correcto'] ? 'success' : 'danger'" style="display: inline-flex;">
                            {{ $prueba['correcto'] ? 'Correcta' : 'Fallida' }}
                        </x-filament::badge>
                        {{ $prueba['fecha']->format('d/m/Y H:i') }} · {{ $prueba['mensaje'] }}
                    @else
                        Todavía no se ha probado.
                    @endif
                </dd>
            </div>
        </dl>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Copias guardadas</x-slot>

        @if (count($copias) === 0)
            <p>Todavía no hay copias. Pulsa «Hacer copia ahora».</p>
        @else
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr>
                            <th scope="col" style="text-align: left; padding: 0.5rem 0.75rem;">Fecha</th>
                            <th scope="col" style="text-align: left; padding: 0.5rem 0.75rem;">Dónde</th>
                            <th scope="col" style="text-align: right; padding: 0.5rem 0.75rem;">Tamaño</th>
                            @if ($puedeDescargar)
                                <th scope="col" style="padding: 0.5rem 0.75rem;"><span style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0);">Acciones</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($copias as $copia)
                            <tr style="border-top: 1px solid rgba(127, 127, 127, 0.2);">
                                <td style="padding: 0.5rem 0.75rem; white-space: nowrap;">{{ $copia['fecha']->format('d/m/Y H:i') }}</td>
                                <td style="padding: 0.5rem 0.75rem;">{{ $copia['disco'] === 'copias' ? 'Este servidor' : 'Fuera del servidor' }}</td>
                                <td style="padding: 0.5rem 0.75rem; text-align: right; white-space: nowrap;">{{ number_format($copia['bytes'] / 1024 / 1024, 1, ',', '.') }} MB</td>
                                @if ($puedeDescargar)
                                    <td style="padding: 0.5rem 0.75rem; text-align: right;">
                                        <x-filament::link tag="button" wire:click="descargar(@js($copia['disco']), @js($copia['ruta']))" icon="heroicon-o-arrow-down-tray">
                                            Descargar
                                        </x-filament::link>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
