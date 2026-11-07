<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Roles</x-slot>
        <x-slot name="description">
            Los roles son fijos. Para dar acceso a alguien, asígnale uno en Usuarios.
        </x-slot>

        <dl style="display: grid; gap: 1rem;">
            @foreach ($roles as $fila)
                <div>
                    <dt style="font-weight: 600;">
                        {{ $fila['nombre'] }}
                        <span style="font-weight: 400; opacity: 0.7;">
                            · {{ $fila['usuarios'] }} {{ $fila['usuarios'] === 1 ? 'usuario activo' : 'usuarios activos' }}
                        </span>
                    </dt>
                    <dd style="margin: 0.25rem 0 0;">{{ $fila['descripcion'] }}</dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Qué ve cada rol en el menú</x-slot>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <caption style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0);">
                    Acceso de cada rol a cada grupo del menú
                </caption>
                <thead>
                    <tr>
                        <th scope="col" style="text-align: left; padding: 0.5rem 0.75rem;">Grupo del menú</th>
                        @foreach ($roles as $fila)
                            <th scope="col" style="text-align: center; padding: 0.5rem 0.75rem;">{{ $fila['nombre'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grupos as $grupo)
                        <tr style="border-top: 1px solid rgba(127, 127, 127, 0.2);">
                            <th scope="row" style="text-align: left; font-weight: 500; padding: 0.5rem 0.75rem;">{{ $grupo->getLabel() }}</th>
                            @foreach ($roles as $fila)
                                <td style="text-align: center; padding: 0.5rem 0.75rem;">
                                    @if ($fila['rol']->puedeVer($grupo))
                                        <x-filament::icon icon="heroicon-o-check" class="h-5 w-5" style="display: inline-block;" />
                                        <span style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0);">Sí</span>
                                    @else
                                        <span aria-hidden="true" style="opacity: 0.4;">—</span>
                                        <span style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0);">No</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
