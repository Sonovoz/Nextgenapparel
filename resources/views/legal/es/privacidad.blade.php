# Política de privacidad

@include('legal.es._titular')

## 1. Responsable del tratamiento

| | |
| --- | --- |
| Responsable | {!! $f($e['razon_social'], 'razón social') !!} |
| NIF | {!! $f($e['nif'], 'NIF') !!} |
| Domicilio | {!! $f($e['direccion'], 'domicilio') !!}, {!! $f($e['codigo_postal'], 'código postal') !!} {!! $f($e['localidad'], 'localidad') !!} |
| Contacto | {!! $f($e['correo'], 'correo de contacto') !!} |

## 2. Qué datos tratamos, para qué y con qué base

| Datos | Para qué | Base legal |
| --- | --- | --- |
| Nombre, correo, teléfono, dirección de entrega y datos fiscales | Fabricar, enviar y facturar tu pedido y avisarte de cada paso | Ejecución del contrato (art. 6.1.b RGPD) |
| Datos del pedido y de la factura | Cumplir las obligaciones fiscales y contables | Obligación legal (art. 6.1.c RGPD) |
| Los diseños (PNG) que subes | Comprobar su calidad e imprimirlos en tu pedido | Ejecución del contrato |
| Las fotos de una incidencia (sin ubicación ni datos de la cámara: los quitamos al recibirlas) | Resolver la incidencia | Ejecución del contrato |
| Si creas una cuenta (es opcional): correo, nombre, contraseña cifrada y direcciones guardadas | Darte acceso a tus pedidos, diseños y facturas | Ejecución del contrato que aceptas al registrarte |
| La dirección IP con la que confirmas el pedido | Probar la aceptación de las condiciones | Interés legítimo (art. 6.1.f RGPD) |

**No pedimos ni guardamos los datos de tu tarjeta**: pagas en la página segura de Redsys, la plataforma de pago de los bancos. **No enviamos publicidad**, no hacemos perfiles ni decisiones automatizadas.

## 3. Quién recibe tus datos

- **Redsys y nuestro banco**, para cobrar el pedido (importe y número del pedido).
- **La empresa de transporte**, que recibe tu nombre, dirección y teléfono para entregar el paquete.
- **Los proveedores que alojan la web y envían los correos**, como encargados del tratamiento: {!! $f(null, 'proveedores de alojamiento y de correo (los indica la asesoría)') !!}.
- **La Administración Tributaria**, cuando la ley lo exija.

El equipo de producción solo ve qué imprimir, sin tus datos personales. Nunca vendemos ni cedemos tus datos.

## 4. Cuánto tiempo los guardamos

- **Pedidos y facturas**: los plazos que marca la ley mercantil y fiscal.
- **Diseños sin pedido**: se borran solos pasado el plazo indicado al subirlos.
- **Fotos de incidencias e IP del pedido**: {!! $f(null, 'plazo de conservación de las fotos de incidencias y de la IP (lo fija la asesoría)') !!}.
- **Cuenta**: mientras la mantengas. Si pides borrarla, se borra; los pedidos y facturas se conservan lo que exige la ley.

## 5. Tus derechos

Puedes pedir el acceso, la rectificación, la supresión, la oposición, la limitación o la portabilidad de tus datos escribiendo a {!! $f($e['correo'], 'correo de contacto') !!}. Si crees que no los tratamos bien, puedes reclamar ante la Agencia Española de Protección de Datos (www.aepd.es).

## 6. Cookies

Consulta la [política de cookies](cookies).
