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

## 2026-11-06 — Fase 1, paso 1.4

| # | Decisión | Motivo |
|---|---|---|
| D52 | **Log de errores propio** (tabla `registro_errores`): un error por huella (tipo + archivo + línea), con contador, primera y última vez, página, usuario y traza resumida. Laravel sigue escribiendo además en `storage/logs` | Autosuficiente y sin enviar datos a servicios externos |
| D53 | Son **críticos** los errores internos de PHP y los de base de datos, conexión y archivos (lista en `config/errores.php`). Solo los críticos avisan por correo, como máximo uno por hora y error, salvo que se resuelva y vuelva | Avisar de lo importante sin saturar el correo |
| D54 | La alerta se envía en el momento (sin cola). Si la base de datos no responde, se envía igualmente y la repetición se controla con un archivo | El caso más grave es justo cuando falla la base de datos o la cola |
| D55 | No se guardan la parte `?…` de las URL ni contraseñas; en las acciones del backend se guarda la página del usuario, no la ruta interna de Livewire | Datos personales y tokens fuera del log; información útil para corregir |
| D56 | Los errores resueltos se borran a los 90 días; un error resuelto que vuelve se reabre solo | Log limpio y sin perder reincidencias |
| D57 | **Registro de actividad** con spatie/laravel-activitylog: altas y cambios de usuarios (sin contraseñas), cambios de rol con antes y después, inicios de sesión, intentos fallidos (correo e IP) y errores resueltos o reabiertos. Solo lectura, para quien tiene Herramientas | Saber quién cambió qué y cuándo, y detectar intentos de acceso |
| D58 | Botón «Provocar error de prueba» (solo Superadministrador) y comando `nga:error-prueba` | Comprobar en cualquier entorno que el log y la alerta funcionan (criterio de aceptación de la fase 1) |
| D59 | Fechas obligatorias con `dateTime()`, no con `timestamp()`; las pruebas con MariaDB se hacen con la configuración de XAMPP (`explicit_defaults_for_timestamp` desactivado y modo estricto). Un test revisa las migraciones | La MariaDB de XAMPP rechazaba la tabla del log de errores («Invalid default value for 'ultima_vez'») |
| D60 | En local, **SQLite** (un archivo, `database/database.sqlite`) en lugar de la MariaDB de XAMPP; sustituye a la parte de base de datos de D31. En el servidor, MariaDB. Cada paso se sigue probando contra MariaDB en modo estricto y con la configuración de XAMPP antes de entregarlo | Como en Clave de Alma: sin XAMPP ni servidores que arrancar en local |

## 2026-11-06 — Fase 1, paso 1.5

| # | Decisión | Motivo |
|---|---|---|
| D61 | **Comprobación de salud propia** (no spatie/laravel-health): base de datos, caché, tareas programadas, colas, correo SMTP, espacio en disco, errores críticos pendientes y configuración. Estado en un archivo (`storage/framework/salud.json`) | El paquete usaba la caché (en base de datos) para no repetir avisos: con la base de datos caída no habría avisado. Además, mensajes en español |
| D62 | Se comprueba cada 5 minutos. Aviso por correo al fallar algo, al fallar algo más, si sigue fallando pasada una hora, y cuando todo vuelve a funcionar | Saber cuándo actuar y cuándo está resuelto, sin saturar |
| D63 | «Latidos» de tareas programadas y cola en archivos (`storage/framework/latidos`) | Detectar procesos parados aunque fallen la base de datos o la caché |
| D64 | En local, `php artisan schedule:work` ejecuta las tareas y procesa la cola (no hay Horizon en Windows). Limpiezas nocturnas: errores resueltos (90 días), actividad (1 año), trabajos fallidos (30 días) | Un solo comando en local; base de datos limpia sola |

## 2026-11-06 — Fase 1, paso 1.6

