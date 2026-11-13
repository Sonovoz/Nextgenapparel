{{-- Cabecera de los PDF (D205, D215): banda oscura con el logo en blanco; tipo y número del documento a la derecha. --}}
@php $logoPdf = 'data:image/png;base64,'.base64_encode((string) @file_get_contents(public_path('img/marca/logotipo-blanco.png'))); @endphp
<div class="hero"><table><tr>
    <td style="width: 52%;"><img class="logo" src="{{ $logoPdf }}" alt="Nextgenapparel"><div class="hero-linea"></div></td>
    <td style="width: 48%; text-align: right;">
        <p class="doc-tipo">{{ $tipo }}</p>
        <p class="doc-numero">{{ $numero }}</p>
        @foreach ($datos ?? [] as $dato)<p class="doc-datos">{!! $dato !!}</p>@endforeach
    </td>
</tr></table></div>
