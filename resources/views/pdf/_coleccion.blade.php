{{-- «Tu colección» (D215): cada prenda con la foto de cada cara y lo que lleva; tarjetas claras y con aire.
     $lineas: list<array{nombre, detalle, fotos: list<array{cara, src}>, importe?}> --}}
@foreach ($lineas as $l)
    <div class="prenda"><table><tr>
        @if ($l['fotos'] !== [])
            <td style="width: {{ count($l['fotos']) * 45 }}mm;"><table><tr>
                @foreach ($l['fotos'] as $foto)
                    <td style="width: 45mm;"><div class="foto-prenda">
                        <img src="{{ $foto['src'] }}" alt="">
                        <p class="foto-cara">{{ __('pdf.cara_'.$foto['cara'], [], $idioma) }}</p>
                    </div></td>
                @endforeach
            </tr></table></td>
        @endif
        <td style="padding-left: 2mm;">
            <p class="nombre">{{ $l['nombre'] }}</p>
            @if (! empty($l['detalle']))<p class="tenue" style="margin-top: 1.2mm;">{{ $l['detalle'] }}</p>@endif
            @if (! empty($l['extra']))<p class="pequena tenue" style="margin-top: 2mm;">{{ $l['extra'] }}</p>@endif
        </td>
        @if (isset($l['importe']))<td class="num" style="width: 28mm;"><p class="importe">{{ $l['importe'] }}</p></td>@endif
    </tr></table></div>
@endforeach