| # | Decisión | Motivo |
|---|---|---|
| D65 | Copias con **spatie/laravel-backup** (comprime, cifra con AES-256, reparte entre discos y aplica la retención 7/4/12). La base de datos SQLite se copia con `VACUUM INTO` | El paquete necesita el programa `sqlite3` para SQLite, que no existe en Windows |
| D66 | Sin los correos del paquete (en inglés): un fallo de copia o de la prueba de restauración va al log de errores como crítico y avisa; el estado del sistema vigila que haya copia de menos de 26 horas | Un solo canal de avisos, en español |
| D67 | Se copian la base de datos y `storage/app` (archivos subidos); el `.env` no | El `.env` contiene contraseñas: se guarda aparte |
| D68 | Copia diaria a las 01:30, limpieza a la 01:00 y **prueba de restauración automática** los domingos; también con botón y con `nga:copia-seguridad --probar`. Descarga solo para el Superadministrador, con registro en la actividad | Una copia que no se prueba no garantiza nada (D26) |

## 2026-11-06 — Fase 1, paso 1.7

| # | Decisión | Motivo |
|---|---|---|
| D69 | Sin **Laravel Horizon**: la cola la procesa un contenedor `cola` con `queue:work`, que Docker reinicia si se cae | Horizon exige extensiones de PHP que no existen en Windows y rompería `composer install` en Herd; para el volumen previsto basta `queue:work` |
| D70 | Un solo `compose.yaml` para pruebas y producción (proyectos `nga-pruebas` y `nga-produccion`, cada uno con su `.env`, base de datos y volúmenes) y **Caddy** delante, compartido, con SSL automático. Mailpit solo en pruebas | Mismo código y configuración en los dos entornos; certificados sin mantenimiento |
| D71 | Imagen PHP 8.4 (Alpine) con las extensiones del proyecto (Imagick, Redis, intl, gd, bcmath, zip, pdo_mysql) y `mariadb-client` para las copias; Nginx en otra imagen con los archivos públicos; contenedores sin root, con reinicio automático, comprobación de salud y logs limitados | Seguridad y autosuficiencia (D25) |
| D72 | Despliegue con un comando: `./docker/desplegar.sh pruebas|produccion` construye, arranca y espera a que todo esté sano; las migraciones se aplican al arrancar `app` | Criterio de aceptación de la fase 1 |
| D73 | Nginx pasa a Laravel cualquier archivo estático (`.js`, `.css`…) que no exista en disco | El JavaScript de Livewire lo genera Laravel; con `=404` no se podía entrar en el backend (detectado al probar en Docker) |

## 2026-11-07 — Forma de trabajar

| # | Decisión | Motivo |
|---|---|---|
| D74 | **Instructor del proyecto** (`.claude/agents/instructor.md`): planifica cada fase, asigna a cada paso un nivel de verificación N1, N2 o N3 según su riesgo y hace la revisión adversarial al cerrarla. Una sola verificación al final de cada paso; revisión del cliente en Herd cada 2 o 3 pasos; Gauntlet completo solo al cerrar fase | En la fase 1 la suite de tests tardaba 17 s; las vueltas venían de capturas y comprobaciones intermedias, de verificarlo todo al mismo nivel y de revisar en Herd tras cada paso |

## 2026-11-07 — Fase 2, paso 2.1

| # | Decisión | Motivo |
|---|---|---|
| D75 | Hasta la fase 3, la ficha muestra «Desde X € IVA incluido» con un **IVA del 21 %**, configurable en Ajustes | Respuesta del cliente; los impuestos completos llegan en la fase 3 |
| D76 | **SKU automático** de cada variación (formato `CAM-ESSENTIAL-NEGRO-M`) | Respuesta del cliente |
| D77 | Catálogo con enteros: precios y costes en céntimos sin IVA, pesos en gramos, medidas en milímetros y posición de las zonas sobre la foto en centésimas de porcentaje (0–10000). Textos traducibles en JSON con el español como respaldo | Sin redondeos (regla 7); las zonas no dependen del tamaño de la foto |
| D78 | Prendas en papelera (borrado recuperable); no se puede borrar una familia, un color o una talla en uso; las claves de API de los proveedores se guardan cifradas | Como en WooCommerce; datos protegidos |
| D79 | La alerta de respaldo de errores críticos se construye sin consultar la base de datos | Con la conexión caída no salía: el modelo consultaba la estructura de la tabla (fallo de la fase 1 detectado en esta sesión) |

