{{-- Cabecera de los PDF (D205): logo a la izquierda; tipo y número del documento a la derecha (o $derecha). --}}
@php $logoPdf = 'data:image/png;base64,'.base64_encode((string) @file_get_contents(public_path('img/marca/logotipo.png'))); @endphp
<table class="cabecera"><tr>
    <td style="width: 50%;"><img class="logo" src="{{ $logoPdf }}" alt="NextGen Apparel"></td>
    <td style="width: 50%; text-align: right;">
        <p class="doc-tipo">{{ $tipo }}</p>
        <p class="doc-numero">{{ $numero }}</p>
        @foreach ($datos ?? [] as $dato)<p class="doc-datos">{!! $dato !!}</p>@endforeach
    </td>
</tr></table>
<div class="linea-marca"></div>
