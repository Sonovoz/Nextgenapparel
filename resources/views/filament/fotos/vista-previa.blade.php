@php
    /** @var \App\Models\Foto|null $foto */
    $foto = $getRecord();
    $filas = collect(is_array($zonasFormulario ?? null) ? $zonasFormulario : [])->filter(fn ($z) => is_array($z));
    $nombres = \App\Models\ZonaImpresion::whereIn('id', $filas->pluck('zona_impresion_id')->filter(fn ($id) => is_numeric($id)))->get()->keyBy('id');
    // Valores del formulario (en % de la foto), aunque aún no se hayan guardado.
    $zonas = $filas->map(fn ($z) => [
        'x' => is_numeric($z['x'] ?? null) ? (float) $z['x'] : 0, 'y' => is_numeric($z['y'] ?? null) ? (float) $z['y'] : 0,
        'ancho' => is_numeric($z['ancho'] ?? null) ? (float) $z['ancho'] : 0, 'alto' => is_numeric($z['alto'] ?? null) ? (float) $z['alto'] : 0,
        'zona' => is_numeric($z['zona_impresion_id'] ?? null) ? ($nombres[(int) $z['zona_impresion_id']] ?? null) : null,
    ]);
    $ejemplo = $ejemplo ?? 'cuadrado';
@endphp

@if ($foto)
    <div>
        <p style="font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Vista previa con un diseño de ejemplo</p>
        <div style="position: relative; display: inline-block; max-width: 420px; width: 100%; background: #F3F2EF; border-radius: 0.5rem; overflow: hidden;">
            <img src="{{ $foto->url() }}" alt="" style="display: block; width: 100%; height: auto;">
            @foreach ($zonas as $zona)
                <div style="position: absolute; left: {{ $zona['x'] }}%; top: {{ $zona['y'] }}%; width: {{ $zona['ancho'] }}%; height: {{ $zona['alto'] }}%; border: 1px dashed #E11D48;">
                    <span style="position: absolute; left: 0; bottom: -1.05rem; font-size: 0.65rem; line-height: 1; padding: 1px 4px; border-radius: 3px; background: rgba(255,255,255,.9); color: #E11D48; white-space: nowrap; z-index: 1;">
                        {{ $zona['zona']?->nombre }} @if ($zona['zona']) · {{ $zona['zona']->ancho_max_mm / 10 }} × {{ $zona['zona']->alto_max_mm / 10 }} cm @endif
                    </span>
                    {{-- Mismo criterio que el visor de la tienda: encajado, centrado y arriba (D99). --}}
                    <svg data-ejemplo="{{ $ejemplo }}" style="position: absolute; inset: 0; width: 100%; height: 100%;" preserveAspectRatio="xMidYMin meet"
                         @switch($ejemplo)
                             @case('horizontal') viewBox="0 0 300 70" @break
                             @case('vertical') viewBox="0 0 100 140" @break
                             @default viewBox="0 0 100 100"
                         @endswitch
                         aria-hidden="true">
                        @switch($ejemplo)
                            @case('horizontal')
                                <rect x="0" y="0" width="300" height="70" rx="6" fill="#1E3A8A" opacity=".9"/>
                                <text x="150" y="47" text-anchor="middle" font-family="Arial, sans-serif" font-weight="700" font-size="38" fill="#F97316">TU MARCA</text>
                                @break
                            @case('vertical')
                                <rect x="0" y="0" width="100" height="140" rx="4" fill="#1E3A8A" opacity=".9"/>
                                <circle cx="50" cy="48" r="28" fill="#F97316"/>
                                <text x="50" y="110" text-anchor="middle" font-family="Arial, sans-serif" font-weight="700" font-size="17" fill="#FFFFFF">TU MARCA</text>
                                @break
                            @default
                                <circle cx="50" cy="50" r="46" fill="#1E3A8A" opacity=".9"/>
                                <circle cx="50" cy="50" r="30" fill="#F97316"/>
                                <text x="50" y="57" text-anchor="middle" font-family="Arial, sans-serif" font-weight="700" font-size="18" fill="#FFFFFF">TU</text>
                        @endswitch
                    </svg>
                </div>
            @endforeach
        </div>
    </div>
@endif