## 2026-11-07 — Fase 2, paso 2.2

| # | Decisión | Motivo |
|---|---|---|
| D80 | En Productos, **Colores** y **Tallas** como secciones propias en lugar del «Atributos» genérico de WooCommerce | Los atributos de las prendas son siempre color y talla: más directo |
| D81 | Secciones sencillas con lista y edición en ventana; textos en español e inglés lado a lado (el inglés es opcional); medidas de las zonas en cm en pantalla y en mm en la base de datos | Rapidez de uso y sin decimales guardados |
| D82 | Datos iniciales opcionales para probar en local (`php artisan db:seed --class=CatalogoInicialSeeder`): 3 familias, 7 tallas, blanco y negro, y 3 zonas con medidas de ejemplo | Empezar a probar sin teclear todo; se cambian en el backend |

## 2026-11-07 — Fase 2, paso 2.3

| # | Decisión | Motivo |
|---|---|---|
| D83 | Los importes se escriben en euros con **coma o punto** decimal (máx. 2 decimales) y se guardan en céntimos; se muestran en formato español («1.234,50 €») | En España se escribe la coma; sin errores de redondeo (probado con 0,29 y 19,99) |
| D84 | El SKU se genera al crear la variación y **no cambia** aunque se renombre la prenda o el color | Un SKU estable no rompe pedidos, etiquetas ni referencias externas |
| D85 | Las prendas van a la papelera y se recuperan; el borrado definitivo no está disponible en el backend. Los cambios de precio, coste, SKU y estado quedan en el registro de actividad | Los pedidos futuros guardarán referencias; trazabilidad de precios |

## 2026-11-07 — Fase 2, paso 2.4 y ejemplos

| # | Decisión | Motivo |
|---|---|---|
| D86 | Fotos en el disco `public` (`storage/app/public/catalogo/<prenda>`), PNG, JPG o WebP de hasta 5 MB; el archivo se borra al borrar la foto. Una maqueta por prenda, color y cara | Servidas directamente por Nginx; sin archivos huérfanos |
| D87 | Al subir una maqueta, las zonas de impresión de esa cara se colocan solas en una posición de partida (frontal, pecho, espalda) y se ajustan en % con vista previa | Ahorra trabajo: la mayoría de prendas tienen las zonas en el mismo sitio |
| D88 | Las maquetas de muestra se preparan con **fondo transparente**, mismo encuadre (1000 × 1200) y misma escala por prenda | Aspecto premium uniforme y se pueden poner sobre cualquier fondo |
| D89 | Tres prendas de ejemplo con datos inventados (`php artisan db:seed --class=EjemplosCatalogoSeeder`): Camiseta Essential, Polo Classic y Sudadera Urban, sin fotos | Probar en local con datos realistas; las fotos se suben desde el backend (D30) |

## 2026-11-07 — Fase 2, paso 2.5

