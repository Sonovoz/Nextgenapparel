{{-- Pie de los PDF (D205, D215): quién emite, la página y, en los del cliente, las preguntas frecuentes y las condiciones de venta. --}}
<div class="pie"><table><tr>
    <td>{{ $texto }}@if (! empty($enlaces))<br>@foreach ($enlaces as $etiqueta => $url)<a href="{{ $url }}">{{ $etiqueta }}</a>@if (! $loop->last) &nbsp;·&nbsp; @endif @endforeach @endif</td>
    <td style="text-align: right; width: 22mm;">{{ __('facturas.pagina', [], $idioma ?? app()->getLocale()) }} <span class="pagina"></span></td>
</tr></table></div>
