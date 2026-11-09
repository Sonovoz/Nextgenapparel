# Historial del proyecto

Qué se ha construido, entrega a entrega, de la más reciente a la más antigua (D171). **Cada entrega nueva añade aquí su línea en su misma PR.** Las correcciones y sus porqués van en el mensaje del commit o de la PR; aquí solo lo que cambia para el cliente o para el equipo.

## Noviembre de 2026

| Fecha | Entrega | PR / rama | Decisión |
| --- | --- | --- | --- |
| 09/11 | **9 Auditoría final**: corregidos el aviso de Redsys (CSRF), Redsys de pruebas en producción, devoluciones dudosas, devolución de incidencias fuera de la factura, CSV sin fórmulas y HSTS; lo que falta para salir en `docs/AUDITORIA_FINAL.md`. | `fase-9-auditoria` | D185 |
| 09/11 | **8.2 Informes de ventas**: *Analíticas → Informes de ventas* con facturado, rectificado, neto, impuestos, pedidos, ticket medio, ventas por día, lo más vendido y cupones, por periodo, y CSV de facturas para la gestoría. | `fase-8-informes` | D184 |
| 09/11 | **8.1 Textos legales**: aviso legal, privacidad, cookies, condiciones de venta y envíos e incidencias generados con los datos del sistema, por versiones, con revisión de la asesoría en *Ajustes → Textos legales*; enlaces en el pie; el pedido guarda la versión de las condiciones aceptadas. | `fase-8-legal` | D183 |
| 09/11 | **7.2 Devoluciones por Redsys**: al cancelar un pedido cobrado y al resolver una incidencia con reembolso, el dinero vuelve solo; opción en *Ajustes → General* para que el cliente cancele al momento. | `fase-7-devoluciones` | D182 |
| 09/11 | **7.1 Pagos con Redsys**: «Pagar ahora» en el pedido (tarjeta o Bizum en la página del banco), aviso y vuelta firmados, cobros que no cuadran anotados, pago simulado para pruebas y *Ajustes → Pagos*. | `fase-7-pagos` | D181 |
| 09/11 | **6.7 Cierre de la fase 6**: revisión del instructor y sus correcciones: nunca dos facturas por pedido ni factura de un pedido cancelado (también garantizado en la base de datos), cadena de huellas sin bifurcaciones, motivo de la rectificativa sin notas internas, caducidad segura frente a pagos, Producción solo con pedidos en producción y fichas desactivadas sin acceso. | `fase-6-cierre` | D179 |
| 09/11 | **6.6b Incidencias con fotos**: el cliente la abre desde su pedido entregado (fotos sin metadatos) y el equipo la resuelve en *Pedidos → Incidencias* con reimpresión, reembolso parcial (rectificativa parcial al momento) o rechazo, con respuesta por correo. Plazo en *Ajustes → General*. | `fase-6-incidencias` | D178 |
| 09/11 | **6.6a Visor del cliente**: línea de progreso con fechas, «Mis pedidos» en la cuenta, repetir pedido (al carrito con el precio de hoy) y cancelar (al momento sin pagar; si está pagado, petición al equipo con aviso y filtro en *Pedidos*). | `fase-6-visor-cliente` | D177 |
| 09/11 | **6.5b Documentos de producción**: órdenes de trabajo con PDF y paquete ZIP, y hoja de compra en pantalla y CSV, en *Producción* (el rol Producción no ve los datos de los clientes). Los PDF pesan ~30 KB (antes ~900 KB, fuente recortada). | `fase-6-produccion` | D176 |
| 09/11 | **6.5a Facturas**: completa con NIF y simplificada sin él, rectificativa al cancelar, desglose de impuestos del pedido, numeración correlativa, Veri*Factu preparado (desactivado), PDF en el correo, en el pedido y en *Pedidos → Facturas*, y *Ajustes → Facturación*. Larastan ya corre también en local. | `fase-6-facturas` | D175 |
| 09/11 | **6.4 Correos del pedido**: SMTP en *Ajustes → Correos* (portado de Clave de Alma) con correo de prueba, plantillas es/en por estado editables, nombres de los estados para el cliente, notas por correo y enlace privado al pedido en cada correo. | `fase-6-correos` | D174 |
| 09/11 | **6.3 Backend de Pedidos**: lista y ficha como WooCommerce, cambio de estado (también en bloque), notas privadas y para el cliente, fecha estimada de entrega, cancelación que devuelve el cupón y caducidad de los pedidos sin pagar. | `fase-6-backend-pedidos` | D173 |
| 09/11 | **6.2 Maquetas del pedido**: una imagen por cara generada en el servidor (Imagick) con los diseños en su sitio y el fundido con la tela, en la cola con reintentos, y visibles en la página del pedido. | `fase-6-maquetas` | D172 |
| 09/11 | **Auditoría del proyecto y nuevo método**: la CI es la puerta (Pint, Larastan, SQLite con PHP 8.3 y 8.5, MariaDB estricta), una revisión del instructor por fase, historial de una línea por entrega y reutilización de Clave de Alma para lo que falta (`docs/AUDITORIA_2026-11-09.md`). | `fase-6-pedidos` | D171 |
| 08/11 | **6.1 Pedido desde el carrito**: «Confirmar pedido» con las condiciones, número correlativo `PED-0001`, copia congelada (datos, líneas, importes del motor, archivos de los diseños), cupón y carrito bajo bloqueo, enlace para que el invitado confirme su correo y página del pedido. | `fase-6-pedidos` | D169, D170 |
| 08/11 | Plan de la fase 6 (presupuesto en PDF aplazado). | `fase-6-pedidos` | D168 |
| 08/11 | **Fase 5, configurador y área de cliente**: cuenta del cliente, área de cliente, backend de Clientes, carrito persistente (también de invitados), «Añadir a mi pedido» con maqueta, packs y «Cierra el pedido» hasta el total. | `fase-5-cliente` (PR 7, sin fusionar) | D153–D167 |
| 08/11 | **Fase 4, diseños**: análisis del PNG en el servidor, subida con declaración de derechos, biblioteca en la tienda y en el backend, retención de 30 días. | #6 | D147–D152 |
| 08/11 | **Sobre nosotros** e impresión **DTI** en toda la web. | #5 | D145, D146 |
| 08/11 | **Frontend de consola**: configurador como página de producto en 3 niveles con precio en directo, mejora del diseño con IA (instrucciones al cliente), portada con camiseta 3D, 404 de la marca y luz en el pie. | #5 | D133–D144 |
| 08/11 | **Fase 3, motor de precios**: tramos, ofertas, mínimos, cupones, envíos, impuestos, recargo, packs y simulador en el backend. | #5 | D119–D132 |
| 07/11 | **Fase 2, catálogo**: familias, prendas variables, colores, tallas, zonas, fotos de maqueta, proveedores, stock, SEO técnico es/en. | #3, #4 | D76–D118 |
| 06/11 | **Fase 1, base**: Laravel 13 + Filament 5, Docker (pruebas y producción), roles, idiomas, registro de errores y actividad, copias, salud y alertas. | #1, #2 | D01–D75 |
