@php use App\Support\RutaCuenta; use App\Dominio\Clientes\TipoDireccion; @endphp
<x-cuenta-area :titulo="$direccion->exists ? __('cuenta.editar_direccion') : __('cuenta.nueva_direccion')" :alternos="$alternos" seccion="direcciones">
    <form method="POST" action="{{ $direccion->exists ? RutaCuenta::url('direcciones.actualizar', ['direccion' => $direccion->uuid]) : RutaCuenta::url('direcciones.guardar') }}" class="formulario-cuenta" novalidate>
        @csrf
        @if ($direccion->exists) @method('PUT') @endif
        <fieldset class="grupo-cuenta" id="campo-tipo">
            <legend>{{ __('cuenta.tipo_direccion') }}</legend>
            <div class="opciones-tipo">
                @foreach (TipoDireccion::cases() as $tipo)
                    <label><input type="radio" name="tipo" value="{{ $tipo->value }}" @checked(old('tipo', $direccion->tipo?->value) === $tipo->value)> <span>{{ $tipo->getLabel() }}</span></label>
                @endforeach
            </div>
        </fieldset>
        @include('cuenta._campo', ['nombre' => 'nombre', 'etiqueta' => __('cuenta.nombre'), 'tipo' => 'text', 'autocompletar' => 'name', 'valor' => $direccion->nombre])
        @include('cuenta._campo', ['nombre' => 'empresa', 'etiqueta' => __('cuenta.empresa'), 'tipo' => 'text', 'autocompletar' => 'organization', 'valor' => $direccion->empresa, 'opcional' => true])
        @include('cuenta._campo', ['nombre' => 'direccion', 'etiqueta' => __('cuenta.direccion'), 'tipo' => 'text', 'autocompletar' => 'address-line1', 'valor' => $direccion->direccion])
        @include('cuenta._campo', ['nombre' => 'direccion_2', 'etiqueta' => __('cuenta.direccion_2'), 'tipo' => 'text', 'autocompletar' => 'address-line2', 'valor' => $direccion->direccion_2, 'opcional' => true])
        <div class="campos-dos">
            @include('cuenta._campo', ['nombre' => 'codigo_postal', 'etiqueta' => __('cuenta.codigo_postal'), 'tipo' => 'text', 'autocompletar' => 'postal-code', 'valor' => $direccion->codigo_postal])
            @include('cuenta._campo', ['nombre' => 'localidad', 'etiqueta' => __('cuenta.localidad'), 'tipo' => 'text', 'autocompletar' => 'address-level2', 'valor' => $direccion->localidad])
        </div>
        <div class="campos-dos">
            @include('cuenta._campo', ['nombre' => 'provincia', 'etiqueta' => __('cuenta.provincia'), 'tipo' => 'text', 'autocompletar' => 'address-level1', 'valor' => $direccion->provincia, 'opcional' => true])
            <div class="campo-cuenta">
                <label for="campo-pais">{{ __('cuenta.pais') }}</label>
                <select id="campo-pais" name="pais" autocomplete="country">
                    @foreach ($paises as $codigo => $nombre)
                        <option value="{{ $codigo }}" @selected(old('pais', $direccion->pais) === $codigo)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @include('cuenta._campo', ['nombre' => 'telefono', 'etiqueta' => __('cuenta.telefono'), 'tipo' => 'tel', 'autocompletar' => 'tel', 'valor' => $direccion->telefono, 'opcional' => true])
        <label class="casilla-cuenta"><input type="checkbox" name="predeterminada" value="1" @checked(old('predeterminada', $direccion->predeterminada))> <span>{{ __('cuenta.usar_predeterminada') }}</span></label>
        <div class="botones-formulario">
            <button type="submit" class="boton principal">{{ __('cuenta.guardar') }}</button>
            <a class="boton" href="{{ RutaCuenta::url('direcciones') }}">{{ __('cuenta.cancelar') }}</a>
        </div>
    </form>
</x-cuenta-area>
