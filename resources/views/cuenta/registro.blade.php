@php use App\Support\RutaCuenta; use App\Dominio\Clientes\TipoCliente; @endphp
<x-cuenta-marco :titulo="__('cuenta.registro_titulo')" :texto="__('cuenta.registro_texto')" :alternos="$alternos">
    <form method="POST" action="{{ RutaCuenta::url('registro') }}" class="formulario-cuenta" novalidate>
        @csrf
        @include('cuenta._campo', ['nombre' => 'nombre', 'etiqueta' => __('cuenta.nombre'), 'tipo' => 'text', 'autocompletar' => 'name'])
        @include('cuenta._campo', ['nombre' => 'correo', 'etiqueta' => __('cuenta.correo'), 'tipo' => 'email', 'autocompletar' => 'email'])
        <fieldset class="grupo-cuenta">
            <legend>{{ __('cuenta.tipo') }}</legend>
            <div class="opciones-tipo">
                @foreach (TipoCliente::cases() as $tipo)
                    <label><input type="radio" name="tipo" value="{{ $tipo->value }}" @checked(old('tipo', 'particular') === $tipo->value)> <span>{{ $tipo->getLabel() }}</span></label>
                @endforeach
            </div>
            @error('tipo')<p class="error-campo" id="error-tipo">{{ $message }}</p>@enderror
        </fieldset>
        @include('cuenta._campo', ['nombre' => 'contrasena', 'etiqueta' => __('cuenta.contrasena'), 'tipo' => 'password', 'autocompletar' => 'new-password', 'ayuda' => __('cuenta.contrasena_ayuda')])
        @include('cuenta._campo', ['nombre' => 'contrasena_confirmation', 'etiqueta' => __('cuenta.contrasena_repetir'), 'tipo' => 'password', 'autocompletar' => 'new-password'])
        <label class="casilla-cuenta"><input type="checkbox" name="marketing" value="1" @checked(old('marketing'))> <span>{{ __('cuenta.marketing') }}</span></label>
        <p class="nota-cuenta">{{ __('cuenta.privacidad_aviso') }}</p>
        <button type="submit" class="boton principal">{{ __('cuenta.registro') }}</button>
    </form>
    <p class="enlaces-cuenta"><span>{{ __('cuenta.con_cuenta') }} <a href="{{ RutaCuenta::url('entrar') }}">{{ __('cuenta.entrar') }}</a></span></p>
</x-cuenta-marco>
