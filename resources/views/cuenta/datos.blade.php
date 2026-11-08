@php use App\Support\RutaCuenta; use App\Dominio\Clientes\TipoCliente; @endphp
<x-cuenta-area :titulo="__('cuenta.datos_titulo')" :texto="__('cuenta.datos_texto')" :alternos="$alternos" seccion="datos">
    <form method="POST" action="{{ RutaCuenta::url('datos.guardar') }}" class="formulario-cuenta" novalidate>
        @csrf @method('PUT')
        @include('cuenta._campo', ['nombre' => 'nombre', 'etiqueta' => __('cuenta.nombre'), 'tipo' => 'text', 'autocompletar' => 'name', 'valor' => $cliente->nombre])
        @include('cuenta._campo', ['nombre' => 'correo', 'etiqueta' => __('cuenta.correo'), 'tipo' => 'email', 'autocompletar' => 'email', 'valor' => $usuario->email, 'ayuda' => $usuario->correo_pendiente ? __('cuenta.correo_pendiente', ['correo' => $usuario->correo_pendiente]) : __('cuenta.correo_cambio_ayuda')])
        @include('cuenta._campo', ['nombre' => 'contrasena_actual', 'etiqueta' => __('cuenta.contrasena_actual'), 'tipo' => 'password', 'autocompletar' => 'current-password', 'ayuda' => __('cuenta.contrasena_actual_ayuda'), 'opcional' => true])
        <fieldset class="grupo-cuenta" id="campo-tipo">
            <legend>{{ __('cuenta.tipo') }}</legend>
            <div class="opciones-tipo">
                @foreach (TipoCliente::cases() as $tipo)
                    <label><input type="radio" name="tipo" value="{{ $tipo->value }}" @checked(old('tipo', $cliente->tipo->value) === $tipo->value)> <span>{{ $tipo->getLabel() }}</span></label>
                @endforeach
            </div>
        </fieldset>
        @include('cuenta._campo', ['nombre' => 'nif', 'etiqueta' => __('cuenta.nif'), 'tipo' => 'text', 'autocompletar' => 'off', 'valor' => $cliente->nif, 'ayuda' => __('cuenta.nif_ayuda'), 'opcional' => true])
        @include('cuenta._campo', ['nombre' => 'telefono', 'etiqueta' => __('cuenta.telefono'), 'tipo' => 'tel', 'autocompletar' => 'tel', 'valor' => $cliente->telefono, 'opcional' => true])
        <div class="campo-cuenta">
            <label for="campo-idioma">{{ __('cuenta.idioma') }}</label>
            <select id="campo-idioma" name="idioma">
                @foreach (config('idiomas.disponibles') as $codigo => $nombre)
                    <option value="{{ $codigo }}" @selected(old('idioma', $cliente->idioma) === $codigo)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        @if ($cliente->tipo->admiteRecargo())
            <p class="nota-cuenta"><strong>{{ __('cuenta.recargo') }}:</strong> {{ $cliente->recargo_equivalencia ? __('cuenta.recargo_si') : __('cuenta.recargo_no') }}</p>
        @endif
        <label class="casilla-cuenta"><input type="checkbox" name="marketing" value="1" @checked(session()->hasOldInput() ? (bool) old('marketing') : $cliente->marketing_consentido_en !== null)> <span>{{ __('cuenta.marketing') }}</span></label>
        <button type="submit" class="boton principal">{{ __('cuenta.guardar') }}</button>
    </form>

    <section class="zona-borrado" aria-labelledby="titulo-borrado">
        <h2 class="subtitulo-cuenta" id="titulo-borrado">{{ __('cuenta.borrado_titulo') }}</h2>
        @if ($cliente->borrado_solicitado_en)
            <p class="aviso-cuenta verificar">{{ __('cuenta.borrado_solicitado', ['fecha' => $cliente->borrado_solicitado_en->translatedFormat(__('cuenta.formato_fecha'))]) }}</p>
        @else
            <p class="claro">{{ __('cuenta.borrado_texto') }}</p>
            <details class="borrado-detalle" @if ($errors->has('contrasena_borrado')) open @endif>
                <summary class="boton">{{ __('cuenta.borrado_boton') }}</summary>
                <form method="POST" action="{{ RutaCuenta::url('borrado') }}" class="formulario-cuenta" novalidate>
                    @csrf
                    @include('cuenta._campo', ['nombre' => 'contrasena_borrado', 'etiqueta' => __('cuenta.contrasena'), 'tipo' => 'password', 'autocompletar' => 'current-password', 'ayuda' => __('cuenta.borrado_confirmar_ayuda')])
                    <button type="submit" class="boton boton-peligro">{{ __('cuenta.borrado_boton') }}</button>
                </form>
            </details>
        @endif
    </section>
</x-cuenta-area>
