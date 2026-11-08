@php use App\Support\RutaCuenta; @endphp
<x-cuenta-marco :titulo="__('cuenta.entrar_titulo')" :texto="__('cuenta.entrar_texto')" :alternos="$alternos">
    <form method="POST" action="{{ RutaCuenta::url('entrar') }}" class="formulario-cuenta" novalidate>
        @csrf
        @include('cuenta._campo', ['nombre' => 'correo', 'etiqueta' => __('cuenta.correo'), 'tipo' => 'email', 'autocompletar' => 'email'])
        @include('cuenta._campo', ['nombre' => 'contrasena', 'etiqueta' => __('cuenta.contrasena'), 'tipo' => 'password', 'autocompletar' => 'current-password'])
        <label class="casilla-cuenta"><input type="checkbox" name="recordar" value="1" @checked(old('recordar'))> <span>{{ __('cuenta.recordar') }}</span></label>
        <button type="submit" class="boton principal">{{ __('cuenta.entrar') }}</button>
    </form>
    <p class="enlaces-cuenta">
        <a href="{{ RutaCuenta::url('recuperar') }}">{{ __('cuenta.olvidaste') }}</a>
        <span>{{ __('cuenta.sin_cuenta') }} <a href="{{ RutaCuenta::url('registro') }}">{{ __('cuenta.registro') }}</a></span>
    </p>
</x-cuenta-marco>
