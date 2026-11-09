# Envíos e incidencias

@include('legal.es._titular')

## Envíos

El plazo de entrega se calcula con el tiempo de producción de cada prenda, los días extra según las unidades y el plazo del transportista, en días laborables. La fecha estimada aparece en tu pedido.

@if ($envios === [])
{!! $f(null, 'destinos de envío activos (se configuran en Ajustes → Envío)') !!}
@else
| Destino | Plazo del transportista | Envío gratis |
| --- | --- | --- |
@foreach ($envios as $envio)
| {{ $envio['zona'] }} | {{ $envio['dias'] }} días laborables | {{ $envio['gratis'] ? 'desde '.$envio['gratis'].' sin IVA' : '—' }} |
@endforeach
@endif

El precio del envío depende del destino y del peso del pedido, y se muestra antes de confirmar. Cuando el pedido sale te enviamos el transportista y el número de seguimiento.

## Incidencias

Si algo llega mal, ábrela desde la página de tu pedido en los **{{ $diasIncidencia }} días** siguientes a la entrega: cuéntanos qué pasa y añade fotos. Te responderemos con una reimpresión, un reembolso parcial o una explicación.

## Devoluciones

Al ser prendas personalizadas, no se admiten devoluciones por desistimiento (ver las [condiciones de venta](condiciones-de-venta)). Sí tienes la garantía legal de 3 años por falta de conformidad.
