{{-- Campo de formulario accesible: etiqueta, ayuda y error ligados con aria-describedby. --}}
@php
    $id = 'campo-'.$nombre;
    $describe = collect([isset($ayuda) ? $id.'-ayuda' : null, $errors->has($nombre) ? $id.'-error' : null])->filter()->join(' ');
@endphp
<div class="campo-cuenta">
    <label for="{{ $id }}">{{ $etiqueta }}@if (! empty($opcional)) <span class="claro">({{ __('cuenta.opcional') }})</span>@endif</label>
    <input id="{{ $id }}" name="{{ $nombre }}" type="{{ $tipo }}" autocomplete="{{ $autocompletar }}"
           @if ($tipo !== 'password') value="{{ old($nombre, $valor ?? '') }}" @endif
           @if ($describe) aria-describedby="{{ $describe }}" @endif
           @error($nombre) aria-invalid="true" @enderror @if (empty($opcional)) required @endif>
    @isset($ayuda)<p class="ayuda-campo" id="{{ $id }}-ayuda">{{ $ayuda }}</p>@endisset
    @error($nombre)<p class="error-campo" id="{{ $id }}-error">{{ $message }}</p>@enderror
</div>
