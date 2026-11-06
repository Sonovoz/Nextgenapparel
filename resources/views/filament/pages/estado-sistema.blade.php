<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Comprobaciones</x-slot>
        <x-slot name="description">
            @if ($comprobadoEn)
                Última comprobación: {{ $comprobadoEn->format('d/m/Y H:i') }} ({{ $comprobadoEn->diffForHumans() }}).
                Se repite sola cada 5 minutos y avisa por correo si algo falla.
            @else
                Todavía no se ha comprobado. Pulsa «Comprobar ahora».
            @endif
        </x-slot>

        @if (count($resultados) > 0)
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr>
                            <th scope="col" style="text-align: left; padding: 0.5rem 0.75rem;">Comprobación</th>
                            <th scope="col" style="text-align: left; padding: 0.5rem 0.75rem;">Estado</th>
                            <th scope="col" style="text-align: left; padding: 0.5rem 0.75rem;">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resultados as $resultado)
                            <tr style="border-top: 1px solid rgba(127, 127, 127, 0.2);">
                                <th scope="row" style="text-align: left; font-weight: 500; padding: 0.625rem 0.75rem; white-space: nowrap;">{{ $resultado->nombre }}</th>
                                <td style="padding: 0.625rem 0.75rem;">
                                    <x-filament::badge :color="$resultado->estado->getColor()">
                                        {{ $resultado->estado->getLabel() }}
                                    </x-filament::badge>
                                </td>
                                <td style="padding: 0.625rem 0.75rem;">{{ $resultado->mensaje }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Tareas automáticas</x-slot>
        <x-slot name="description">
            @if ($esLocal)
                En tu ordenador solo se ejecutan si tienes abierta una ventana de comandos con «php artisan schedule:work». En el servidor se ejecutan siempre.
            @else
                Las ejecuta el servidor sin intervención.
            @endif
        </x-slot>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr>
                        <th scope="col" style="text-align: left; padding: 0.5rem 0.75rem;">Tarea</th>
                        <th scope="col" style="text-align: left; padding: 0.5rem 0.75rem;">Cuándo</th>
                        <th scope="col" style="text-align: left; padding: 0.5rem 0.75rem;">Próxima vez</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tareas as $tarea)
                        <tr style="border-top: 1px solid rgba(127, 127, 127, 0.2);">
                            <td style="padding: 0.5rem 0.75rem;">{{ $tarea['descripcion'] }}</td>
                            <td style="padding: 0.5rem 0.75rem; white-space: nowrap;">{{ $tarea['frecuencia'] }}</td>
                            <td style="padding: 0.5rem 0.75rem; white-space: nowrap;">{{ $tarea['siguiente']->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
