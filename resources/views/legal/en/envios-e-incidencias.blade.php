# Shipping and issues

@include('legal.en._titular')

## Shipping

The delivery time adds up the production time of each garment, extra days by quantity and the carrier's time, in working days. The estimated date is shown on your order.

@if ($envios === [])
{!! $f(null, 'destinos de envío activos (se configuran en Ajustes → Envío)') !!}
@else
| Destination | Carrier time | Free shipping |
| --- | --- | --- |
@foreach ($envios as $envio)
| {{ $envio['zona'] }} | {{ $envio['dias'] }} working days | {{ $envio['gratis'] ? 'from '.$envio['gratis'].' excl. VAT' : '—' }} |
@endforeach
@endif

The shipping price depends on the destination and the weight of the order and is shown before you confirm. When the order ships we send you the carrier and the tracking number.

## Issues

If something arrives wrong, report it from your order page within **{{ $diasIncidencia }} days** of delivery: tell us what happened and add photos. We will reply with a reprint, a partial refund or an explanation.

## Cancellations and returns

- **Cancelling:** from the order page, until it goes into printing. **Once the garments are printed, the order can no longer be cancelled.**
- **Returns:** as the garments are personalised, returns by withdrawal or because you changed your mind, size or colour are not accepted (see the [terms of sale](condiciones-de-venta)).
- **If the mistake is ours:** a reprint or a refund at no cost to you, with the 3-year legal guarantee for lack of conformity.
