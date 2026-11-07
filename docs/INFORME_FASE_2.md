# Informe de calidad — Fase 2 (Catálogo)

Según la sección 14 de `docs/GAUNTLET.md`. Rama `fase-2-catalogo`, 7 de noviembre de 2026.

## Estado

**Terminado con limitaciones.** El criterio de aceptación se cumple y lo demuestra un test de principio a fin. Las limitaciones son de entorno, no del código: falta la revisión del cliente en Herd de los últimos pasos y el despliegue en el servidor de OVH, que aún no está preparado.

| Criterio de aceptación (ficha, § 15) | Resultado | Cómo se comprobó |
|---|---|---|
| Se crea una prenda variable completa desde el backend y se ve en el frontend en es/en con su sitemap y datos estructurados | **Cumplido** | `tests/Feature/Catalogo/AceptacionFase2Test.php`: como gestor, por Livewire, crea familia, prenda, variaciones (dos precios y un color retirado), zona y maqueta. Comprueba la ficha y el listado en `/es` y `/en`, la canonical, los hreflang, el JSON-LD (Product con `lowPrice` y `highPrice`, BreadcrumbList por idioma), el visor, `sitemap-es.xml` y `sitemap-en.xml` con imagen, y que en la papelera desaparece de la tienda y del sitemap. Pasa en SQLite y en MariaDB 10.11 estricta |

## Pasos

| Paso | Contenido | Nivel | Revisado por el cliente en Herd |
|---|---|---|---|
| 2.1 | Modelo de datos del catálogo (migraciones, modelos, reglas) | N3 | Pendiente |
| 2.2 | Backend: familias, colores, tallas, zonas, proveedores | N2 | Pendiente |
| 2.3 | Productos variables: variaciones con SKU automático, zonas con precio, guía de tallas | N3 | Pendiente |
| 2.4 | Fotos y maquetas con zonas; tres prendas de ejemplo | N2 | Sí (fotos subidas) |
| 2.5 | Tienda: listado y ficha; IVA en Ajustes | N2 (IVA N3) | Sí |
| 2.5b | Visor «Tu marca, aquí»: logo o texto sobre la prenda, colección, lupa, giro, ajustador y aviso de calidad | N2 | Sí («Está bien y funciona») |
| 2.6 | SEO técnico: robots, sitemaps, canonical, Open Graph, datos estructurados; cabecera del móvil | N2 (indexación N3) | Pendiente |

Peticiones del cliente añadidas durante la fase:
- zonas medidas desde el cuello (D99);
- ajustador con aviso de calidad (D100, D101);
- maquetas del pedido en el PDF (D98, para la fase 6);
- diseño de ejemplo en la vista previa del backend (D115);
- compra en 3 pasos y Packs (D105–D107, para las fases 3 y 5).

## Rondas de revisión

- **Por paso:** tests, Pint y, en N2 y N3, una pasada de navegador con dominio `.test`.
- **Revisión del cliente en Herd:** tras el 2.4 y tras el 2.5b. Encontró dos fallos: las zonas salían bajas y las marcas tapaban el diseño.
- **Gauntlet de cierre:** dos rondas adversariales hechas por el agente `instructor` (`.claude/agents/instructor.md`), independiente del constructor y sin permiso para modificar archivos.
  - **Ronda 1:** 17 hallazgos. Veredicto: «no listo».
  - **Ronda 2:** comprobó las correcciones y el código nuevo. Encontró 7 defectos bajos. Veredicto: «listo con limitaciones».
  - Los 7 defectos de la ronda 2 se corrigieron después, sin una tercera ronda.

## Defectos encontrados y corregidos

| # | Gravedad | Defecto | Corrección | Cómo se comprueba |
|---|---|---|---|---|
| 1 | Alta | Detrás de Caddy, las URL públicas (canonical, sitemap, JSON-LD) y `visor.js` saldrían en `http://`; el visor no cargaría | `trustProxies` (D108) | Test con `X-Forwarded-*` que falla sin la corrección |
| 2 | Alta (cliente) | Los diseños salían bajos y demasiado grandes: zonas de partida mal medidas | Zonas medidas desde el cuello con su tamaño real en cm; comando `catalogo:recolocar-zonas` (D99) | Tests de proporción y de recolocación; navegador |
| 3 | Media | Una URL inglesa podía abrir otra prenda | Comprobación de la URL efectiva por idioma (D110) | Test en los dos sentidos |
| 4 | Media | Vaciar un texto en inglés no lo borraba | Rasgo `Traducible` (D111) | Test |
| 5 | Media | Un color desactivado seguía en la tienda y en el precio | Variaciones «a la venta» en toda la tienda (D109) | Test + test de aceptación |
| 6 | Media | El visor ofrecía zonas que la prenda ya no tiene | Zonas activas de la prenda; al quitarla, sale de las maquetas (D109) | Tests |
| 7 | Media | Una zona fuera de la foto daba un error 500 | Validación en el campo, en centésimas (D112, D117) | Test |
| 8 | Media | Las variaciones se podían borrar | Solo se desactivan (D109) | Revisión de la ronda 2 |
| 9 | Media | Ningún test demostraba el criterio de aceptación | `AceptacionFase2Test` | El propio test |
| 10 | Media (cliente) | Las marcas de zona tapaban el diseño | Se apagan al colocar un diseño; guía fina al ajustar | Navegador |
| 11 | Baja | Se aceptaba cualquier dominio tras el proxy | `trustHosts` + `127.0.0.1` para la salud (D116) | Test: dominio falso → 400; `/up` → 200 |
| 12 | Baja | Fotos huérfanas al cambiar la imagen; borrado antes de confirmar | Borrado tras confirmar la transacción (D112, D117) | Test |
| 13 | Baja | Una maqueta que pasaba a modelo conservaba cara y zonas | Se limpian (D112) | Test |
| 14 | Baja | Un color usado en fotos se podía borrar | `enUso` incluye las fotos | Test |
| 15 | Baja | URLs con mayúsculas y familias con nombres de rutas futuras | Solo minúsculas; palabras reservadas (D110, D117) | Test |
| 16 | Baja | El IVA de Ajustes no admitía coma y redondeaba en silencio | La misma regla que los importes | Revisión de la ronda 2 |
| 17 | Baja | El `lastmod` del sitemap no cambiaba al editar variaciones o fotos | `$touches` | Revisión de la ronda 2 |
| 18 | Baja | La foto principal y el «Desde» del backend podían ser de un color retirado | `aLaVenta()` también ahí | Test de aceptación |
| 19 | Baja | Al volver a añadir una zona, las maquetas no la recuperaban | Se colocan solas al añadirla (D117) | Suite |
| 20 | Baja | Un dato manipulado en la vista previa del backend podía dar un error de PHP | Se ignoran las filas que no son válidas | Revisión |
| 21 | Baja | El visor no cargaba porque su script se añadía tarde; una medida salía como «28 cm × 38 cm» | Corregidos en el paso 2.5 | Test + navegador |

