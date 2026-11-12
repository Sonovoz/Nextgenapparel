<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="description">
            Lo que necesita el portal en el servidor y si lo tiene. Los datos de conexión (base de datos, Redis, correo, Redsys, IA) van solo en el .env del servidor: aquí nunca se muestra una contraseña.
        </x-slot>
        @foreach ($grupos as $grupo => $filas)
            <h3 style="margin: 1rem 0 0.5rem; font-weight: 600;">{{ $grupo }}</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <tbody>
                        @foreach ($filas as $fila)
                            <tr style="border-top: 1px solid rgba(127, 127, 127, 0.2);">
                                <th scope="row" style="text-align: left; font-weight: 500; padding: 0.5rem 0.75rem; width: 34%;">{{ $fila['nombre'] }}</th>
                                <td style="padding: 0.5rem 0.75rem; width: 7rem;">
                                    <x-filament::badge :color="['correcto' => 'success', 'aviso' => 'warning', 'fallo' => 'danger'][$fila['estado']]">
                                        {{ ['correcto' => 'Correcto', 'aviso' => 'Aviso', 'fallo' => 'Falta'][$fila['estado']] }}
                                    </x-filament::badge>
                                </td>
                                <td style="padding: 0.5rem 0.75rem;">{{ $fila['valor'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </x-filament::section>
</x-filament-panels::page>
