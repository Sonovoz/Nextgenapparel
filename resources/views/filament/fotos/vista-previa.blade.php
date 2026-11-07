@php
    /** @var \App\Models\Foto|null $foto */
    $foto = $getRecord();
    $zonas = $foto?->zonas()->with('zona')->get() ?? collect();
@endphp

@if ($foto)
    <div>
        <p style="font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Vista previa</p>
        <div style="position: relative; display: inline-block; max-width: 320px; width: 100%; background: #F3F2EF; border-radius: 0.5rem; overflow: hidden;">
            <img src="{{ $foto->url() }}" alt="" style="display: block; width: 100%; height: auto;">
            @foreach ($zonas as $zona)
                <div style="position: absolute; left: {{ $zona->x / 100 }}%; top: {{ $zona->y / 100 }}%; width: {{ $zona->ancho / 100 }}%; height: {{ $zona->alto / 100 }}%; border: 2px dashed #E11D48; background: rgba(225, 29, 72, 0.08);">
                    <span style="position: absolute; left: 0; top: -1.25rem; font-size: 0.7rem; color: #E11D48; white-space: nowrap;">{{ $zona->zona?->nombre }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif
