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
| D27 | Sin precios tachados ni porcentajes de descuento; la ficha muestra solo «Desde X €/ud. · IVA incluido» | Decisión del cliente. Las ofertas se explican en el configurador. |
| D28 | Sin Klarna ni mensajes de financiación; pagos solo con Redsys (tarjeta y Bizum) | Klarna aparecía solo en la captura de referencia. |
| D29 | Las cantidades por talla se eligen en el configurador, no en la ficha de producto | Ficha limpia y parte operativa en un solo sitio. |
| D30 | Fotos de origen manual y/o API de proveedor, conviviendo; fotos con modelo opcionales | El cliente las subirá o vendrán de un proveedor, quizá ambas. |

## 2026-11-06 — Fase 1, paso 1.1

| # | Decisión | Motivo |
|---|---|---|
| D31 | Desarrollo local en Windows con **Herd (gratuito)** y la **MariaDB de XAMPP** (solo el módulo MySQL; Apache apagado). Docker se usa solo en el servidor de OVH | Entorno que ya tiene el equipo. En local, colas y caché en base de datos y correo al log |
| D32 | **Laravel 13** (13.40 al instalar), PHP 8.3 como mínimo | Última versión estable disponible |
| D33 | Backend solo en **español**; frontend en español e inglés con prefijo en la URL (`/es`, `/en`) y redirección según el idioma del navegador | Respuesta del cliente; URLs por idioma necesarias para el SEO (hreflang) |
| D34 | Zona horaria **Europe/Madrid** | Fechas de pedidos, plazos y facturas en hora peninsular |
| D35 | Fuentes del sistema, sin cargar Google Fonts desde los servidores de Google; si más adelante se usa una fuente propia, se aloja en el servidor | Evitar enviar la IP de los visitantes a terceros (RGPD) |
| D36 | Las alertas del sistema se envían al correo facilitado por el cliente, configurado en el `.env` del servidor (no en el repositorio) | Datos personales fuera del código |
| D37 | Retención de copias provisional 7 diarias, 4 semanales y 12 mensuales; copia externa (OVH Object Storage) preparada pero desactivada hasta que se contrate | Respuesta del cliente: se decide más adelante |
| D38 | Dominios provisionales hasta que el cliente los confirme | Respuesta del cliente |

## 2026-11-06 — Fase 1, paso 1.2

| # | Decisión | Motivo |
|---|---|---|
| D39 | **Filament 5** (5.11 al instalar) con **Livewire 4**, panel en `/admin`, color gris neutro | Última versión estable; sobrio y coherente con el frontend |
| D40 | El menú del backend se define con una enumeración (`GrupoNavegacion`) en el orden de WooCommerce. Las secciones aún no construidas aparecen con su descripción y la fase en que llegan | El equipo ve desde el principio dónde estará cada cosa |
| D41 | Grupo **Producción** propio (órdenes de trabajo y hoja de compra), separado de Pedidos | El rol Producción solo verá ese grupo (paso 1.3) |
| D42 | Avatar de iniciales generado en el servidor, en lugar del servicio externo ui-avatars.com que trae Filament | No enviar nombres de administradores a terceros (D35) |
| D43 | Traducciones de Laravel al español (laravel-lang) publicadas en `lang/` dentro del repositorio; el paquete solo se usa en desarrollo | Mensajes de validación y error en español sin dependencias en producción |
| D44 | Alta de administradores con el comando en español `php artisan nga:crear-administrador` (contraseña de 12 caracteres como mínimo) | El comando de Filament pregunta en inglés |
| D45 | En el panel, solo `IdiomaBackend` se declara como middleware persistente de Livewire | Declarar persistentes las cookies o la sesión las procesa dos veces en cada petición de Livewire y provoca el error 419 al entrar (detectado en Herd). Cubierto por un test |
| D46 | Las comprobaciones en navegador se hacen con un dominio `http://*.test`, no con `127.0.0.1` | En `localhost` el navegador envía `Sec-Fetch-Site` y Laravel 13 no llega a comprobar la sesión, lo que ocultó el error anterior |

## 2026-11-06 — Fase 1, paso 1.3

| # | Decisión | Motivo |
|---|---|---|
| D47 | Cuatro **roles fijos** definidos en el código (`Rol`): Superadministrador, Administrador, Gestor de la tienda y Producción. `php artisan migrate` los crea o actualiza; la página «Roles y permisos» los muestra, no los edita | Roles estables y probados; cambiar un rol es una decisión, no un clic |
| D48 | Un permiso por grupo del menú (`seccion.<grupo>`). Gestor de la tienda: pedidos, producción, clientes, productos, marketing y **analíticas**; sin ajustes ni herramientas, como en WooCommerce | Respuesta del cliente: el gestor ve y muestra las analíticas |
| D49 | Solo entran en el backend usuarios **activos** con uno de los cuatro roles. Los usuarios no se borran: se desactivan | Los futuros clientes no tendrán rol; conservar historial |
| D50 | Nadie puede cambiar su propio rol ni desactivarse, y siempre queda al menos un superadministrador activo | Evitar quedarse sin acceso al backend |
| D51 | Los usuarios creados antes de los roles pasan a Superadministrador al migrar | Eran del equipo; nadie se queda fuera |
