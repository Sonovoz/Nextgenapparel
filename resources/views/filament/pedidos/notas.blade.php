@php
    $pedido = $getRecord();
    // Como en WooCommerce: lo más reciente arriba; ámbar las privadas, azul las del cliente, gris lo del sistema.
    $apuntes = $pedido->notas()->with('autor')->get()->map(fn ($n) => ['fecha' => $n->created_at, 'texto' => $n->texto, 'tipo' => $n->tipo, 'autor' => $n->autor?->name ?? 'Sistema'])
        ->concat($pedido->historial()->with('usuario')->get()->map(fn ($h) => ['fecha' => $h->created_at, 'texto' => $h->estado->etiqueta().($h->nota ? ' · '.$h->nota : ''), 'tipo' => 'sistema', 'autor' => $h->usuario?->name ?? 'Sistema']))
        ->sortByDesc('fecha')->values();
    $colores = ['privada' => 'rgba(245, 158, 11, .15)', 'cliente' => 'rgba(59, 130, 246, .15)', 'sistema' => 'rgba(127, 127, 127, .12)'];
@endphp
<div style="display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
    @forelse ($apuntes as $a)
        <div style="padding: 10px 12px; border-radius: 8px; background: {{ $colores[$a['tipo']] }};">
            <div style="white-space: pre-line;">{{ $a['texto'] }}</div>
            <div style="opacity: .7; margin-top: 4px;">{{ $a['fecha']->format('d/m/Y H:i') }} · {{ $a['autor'] }}@if ($a['tipo'] === 'cliente') · para el cliente @elseif ($a['tipo'] === 'privada') · privada @endif</div>
        </div>
    @empty
        <p style="opacity: .7;">Sin notas.</p>
    @endforelse
</div>
