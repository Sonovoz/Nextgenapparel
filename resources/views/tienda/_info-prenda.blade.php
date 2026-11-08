{{-- Información de la prenda: pestaña «Prenda» del configurador ($enEstudio) o ficha clásica. --}}
<div>
    <p class="etiqueta">{{ $familia->nombre }} · DTI Textil</p>
    @if ($enEstudio)<h2 class="titulo-prenda">{{ $prenda->nombre }}</h2>@else<h1>{{ $prenda->nombre }}</h1>@endif
    @if ($precio)
        <div class="precio">
            <span class="suave">{{ __('tienda.desde') }}</span>
            <strong>{{ $precio }}</strong>
            <span class="suave">{{ __('tienda.por_unidad') }} · {{ __('tienda.con_impresion') }} · {{ __('tienda.iva_incluido') }}</span>
        </div>
    @endif
    @if ($prenda->descripcion)
        <p style="margin: 16px 0 0; color: var(--tinta-suave);">{{ $prenda->descripcion }}</p>
    @endif
</div>

@if (count($colores) > 0 && ! $enEstudio)
    <div>
        <p class="suave" style="margin-bottom: 10px;">{{ __('tienda.color') }} · <strong id="nombre-color" style="color: var(--tinta); font-weight: 500;">{{ $primerColor['nombre'] }}</strong></p>
        <div class="muestras" role="radiogroup" aria-label="{{ __('tienda.color') }}">
            @foreach ($colores as $i => $color)
                <button type="button" class="muestra" role="radio" aria-checked="{{ $i === 0 ? 'true' : 'false' }}" aria-label="{{ $color['nombre'] }}" data-color="{{ $i }}">
                    <span style="background: {{ $color['hex'] }};"></span>
                </button>
            @endforeach
        </div>
    </div>
@endif

<div>
    <p class="suave" style="margin-bottom: 10px;">{{ __('tienda.tallas_disponibles') }}</p>
    <div class="chips">
        @foreach ($tallas as $talla)
            <span class="chip">{{ $talla }}</span>
        @endforeach
    </div>
    <p class="suave" style="margin-top: 10px; font-size: 13px;">{{ __('tienda.tallas_nota') }}</p>
</div>

@if ($prenda->zonas->isNotEmpty() && ! $enEstudio)
    <div>
        <p class="suave" style="margin-bottom: 10px;">{{ __('tienda.donde_imprimimos') }}</p>
        <div class="zonas">
            @foreach ($prenda->zonas as $zona)
                <div class="zona-tarjeta">
                    <span style="font-size: 14px; font-weight: 500;">{{ $zona->nombre }}</span>
                    <span class="suave" style="font-size: 12px;">{{ __('tienda.hasta', ['medida' => $numero($zona->ancho_max_mm).' × '.$numero($zona->alto_max_mm).' cm']) }}</span>
                </div>
            @endforeach
        </div>
        <p class="suave" style="margin-top: 10px; font-size: 13px;">{{ __('tienda.zonas_nota') }}</p>
    </div>
@endif

@if ($enEstudio)
    <p class="suave">{{ __('tienda.color') }} · <strong id="nombre-color" style="color: var(--tinta); font-weight: 500;">{{ $primerColor['nombre'] ?? '' }}</strong></p>
@endif

<div style="display: flex; flex-direction: column; gap: 8px;">
    @if ($enEstudio)
        {{-- En el configurador no hace falta botón: ya estás diseñando. --}}
    @elseif ($conTuMarca)
        <button type="button" class="boton principal" data-abrir-estudio style="width: 100%;">{{ __('tienda.abrir_estudio') }}</button>
    @else
        <button type="button" class="boton inactivo" disabled style="width: 100%;">{{ __('tienda.personalizar') }}</button>
    @endif
    <p class="suave" id="proximamente" style="font-size: 13px;">{{ __('tienda.proximamente') }} {{ __('tienda.minimo') }}</p>
</div>

@unless ($enEstudio)
<ol class="pasos-ficha">
    <li>{{ __('tienda.paso_1') }}</li>
    <li>{{ __('tienda.paso_2') }}</li>
    <li>{{ __('tienda.paso_3') }}</li>
</ol>
@endunless

<div>
    <details>
        <summary>{{ __('tienda.detalles') }}</summary>
        <ul>
            @if ($prenda->composicion)<li>{{ __('tienda.composicion') }}: {{ $prenda->composicion }}</li>@endif
            @if ($prenda->gramaje)<li>{{ __('tienda.gramaje') }}: {{ $prenda->gramaje }} g/m²</li>@endif
            @if ($prenda->detalles)<li>{{ $prenda->detalles }}</li>@endif
        </ul>
    </details>
    @if ($medidas->isNotEmpty())
        <details>
            <summary>{{ __('tienda.guia_tallas') }}</summary>
            <table>
                <thead><tr><th scope="col">{{ __('tienda.talla') }}</th><th scope="col">{{ __('tienda.largo') }}</th><th scope="col">{{ __('tienda.ancho') }}</th><th scope="col">{{ __('tienda.manga') }}</th></tr></thead>
                <tbody>
                    @foreach ($medidas as $medida)
                        <tr><th scope="row">{{ $medida->talla->nombre }}</th><td>{{ $cm($medida->largo_mm) }}</td><td>{{ $cm($medida->ancho_mm) }}</td><td>{{ $cm($medida->manga_mm) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
    <details>
        <summary>{{ __('tienda.zonas_calidad') }}</summary>
        <ul><li>{{ __('tienda.calidad_texto') }}</li></ul>
    </details>
    <details>
        <summary>{{ __('tienda.plazos') }}</summary>
        <ul><li>{{ __('tienda.plazo_produccion', ['dias' => $familia->plazo_base_dias]) }}</li><li>{{ __('tienda.plazo_envio') }}</li></ul>
    </details>
    <details>
        <summary>{{ __('tienda.garantia') }}</summary>
        <ul><li>{{ __('tienda.garantia_desistimiento') }}</li><li>{{ __('tienda.garantia_defecto') }}</li></ul>
    </details>
</div>

<p class="suave" style="font-size: 13px;">{{ __('tienda.pago_seguro') }}</p>
