@php use App\Support\RutaCuenta; @endphp
<x-cuenta-area :titulo="__('cuenta.mis_disenos')" :texto="__('cuenta.disenos_texto')" :alternos="$alternos" seccion="disenos">
    @if ($disenos->isEmpty())
        <p class="claro">{{ __('cuenta.sin_disenos') }} <a href="{{ route('portada') }}#prendas">{{ __('cuenta.ver_prendas') }}</a></p>
    @else
        <ul class="mis-disenos">
            @foreach ($disenos as $d)
                <li>
                    <img src="{{ $d['miniatura'] }}" alt="" loading="lazy" width="96" height="96">
                    <span>{{ $d['nombre'] }}</span>
                    <small>{{ $d['ancho_px'] }} × {{ $d['alto_px'] }} px</small>
                    <form method="POST" action="{{ RutaCuenta::url('disenos.borrar', ['diseno' => $d['uuid']]) }}" onsubmit="return confirm(@js(__('cuenta.borrar_diseno_confirmar', ['nombre' => $d['nombre']])))">
                        @csrf @method('DELETE')
                        <button type="submit" class="boton-texto peligro">{{ __('cuenta.borrar') }}<span class="oculto"> · {{ $d['nombre'] }}</span></button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</x-cuenta-area>
