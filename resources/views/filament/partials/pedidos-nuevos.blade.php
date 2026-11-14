{{-- D240: aviso de pedidos nuevos (pagados y sin empezar) arriba de todas las páginas del panel. --}}
@php
    use App\Dominio\Precios\Formato;
    use App\Filament\Resources\Pedidos\PedidoResource;
    use App\Models\Pedido;

    $usuario = auth()->user();
    $nuevos = $usuario !== null && $usuario->can('viewAny', Pedido::class) ? PedidoResource::nuevos() : collect();
    $total = $nuevos->isEmpty() ? 0 : rescue(fn () => PedidoResource::sinVer()->count(), $nuevos->count(), report: false);
@endphp
@if ($total > 0)
    <style>
        .aviso-pedidos { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin: 16px 0 6px; padding: 14px 18px; border-radius: 14px; background: linear-gradient(90deg, #15803d, #16a34a); color: #fff; box-shadow: 0 10px 30px -12px rgba(22, 163, 74, .6); }
        .aviso-pedidos-icono { position: relative; display: inline-flex; width: 38px; height: 38px; align-items: center; justify-content: center; border-radius: 99px; background: rgba(255, 255, 255, .18); font-size: 20px; flex-shrink: 0; }
        .aviso-pedidos-icono::after { content: ""; position: absolute; inset: -4px; border-radius: inherit; border: 2px solid rgba(255, 255, 255, .55); animation: aviso-pedidos 1.8s ease-out infinite; }
        @keyframes aviso-pedidos { from { transform: scale(.85); opacity: 1; } to { transform: scale(1.35); opacity: 0; } }
        @media (prefers-reduced-motion: reduce) { .aviso-pedidos-icono::after { animation: none; } }
        .aviso-pedidos-texto { flex: 1 1 260px; min-width: 0; }
        .aviso-pedidos-texto strong { display: block; font-size: 16px; }
        .aviso-pedidos-texto span { font-size: 13px; opacity: .9; }
        .aviso-pedidos a.aviso-pedidos-boton { padding: 9px 16px; border-radius: 99px; background: #fff; color: #14532d; font-weight: 600; font-size: 13px; text-decoration: none; white-space: nowrap; }
        .aviso-pedidos a.aviso-pedidos-boton:hover { background: #dcfce7; }
    </style>
    <div class="aviso-pedidos" role="status" id="aviso-pedidos-nuevos">
        <span class="aviso-pedidos-icono" aria-hidden="true">🛍</span>
        <div class="aviso-pedidos-texto">
            @if ($total === 1)
                @php $p = $nuevos->first(); @endphp
                <strong>Nuevo pedido pagado: {{ $p->numero }}</strong>
                <span>{{ $p->nombre_fiscal ?: $p->correo }} · {{ Formato::euros($p->total, 'es') }} · {{ $p->created_at?->format('d/m/Y H:i') }}. El aviso se quita al abrirlo.</span>
            @else
                <strong>{{ $total }} pedidos nuevos pagados</strong>
                <span>{{ $nuevos->pluck('numero')->implode(', ') }}{{ $total > $nuevos->count() ? '…' : '' }}. Cada aviso se quita al abrir su pedido.</span>
            @endif
        </div>
        <a class="aviso-pedidos-boton" href="{{ $total === 1 ? PedidoResource::getUrl('view', ['record' => $nuevos->first()]) : PedidoResource::getUrl('index', ['tab' => 'pagados']) }}">{{ $total === 1 ? 'Ver pedido' : 'Ver pedidos pagados' }}</a>
    </div>
@endif
