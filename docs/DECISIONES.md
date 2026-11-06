# Registro de decisiones

Formato: decisión — motivo. Las decisiones nuevas se añaden al final con su fecha.

## 2026-11-06 — Fase de planteamiento

| # | Decisión | Motivo |
|---|---|---|
| D01 | Laravel + Filament + Livewire, todo en PHP | Pedido del cliente (PHP/Laravel); una sola tecnología facilita el mantenimiento. Filament permite replicar las funciones de WooCommerce. |
| D02 | Backend con funciones y organización de WooCommerce | El equipo lleva años trabajando con WooCommerce. |
| D03 | Impresión DTF; precio por zona, sin depender del número de colores | El DTF imprime a todo color. |
| D04 | Zonas iniciales: frontal, pecho y espalda grande; varias zonas por prenda si el cliente lo decide | Respuesta del cliente. |
| D05 | Paquete de producción en **ZIP**, no RAR | RAR es propietario y requiere software no libre en Linux; ZIP es nativo en PHP. |
| D06 | Calidad del diseño medida en **píxeles reales frente a cm de impresión**, no por el DPI del archivo | El DPI de un PNG es una etiqueta modificable que no indica calidad real. |
| D07 | Solo se aceptan diseños en PNG | Requisito del cliente. |
| D08 | La IA no calcula precios; el motor de precios es determinista y con tests | Un precio inventado puede acabar en una factura. |
| D09 | Si aplican varias ofertas, se aplica la mejor para el cliente y se le informa («Cumples dos condiciones…») | Respuesta del cliente. |
| D10 | Mínimos y precios como reglas configurables (calibrador) en el backend | Respuesta del cliente. |
| D11 | Envíos por kilo o por caja, configurables por zona de destino | Respuesta del cliente. Cada variante necesita su peso. |
| D12 | Todo tipo de clientes (particulares, autónomos, empresas); precios mostrados con IVA | Respuesta del cliente. Se contempla el recargo de equivalencia. |
| D13 | Catálogo manual con conector de proveedor intercambiable | La API del proveedor aún no se conoce. |
| D14 | Cobro del 100 % al formalizar el pedido, por Redsys (más Bizum si el banco lo activa) | Respuesta del cliente. |
| D15 | Facturas emitidas desde el portal (PDF + SMTP), con registro preparado para Verifactu | Redsys cobra pero no factura; Verifactu será obligatorio. |
| D16 | Proveedor de IA intercambiable (Claude u OpenAI), solo para clientes registrados | Respuesta del cliente. |
| D17 | Maqueta en 2D (foto plana + rectángulo de zona), no 3D | Más eficaz y barata; suficiente para vender. |
| D18 | Estados internos separados de los estados visibles para el cliente | El equipo necesita más detalle que el cliente. |
| D19 | El cliente puede cancelar hasta que empieza la impresión | Respuesta del cliente. |
| D20 | Revisión humana del diseño tras el pago; si no es válido, se contacta al cliente | Respuesta del cliente: es un servicio. |
| D21 | Correo por SMTP con proveedor de envíos; remitentes separados para pedidos y marketing | Proteger la entrega de facturas si el marketing acaba en spam. |
| D22 | Contenido SEO generado por IA siempre como borrador que aprueba un administrador | Evitar contenido masivo sin revisión y datos inventados. |
| D23 | Separación en versión 1 (vender y producir) y versión 2 (vender más) | Facturar antes, bases más sólidas y versión 2 diseñada con datos reales. |
| D24 | «Repetir pedido» se adelanta a la versión 1 | Coste bajo y ventas repetidas desde el primer mes. |
| D25 | Docker con entorno de pruebas y de producción en el dedicado de OVH | Ningún cambio llega a producción sin probarse. |
| D26 | Copias de seguridad fuera del servidor y con prueba de restauración periódica | Una copia no probada no garantiza nada. |
