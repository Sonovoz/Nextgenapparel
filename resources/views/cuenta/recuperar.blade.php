@php use App\Support\RutaCuenta; @endphp
<x-cuenta-marco :titulo="__('cuenta.recuperar_titulo')" :texto="__('cuenta.recuperar_texto')" :alternos="$alternos">
    <form method="POST" action="{{ RutaCuenta::url('recuperar') }}" class="formulario-cuenta" novalidate>
        @csrf
        @include('cuenta._campo', ['nombre' => 'correo', 'etiqueta' => __('cuenta.correo'), 'tipo' => 'email', 'autocompletar' => 'email'])
        <button type="submit" class="boton principal">{{ __('cuenta.enviar_enlace') }}</button>
    </form>
    <p class="enlaces-cuenta"><a href="{{ RutaCuenta::url('entrar') }}">{{ __('cuenta.volver_entrar') }}</a></p>
</x-cuenta-marco>
