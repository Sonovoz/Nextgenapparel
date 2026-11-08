# Informe de calidad — Fase 4 (Diseños)

Según la sección 14 de `docs/GAUNTLET.md`. Rama `fase-4-disenos`, 8 de noviembre de 2026.

## Estado

**Terminado con limitaciones.** Todo lo de la fase está construido y probado. El criterio de aceptación se cumple **con PNG sintéticos**, no con PNG reales de clientes, porque el cliente todavía no los ha aportado. La suite no se ha ejecutado dentro de la imagen de Docker (ImageMagick 7); en el servidor lo vigila la comprobación de salud «Procesado de imágenes». Falta la revisión del cliente en Herd.

| Criterio (ficha § 15) | Resultado | Cómo se comprobó |
|---|---|---|
| Tests con PNG reales de distintas resoluciones dan el aviso correcto según el tamaño de impresión | **Cumplido en parte** | Con PNG sintéticos (`tests/Support/PngDePrueba.php`): cálculo de ppp sobre lo que se imprime, umbral de alfa 8/9, márgenes, paleta, gris, 16 bits, perfil de color y límites. Faltan exportaciones reales (Photoshop, Illustrator, Canva, Procreate) |

## Pasos

| Paso | Contenido | Nivel |
|---|---|---|
| 4.1 | Tabla `disenos`, disco privado y límites en Ajustes → General | N3 |
| 4.2 | Análisis del PNG en el servidor (`AnalizadorPng`, D147) | N2 |
| 4.3 | Subida con declaración de derechos, miniatura solo para su dueño | N3 |
| 4.4 | El configurador sube los logos al pasar a tallas y colores | N2 |
| 4.5 | Biblioteca en la tienda y en el backend (D149) | N3 |
| 4.6 | Retención y archivos huérfanos (D150) | N3 |

## Rondas de revisión

Tres revisiones del agente `instructor`, independiente del constructor:

1. **Revisión N3 de 4.1–4.3.** Veredicto «no listo»: 1 crítico, 2 altos, 4 medios y 5 bajos. Corregidos (D148).
2. **Gauntlet, ronda 1** (fase completa, con Playwright). Veredicto «no listo»: 3 altos, 6 medios y 8 bajos. Corregidos (D151).
3. **Gauntlet, ronda 2.** Veredicto «listo con limitaciones»: 4 medios y 3 bajos. Corregidos y comprobados con los guiones del auditor (D152).

Además, las pasadas de navegador del constructor encontraron dos fallos que ningún test detectaba (miniatura vacía y límite de tiempo de ImageMagick).

## Defectos encontrados y corregidos

| # | Gravedad | Defecto | Corrección | Prueba |
|---|---|---|---|---|
| 1 | Crítica | Con ImageMagick 7 (Docker) la transparencia se leía al revés | `ALPHACHANNEL_EXTRACT`, igual en la 6 y la 7 | Tests y comprobación de salud con un PNG real |
| 2 | Crítica | El límite de tiempo de ImageMagick cuenta desde que arranca el proceso: en PHP-FPM rechazaba todas las imágenes al minuto | Sin límite de tiempo | Test y pasada de navegador con el servidor en marcha más de 75 s |
| 3 | Alta | Docker sin WebP en ImageMagick: fallarían todas las subidas | `imagemagick-webp` y política propia | Comprobación de salud |
| 4 | Alta | Un PNG trampa de 520 KB consumía 2 GB de RAM | Límites de memoria y 2 análisis a la vez | Medido: 40 MB; test del semáforo |
| 5 | Alta | La miniatura se guardaba vacía (un «fn» captura por valor) | Cierre normal | Test de la miniatura |
| 6 | Alta | Cambiar el logo durante la subida dejaba la zona con el anterior | Logo bloqueado durante la subida y respuesta solo para el archivo enviado | Guiones t2 y t9 |
| 7 | Alta | Un diseño quitado de la biblioteca seguía en la zona | Se invalida en las zonas | Guion t5 |
| 8 | Alta | Un empleado desactivado con la sesión abierta descargaba originales | Mismas condiciones que el panel | Test |
| 9 | Media | Un fallo de ImageMagick daba un error 500 | Aviso «procesar» | Test |
| 10 | Media | Dos subidas iguales a la vez creaban dos diseños | Índice único y recuperación | Test |
| 11 | Media | Visitantes sin cuenta podían llenar el disco | Tope diario, subidas por hora en Ajustes y sin subidas con el disco casi lleno | Tests (salvo el del disco casi lleno) |
| 12 | Media | La casilla de derechos salía marcada para un logo nuevo | Una declaración por logo | Guion t10 |
| 13 | Media | Texto de privacidad falso («no lo subimos») | Dice cuándo se sube y cuándo se borra, con los días de Ajustes | Test |
| 14 | Media | Sesión caducada (419) con un mensaje en inglés | Aviso propio en es/en | Guion t5 |
| 15 | Media | Los avisos del servidor (perfil de color) no llegaban al cliente; luego quedaban obsoletos | Se muestran en el nivel 2 y se actualizan | Guion t8 |
| 16 | Media | Pérdida del foco del teclado (WCAG 2.4.3 y 2.4.7) | Foco conservado y visible | Guiones t6 y t10 |
| 17 | Media | El original se descargaba con la extensión del cliente (`.html`) | Siempre `.png` | Test |
| 18 | Baja | Otras: `uuid` distinto entre MariaDB 11 y 10.4, nombres con U+202E, fondo medido con el margen, versión duplicada de `visor.js`, carpetas vacías, borrado de carpetas durante una subida, PNG sin tipo MIME, original de la portada mezclado entre pestañas, singular de «días» | Corregidas | Tests o guiones |

