# Privacy policy

@include('legal.en._titular')

## 1. Data controller

| | |
| --- | --- |
| Controller | {!! $f($e['razon_social'], 'razón social') !!} |
| Tax ID (NIF) | {!! $f($e['nif'], 'NIF') !!} |
| Address | {!! $f($e['direccion'], 'domicilio') !!}, {!! $f($e['codigo_postal'], 'código postal') !!} {!! $f($e['localidad'], 'localidad') !!} (Spain) |
| Contact | {!! $f($e['correo'], 'correo de contacto') !!} |

## 2. What data we process, why and on what basis

| Data | Purpose | Legal basis |
| --- | --- | --- |
| Name, email, phone, delivery address and tax details | Making, shipping and invoicing your order and keeping you informed | Performance of the contract (art. 6.1.b GDPR) |
| Order and invoice data | Meeting our tax and accounting obligations | Legal obligation (art. 6.1.c GDPR) |
| The designs (PNG) you upload | Checking their quality and printing them on your order | Performance of the contract |
| Photos of an issue (we remove location and camera data on receipt) | Resolving the issue | Performance of the contract |
| If you create an account (optional): email, name, encrypted password and saved addresses | Giving you access to your orders, designs and invoices | Performance of the contract you accept when signing up |
| The IP address used to confirm the order | Proving acceptance of the terms | Legitimate interest (art. 6.1.f GDPR) |

**We never ask for or store your card details**: you pay on the secure page of Redsys, the Spanish banks' payment platform. **We do not send advertising**, build profiles or make automated decisions.

## 3. Who receives your data

- **Redsys and our bank**, to charge the order (amount and order number).
- **The carrier**, which receives your name, address and phone number to deliver the parcel.
- **The providers that host the website and send emails**, as data processors: {!! $f(null, 'proveedores de alojamiento y de correo (los indica la asesoría)') !!}.
- **The Spanish Tax Agency**, when required by law.

The production team only sees what to print, without your personal data. We never sell or share your data.

## 4. How long we keep it

- **Orders and invoices**: the periods required by commercial and tax law.
- **Designs without an order**: deleted automatically after the period shown when you upload them.
- **Issue photos and the order IP address**: {!! $f(null, 'plazo de conservación de las fotos de incidencias y de la IP (lo fija la asesoría)') !!}.
- **Account**: while you keep it. If you ask us to delete it, we do; orders and invoices are kept as long as the law requires.

## 5. Your rights

You can request access, rectification, erasure, objection, restriction or portability of your data by writing to {!! $f($e['correo'], 'correo de contacto') !!}. If you think we are not handling them properly, you can complain to the Spanish Data Protection Agency (www.aepd.es).

## 6. Cookies

See the [cookie policy](cookies).