| # | Decisión | Motivo |
|---|---|---|
| D90 | **Ajustes → General** con nombre de la tienda, correo de contacto e **IVA general** (21 % por defecto); los ajustes se guardan en la tabla `ajustes` con caché y, si la base de datos no responde, se usa el valor por defecto. El cambio de IVA queda en la actividad | Regla 4 (configurable en el backend) y D75 |
| D91 | IVA calculado con enteros: tipo en centésimas de % y redondeo al céntimo, medio hacia arriba (7,50 € → 9,08 €) | Sin coma flotante en el dinero (regla 7) |
| D92 | URLs de la tienda traducidas: `/es/camisetas/camiseta-essential` y `/en/t-shirts/essential-t-shirt`; si una prenda no tiene URL en inglés, se usa la española. El selector de idioma lleva a la misma página en el otro idioma | SEO por idioma (hreflang) y navegación coherente |
| D93 | Ficha con visor propio en JavaScript sin librerías (color, cara y zonas sobre la maqueta), apartados con `<details>` nativos y el botón de personalizar desactivado hasta el configurador | Ligero, accesible y sin dependencias |
| D94 | Solo se muestran prendas visibles, fuera de la papelera y con al menos una variación activa; la portada y el menú se muestran aunque la base de datos no responda | Nada a medias en la tienda; autosuficiencia |
| D95 | «Tu marca, aquí» en la ficha: el cliente suelta su logo o escribe su marca y lo ve impreso en la zona elegida, en todos los colores («Tu colección»). Todo ocurre en su navegador: no se sube ni se guarda nada | El momento emocional que vende («esto es mío») antes de pedir datos; sin datos personales ni almacenamiento; la misma pieza será la vista previa del configurador |
| D96 | El diseño se funde con la tela usando la propia maqueta como mapa (luces, sombras y pliegues suavizados, sin el grano), a doble resolución; sin WebGL ni modelos 3D ni IA | Aspecto de impresión real con un archivo JavaScript sin librerías; rápido y sin coste por visita. El 3D queda para la versión 2 si hace falta |
| D97 | El visor vive en `public/js/visor.js` (sin compilar con Vite), cargado con versión por fecha del archivo | En Herd y en Docker funciona sin `npm run build`; la caché del navegador se renueva al cambiar el archivo |
| D98 | Las maquetas del pedido (una por cara, con todos los diseños) las genera el **servidor con Imagick** a partir del diseño original y la posición guardada, no una captura del navegador. Se adjuntan al PDF de confirmación del cliente y a la orden de trabajo | Es un documento del pedido: debe ser reproducible, igual en cualquier navegador, a buena resolución y sin depender de lo que envíe el cliente. El visor sigue siendo solo vista previa (D95) |
| D99 | Las impresiones se colocan **midiendo desde el cuello**, no centradas en la zona: frontal y pecho a 7,5 cm bajo el cuello delantero, espalda a 10 cm bajo el trasero; frontal y espalda centrados, pecho a 10 cm del centro en el lado izquierdo de quien la lleva. Las zonas de partida guardan la proporción real en cm. El visor alinea el diseño arriba de la zona y recorta los márgenes transparentes del logo. En el pedido, `posicion` se guarda en mm desde el cuello y desde el centro | Con las zonas de la primera versión los diseños salían bajos y demasiado grandes: en producción eso es un pedido mal impreso. Las medidas desde el cuello son las que usa el taller |
| D100 | **Ajustador** en el visor: el cliente cambia el tamaño (20–100 % de la zona) y arrastra el diseño dentro de su zona; ve el tamaño real de impresión en cm y la **calidad al momento**: nivel, ppp efectivo, mensaje, consejo (tamaño máximo con calidad excelente o píxeles necesarios) y aviso si la imagen tiene fondo o fondo blanco. Al colocar un diseño, las marcas de zona se apagan solas | Decirle la verdad sobre su imagen antes de pagar evita pedidos borrosos y reclamaciones; ver los cm reales evita sorpresas en el tamaño |
| D101 | La calidad se calcula en `App\Dominio\Disenos\Calidad` (aritmética entera, tests) con umbrales en **Ajustes → General** (300/200/150 ppp por defecto, en orden obligatorio y con registro de actividad). El visor repite la misma fórmula con los umbrales que le da el servidor; la comprobación que vale para el pedido es la del servidor (fase 4) | Regla de negocio en PHP y configurable (reglas 2 y 4); aviso inmediato sin subir el diseño (D95) |
| D102 | La tienda solo se indexa **en producción y con el interruptor de Ajustes → General encendido** (apagado por defecto). En cualquier otro caso: `noindex` en las páginas y `robots.txt` con `Disallow: /`. `robots.txt` y los sitemaps (`/sitemap.xml` con uno por idioma, con `hreflang`, `lastmod` e imagen) se generan en Laravel; el backend lleva `X-Robots-Tag: noindex` | Que pruebas o una copia de la base de datos nunca acaben en Google; el equipo decide el día del lanzamiento sin tocar código (regla 4) |
| D103 | Cada página lleva `canonical` (su URL en su idioma), Open Graph (con `og:locale` y alternativa) y JSON-LD: Organization y WebSite en la portada, BreadcrumbList en listado y ficha, y Product con `AggregateOffer` (precio mínimo y máximo con IVA del catálogo) en la ficha | Resultados enriquecidos y enlaces bonitos al compartir; el precio de los datos estructurados es el mismo que ve el cliente |
| D104 | En móvil, la cabecera ocupa dos filas compactas: marca e idioma (ES/EN) arriba y el menú de familias en una fila deslizable debajo | La cabecera apilada ocupaba un tercio de la pantalla |
| D105 | La compra se propone en **3 pasos**: Sube tu diseño · Escoge las prendas · Cierra el pedido. Se añade un **selector de Packs** (ofertas cerradas) como segunda vía de venta | Decisión del cliente: sencillez para el comprador joven y una vía de captación con precio cerrado |
| D106 | Pack: reparto **configurable por pack** (fijo o el cliente reparte tallas y color permitido); precio cerrado que incluye prendas y **zonas fijadas**; otras zonas se cobran con su tarifa; **público** en la tienda con fechas opcionales | Respuestas del cliente; flexible sin complicar al comprador |
| D107 | Los packs **nunca** se combinan con cupones, tramos ni ofertas combinadas; cumplen el mínimo por sí solos. El modelo, el backend y el precio van en la fase 3 (motor de precios, con tests K1–K4) y el selector en la tienda en la fase 5 | El precio del pack ya es la oferta; lógica antes que interfaz (regla 2) y versión 1 antes que la 2 (regla 8) |
| D108 | Laravel confía en las cabeceras `X-Forwarded-*` del proxy (`trustProxies(at: '*')`): Caddy termina el HTTPS y el contenedor de la aplicación no se publica fuera de la red de Docker | Sin esto, canonical, sitemap, JSON-LD y el JavaScript del visor saldrían en `http://` en producción (Gauntlet fase 2, ronda 1) |
| D109 | Catálogo a prueba de pedidos: las variaciones **no se borran** (se desactivan); un color con variaciones o fotos no se borra; la tienda solo muestra variaciones **a la venta** (activas y de color activo); el visor solo ofrece zonas que la prenda tiene activas, y al quitar una zona de la prenda se quita también de sus maquetas | Lo que se vende debe existir siempre con su precio; nada visible sin precio (Gauntlet fase 2, ronda 1) |
| D110 | Las URL se comprueban por su valor **efectivo** en cada idioma (la inglesa o, si falta, la española): dos registros nunca responden a la misma dirección. Las familias no pueden usar palabras reservadas (`config/tienda.php`) | Evita que una URL inglesa abra otra prenda y que una familia choque con rutas futuras (carrito, cuenta, packs…) |
| D111 | Vaciar un texto en inglés en el backend lo **borra** y vuelve a usarse el español (rasgo `Traducible` en los modelos) | Spatie solo añadía traducciones: una URL inglesa equivocada no se podía quitar |
| D112 | Las fotos no dejan archivos huérfanos al cambiar la imagen; si una maqueta pasa a ser foto de modelo o detalle, pierde la cara y sus zonas. Las zonas no pueden salirse de la foto (aviso en el campo) | Coherencia de datos y del disco (D86) |
| D113 | Disponibilidad en los datos estructurados: siempre `InStock`, porque se imprime bajo pedido y la prenda lisa se repone del proveedor. Se revisará si en la fase 6 se decide bloquear la venta sin stock | Aclara el defecto 10 de la ronda 1 sin inventar una regla de stock |
| D114 | La calidad se mide sobre lo que se imprime: el diseño **sin sus márgenes transparentes**. El análisis del servidor de la fase 4 (`App\Dominio\Disenos\Calidad` con Imagick) aplicará el mismo recorte para que el aviso de la ficha y el del pedido coincidan | Un logo con mucho margen no debe parecer de peor calidad de la que tiene |
| D115 | En el backend, la vista previa de cada maqueta coloca un **diseño de ejemplo** (logo cuadrado, texto horizontal o cartel vertical) con la misma regla que la tienda y se actualiza al cambiar las zonas, sin guardar | Petición del cliente: ajustar a ojo la posición correcta de cada zona según el tipo de imagen |
| D116 | Solo se atienden peticiones para el dominio de `APP_URL` y sus subdominios (`trustHosts`), más `127.0.0.1` para la comprobación de salud del contenedor web. No actúa en local ni en tests | Con `trustProxies('*')`, otro proceso del servidor podría colar un host falso en los enlaces de los correos (Gauntlet fase 2, ronda 2) |
| D117 | Las URL del catálogo son siempre minúsculas, números y guiones; las zonas se validan en centésimas, como se guardan; los archivos de fotos se borran tras confirmar la transacción; al añadir una zona a la prenda, sus maquetas la reciben en la posición de partida | Defectos bajos de la ronda 2, corregidos antes de cerrar |
| D118 | El precio **«Desde»** de la tienda incluye la impresión: variación a la venta más barata + zona activa más barata de la prenda, sumadas sin IVA y con el IVA aplicado una vez. Los datos estructurados usan los mismos importes (`highPrice`: variación más cara + zona más cara). Sin zonas activas no se muestra precio ni oferta. Cuando exista el motor de precios (fase 3), el «Desde» seguirá siendo el precio por unidad sin descuentos por cantidad | Decisión del cliente: el precio que se anuncia debe coincidir con lo que se paga, también para Google; la prenda nunca se vende sin imprimir |
| D119 | **Motor de precios** en `App\Dominio\Precios\Motor`: recibe una `Cesta` (prendas por color, tallas y zonas; packs; cupón; destino; recargo) y devuelve un `Resultado` con cada paso en céntimos, lo que impide confirmar y los avisos. No se fía de ningún precio de la entrada | Regla 3: precios deterministas y con tests; un único sitio de cálculo para tienda, carrito, pedido y simulador |
| D120 | Interpretaciones del motor: tramos y combinadas **excluyentes**, se aplica la que más ahorra (empate: prioridad); efectos «porcentaje» e «importe» (el precio fijo es el de los packs); el aviso «te faltan…» sale desde el 50 % de avance (Ajuste `ofertas.aviso_cercana`); el cupón va **después** de la oferta y solo sobre prendas sueltas; un cupón no válido se explica pero **nunca impide comprar**; los descuentos de importe se reparten entre líneas sin perder céntimos | Ficha § 6 y § 6 bis; lo no escrito se decide a favor de la claridad para el cliente |
| D121 | Mínimos: un pack cumple solo los mínimos **generales** (ámbito catálogo); los mínimos de una familia o prenda se aplican a sus prendas sueltas. Una prenda nunca se vende sin zona de impresión ni en una zona que no ofrece | D107 y D118 |
| D122 | Envío: zona por país y, si la tiene, prefijo de código postal (la más concreta gana, luego la prioridad); por peso con tramos y kilo extra, o por cajas (la caja activa más grande; cajas = lo que exija el número de prendas o el peso); gratis desde un importe sin IVA. El envío tributa como la mercancía: se reparte en proporción a la base de cada tipo de IVA (con su recargo, si el cliente lo tiene); sin mercancía con base, la clase por defecto | Ficha § 6. **El trato fiscal del envío y del recargo debe confirmarlo el asesor** |
| D123 | Impuestos por **clase** (Ajustes → Impuestos, como en WooCommerce): IVA y recargo de equivalencia; cada prenda o pack usa su clase o la de por defecto. En la versión 1 solo hay zonas de envío y fiscalidad de España peninsular y Baleares; Canarias, Ceuta, Melilla y el extranjero (IGIC, exenciones) se añadirán cuando se decidan los destinos | Regla 7 (precio sin IVA más su tipo de impuesto); destinos aún por determinar |
| D124 | Los mensajes del motor guardan **referencias** (nombres, importes, palabras) y se traducen al mostrarlos | El mismo resultado, guardado en el pedido, sale correcto en español y en inglés |
| D125 | Backend de precios como WooCommerce: **Ajustes → Impuestos, Envío y Cajas** (solo administradores); **Marketing → Ofertas, Pedido mínimo, Cupones y Simulador de precios**; **Productos → Packs** (gestores). El IVA sale de Ajustes → General y pasa a las clases de impuesto; la migración crea General (21 % + 5,2 %), Reducido (10 % + 1,4 %) y Superreducido (4 % + 0,5 %), y la general conserva el tipo que hubiera. No se borra una clase en uso o la de por defecto, ni un cupón ya usado | El equipo reconoce las secciones; lo que ya se usó no desaparece |
| D126 | **Simulador de precios** en el backend: monta una cesta (prendas, packs, cupón, recargo y destino) y muestra el desglose completo del motor. No guarda nada | Probar ofertas, mínimos y envíos antes de publicarlos, y antes de que exista el carrito |
| D127 | Con un pack en el pedido, las prendas sueltas quedan exentas del mínimo **general de unidades e importe**, pero no del **mínimo por talla** | Respuesta prudente a la pregunta del Gauntlet de la fase 3; **pendiente de confirmar con el cliente** |
| D128 | El motor no se rompe con datos raros: descuentos limitados a la base (aunque se configure más del 100 %, que además el backend rechaza); porcentajes calculados sobre el total y repartidos (un solo redondeo); cantidades enteras de 1 a 10 000 por talla; un pack con una prenda retirada o en la papelera deja de venderse; ofertas con condiciones mal formadas no se aplican; sin recargo, los impuestos se agrupan solo por tipo de IVA | Gauntlet de la fase 3 (ronda única) |
| D129 | Plan visual a partir de las maquetas del cliente (`docs/PLAN_VISUAL.md`): «Creamos marcas que comunican», **dos caminos con el mismo peso** (3 pasos o pack), tinta, crema y azul eléctrico solo para la acción, serifa display con palo seco alojados en el propio servidor, móvil primero con barra de acción fija, y criterios de Lighthouse ≥ 90 y LCP < 2,5 s. Se construye en los pasos V1 a V4, empezando por la portada antes de la fase 4 | Petición del cliente: sensación premium desde el primer contacto (móvil) y facilidad para comprar |
| D130 | La impresión se presenta al cliente como **DTI Textil** (nombre comercial del cliente, de más calidad) y la marca se escribe **Nextgenapparel**, como en el logotipo | Respuestas del cliente al plan visual |
| D131 | **Paso V1**: hoja de estilos propia (`public/css/tienda.css`) con tokens; tipografías **Bodoni Moda** (titulares) y **Jost** (texto), alojadas en el servidor (OFL, 73 KB en total); logotipo y fotos sacados de las diapositivas del cliente a alta resolución, en WebP con `srcset`. Portada con los dos caminos, línea de confianza con datos reales del backend (mínimo, plazo; si no hay mínimo configurado, no se inventa), 3 pasos con **prueba del logo** (se guarda solo en el navegador con `sessionStorage` y la ficha lo coloca al abrirse), packs vigentes, familias con su «Desde», calidad DTI Textil con macro, para quién, preguntas y llamada final. Menú móvil a pantalla completa y animaciones solo con JavaScript y sin «reducir movimiento». Lighthouse en móvil (local): rendimiento 98, accesibilidad 100, LCP 2,3 s | Plan visual (D129); el botón de cada pack queda en «Muy pronto» hasta la fase 5 para no llevar a un callejón sin salida |