Además se corrigió un test de la fase 1: el de copias, que solo vale con SQLite, ahora se salta con MariaDB e indica el motivo.

## Pruebas superadas

- **Suite completa en SQLite**, como en Herd: 271 tests y 1193 comprobaciones, todos superados.
- **Suite completa en MariaDB 10.11 estricta**, con la configuración de XAMPP (`STRICT_TRANS_TABLES`, `explicit_defaults_for_timestamp=0`): 269 superados y 2 saltados (copias SQLite).
- **Migraciones en MariaDB estricta:** crear, datos de ejemplo, deshacer y volver a crear.
- **Migraciones en SQLite**, en un archivo nuevo.
- **Navegador** (Chromium con el dominio `nextgenapparel.test`):
  - ficha y visor en escritorio y en móvil (iPhone 13), sin desplazamiento horizontal ni errores en la consola;
  - logo y texto sobre la prenda, colección, lupa, giro, deslizar en móvil, ajustador y avisos de calidad;
  - cambio de idioma a la misma prenda;
  - cabecera del móvil;
  - vista previa del backend con el diseño de ejemplo.
- **Revisión del cliente en Herd** del visor: «Está bien y funciona».

## Pruebas no ejecutadas

- **Despliegue en OVH y la web detrás de Caddy real:** el servidor no está preparado. Se probó con cabeceras simuladas en los tests.
- **Safari y un móvil real:** solo se probó con Chromium y emulación de móvil. El arrastre con el dedo se simuló con eventos de puntero.
- **Tipografías del visor** (Impact y Segoe Script): no están instaladas en el entorno de pruebas. En Windows sí lo están.
- **Validación de los datos estructurados en Google** (Rich Results Test): la tienda no es pública.
- **Medición de Core Web Vitals:** no hay entorno de producción.
- **Revisión del cliente en Herd de los pasos 2.1–2.3 y 2.6.**

## Riesgos pendientes

| Riesgo | Gravedad | Situación |
|---|---|---|
| `APP_URL` mal escrito en el servidor: la web respondería con error 400 | Media | Documentado en `docs/DESPLIEGUE_OVH.md`; se comprueba al desplegar |
| Las posiciones de partida de las zonas están medidas sobre las fotos de muestra; con otro encuadre habrá que ajustarlas | Baja | El equipo las ajusta con la vista previa del diseño de ejemplo (D115) |
| El aviso de calidad del visor y el análisis del servidor (fase 4) deben medir igual | Baja | Decidido en D114: los dos recortan los márgenes transparentes |
| El precio «Desde» es el de la prenda sin imprimir | Baja | Pregunta abierta al cliente: si debe incluir la zona más barata |
| Las variaciones aún no tienen su clase de impuesto (regla 7) | Baja | Se añade en la fase 3, con Ajustes → Impuestos |

## Despliegue

1. Fusionar la rama `fase-2-catalogo` en `main`.
2. En el servidor: `git pull`, reconstruir la imagen y levantar los contenedores (`docs/DESPLIEGUE_OVH.md`). Las migraciones se aplican solas al arrancar.
3. Comprobar que `APP_URL` de cada entorno es su dominio exacto con `https://`.
4. Cargar el catálogo inicial: `php artisan db:seed --class=CatalogoInicialSeeder`, y opcionalmente `--class=EjemplosCatalogoSeeder` solo en pruebas.
5. Dejar **apagado** «Permitir que los buscadores indexen la tienda» (Ajustes → General) hasta el lanzamiento. En pruebas no se indexa nunca.
6. En Herd: `git pull`, `php artisan migrate` y `php artisan catalogo:recolocar-zonas` si hay maquetas subidas antes de este cambio.
