{{-- Correo de un pedido (D204): el de siempre con el resumen del pedido. --}}
@php
    // Logo incrustado en el correo (se ve aunque el cliente bloquee imágenes remotas); al previsualizar, su URL (D204).
    $logo = isset($message) && method_exists($message, 'embed') ? $message->embed(public_path('img/marca/logotipo.png')) : asset('img/marca/logotipo.png');
@endphp
<x-mail::message :logo="$logo">
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Whoops!')
@else
# @lang('Hello!')
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Resumen del pedido (D204): lo que lleva y su total, con IVA. --}}
@if (! empty($resumen))
<x-mail::table>
| {{ __('correos.resumen_prenda') }} | {{ __('correos.resumen_unidades') }} |
|:--|--:|
@foreach ($resumen['lineas'] as $l)
| **{{ $l['nombre'] }}**{{ $l['detalle'] ? ' · '.$l['detalle'] : '' }} | {{ $l['unidades'] }} |
@endforeach
@if ($resumen['envio'] !== null)
| {{ __('correos.resumen_envio') }} | {{ $resumen['envio'] }} |
@endif
| **{{ __('correos.resumen_total') }}** | **{{ $resumen['total'] }}** |
</x-mail::table>
@endif

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
@lang('Regards,')<br>
{{ config('app.name') }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
@lang(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".
    'into your web browser:',
    [
        'actionText' => $actionText,
    ]
) <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
