@php $incidencia = $getRecord(); @endphp
@if ($incidencia->fotos->isEmpty())
    <p style="opacity: .7;">Sin fotos.</p>
@else
    <div style="display: flex; flex-wrap: wrap; gap: .75rem;">
        @foreach ($incidencia->fotos as $foto)
            <a href="{{ route('backend.pedidos.foto-incidencia', ['pedido' => $incidencia->pedido->uuid, 'foto' => $foto->id]) }}" target="_blank" rel="noopener">
                <img src="{{ route('backend.pedidos.foto-incidencia', ['pedido' => $incidencia->pedido->uuid, 'foto' => $foto->id]) }}" alt="Foto {{ $loop->iteration }}" style="width: 180px; height: 180px; object-fit: cover; border-radius: .5rem;" loading="lazy">
            </a>
        @endforeach
    </div>
@endif