## Pruebas superadas

- Suite completa: **442 tests** en SQLite; en MariaDB 10.11 estricta (configuración de XAMPP), 440 superados y 2 saltados (copias SQLite).
- Migración en MariaDB estricta: crear, deshacer y rehacer.
- Navegador (Playwright, escritorio y móvil):
  - Subida con y sin declaración.
  - Rechazo de JPG y de archivos pesados.
  - Reutilización desde la biblioteca en otra prenda.
  - Quitar diseños.
  - Backend con el rol Gestor de la tienda: lista, ficha y descarga.
  - Navegación con teclado.
  - Sesión caducada.
  - Sin errores en la consola.
- Política de ImageMagick: permite PNG y WebP y bloquea SVG.

## Pruebas no ejecutadas

- **PNG reales de clientes:** no se han aportado.
- **Suite dentro de la imagen de Docker (ImageMagick 7):** aquí no hay Docker. La comprobación de salud analiza un PNG de prueba y genera WebP en el servidor.
- **MariaDB 10.4 de XAMPP:** probada la 10.11 con la misma configuración estricta.
- **Lector de pantalla real:** solo se probó con teclado.
- **Disco casi lleno:** está en el código, sin test.
- **Revisión del cliente en Herd.**

## Riesgos pendientes

| Riesgo | Gravedad | Situación |
|---|---|---|
| Comportamiento con exportaciones reales (sombras con alfa bajo, perfiles P3, APNG) | Media | Probar cuando el cliente aporte PNG |
| ImageMagick 7 sin ejecutar la suite | Media | Comprobación de salud en el servidor; ejecutar `php artisan test --filter=Disenos` en el contenedor tras el despliegue |
| Retención de los diseños en las copias de seguridad (hasta 12 meses) | Media | Política de privacidad y registro de tratamientos en la fase 8, con validación jurídica |
| Diseños en un pedido o un carrito | Media | Fases 5 y 6: la limpieza no debe borrarlos (`LimpiarDisenos::disenosCaducados()`) |
| Diseños de clientes con cuenta: duplicados (`propietario` nulo) y retención | Baja | Fase 5 |
| El tope diario por visitante se salta borrando la cookie | Baja | Solo frena el límite por IP |

## Despliegue

1. Fusionar `fase-4-disenos` en `main` y reconstruir la imagen (`imagemagick-webp` y la política de ImageMagick).
2. Ejecutar `php artisan migrate`.
3. Comprobar en Herramientas → Estado del sistema que «Procesado de imágenes» está en verde.
4. Ejecutar `php artisan test --filter=Disenos` dentro del contenedor.
5. Revisar en Ajustes → General los límites de «Archivos de diseño».
