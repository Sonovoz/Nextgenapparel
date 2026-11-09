# Condiciones de venta

@include('legal._titular')

## 1. Vendedor

{!! $f($e['razon_social'], 'razón social') !!}, NIF {!! $f($e['nif'], 'NIF') !!}, con domicilio en {!! $f($e['direccion'], 'domicilio') !!}, {!! $f($e['codigo_postal'], 'código postal') !!} {!! $f($e['localidad'], 'localidad') !!}. Contacto: {!! $f($e['correo'], 'correo de contacto') !!}.

Al confirmar un pedido aceptas estas condiciones, que quedan guardadas con él.

## 2. Productos y precios

- Todas las prendas **se imprimen con tu diseño cuando haces el pedido**. Antes de confirmar ves la maqueta, las medidas y la calidad de cada diseño.
- Los precios están en euros. Cada pedido tiene un mínimo de unidades por prenda, que se indica al configurarla. Las ofertas y los cupones se aplican en el carrito según sus condiciones.
- El envío y los impuestos (IVA y, si eres comerciante minorista, recargo de equivalencia) se muestran antes de confirmar.

## 3. Cómo se compra

1. Configura las prendas, colores, tallas y diseños y añádelos al carrito.
2. Escribe tus datos y la dirección de entrega: verás el envío y el total.
3. Acepta estas condiciones y confirma el pedido. Si compras sin cuenta, confirma tu correo con el enlace que te enviamos.
4. Paga el pedido. Si no se paga en {{ $diasSinPago }} días, se cancela solo.

## 4. Pago

El pago se hace en la página segura de **Redsys**, la plataforma de pago de los bancos, con tarjeta o con Bizum. Los datos de la tarjeta nunca llegan a nuestro servidor. El pedido se da por pagado cuando Redsys confirma el cobro, y entonces recibes la factura.

## 5. Revisión del diseño y fabricación

Tras el pago revisamos los diseños. Si alguno no se puede imprimir bien, te escribimos para que lo cambies. Si un diseño infringe derechos de terceros o una marca, podemos rechazar el pedido y te devolvemos el dinero.

## 6. Cancelación

Puedes cancelar el pedido desde su página **mientras no esté en impresión**. Si ya lo has pagado, te devolvemos el dinero en el mismo medio de pago y emitimos la factura rectificativa. Una vez empieza la impresión ya no se puede cancelar.

## 7. Sin derecho de desistimiento

Las prendas se fabrican **según tus indicaciones y personalizadas**, por lo que no tienen derecho de desistimiento (artículo 103.c del texto refundido de la Ley General para la Defensa de los Consumidores y Usuarios).

## 8. Garantía e incidencias

Las prendas tienen la **garantía legal de 3 años** por falta de conformidad. Si algo llega mal (un defecto de impresión, una prenda dañada, una talla o un color que no pediste, o falta alguna prenda), abre una **incidencia** desde la página de tu pedido en los {{ $diasIncidencia }} días siguientes a la entrega, con fotos si puedes. La resolvemos con una reimpresión, un reembolso parcial o, si no procede, te explicamos por qué. Pasado ese plazo puedes escribirnos a {!! $f($e['correo'], 'correo de contacto') !!} dentro de la garantía legal.

## 9. Atención al cliente y legislación

Puedes escribirnos a {!! $f($e['correo'], 'correo de contacto') !!}. Estas condiciones se rigen por la legislación española; el consumidor puede acudir a los juzgados de su domicilio.
