# Informe de calidad — Fase 5 (Configurador y área de cliente)

Rama `fase-5-cliente`. Fecha: 2026-11-08.

## Estado

**Terminado con limitaciones.** Todo lo de la fase está construido y probado:
- cuenta del cliente y área de cliente;
- backend de Clientes;
- carrito persistente, también para invitados;
- «Añadir a mi pedido» y página del carrito con maqueta;
- selector de Packs;
- cierre del pedido hasta el total.

El criterio de aceptación («un cliente configura un pedido con dos zonas y ve la maqueta y el precio correctos en móvil y escritorio») se ha comprobado en el navegador con fotos de maqueta de prueba, porque el catálogo de ejemplo no trae fotos reales ni maqueta trasera.

Pendiente:
- la revisión del cliente en Herd;
- el criterio del asesor sobre el IVA y el recargo del envío (viene de la fase 3).

Por decisión del director de proyecto (D163, D165, D166), la fase se ha **simplificado**:
- Packs sin ajustador ni zonas extra.
- Facturación igual a la dirección de envío.
- Datos fiscales del cliente con cuenta solo en «Mis datos».
- Confirmar y pagar quedan para las fases 6 y 7.

## Pasos

| Paso | Contenido | Nivel | Decisiones |
|---|---|---|---|
| 5.1–5.2 | Modelo de clientes, registro, entrada, verificación, contraseña, cuenta de invitado | N3 | D153–D155 |
| 5.3 | Área de cliente: Mis datos, Direcciones, Mis diseños, Contraseña, solicitud de borrado | N3 | D156–D157 |
| 5.4 | Backend → Clientes al estilo WooCommerce, recargo configurable | N3 | D158–D159 |
| 5.5 | Lógica del carrito persistente (sin precios guardados), suma al entrar, retención | N3 | D160–D162 |
| 5.6 | «Añadir a mi pedido», textos en el carrito, zonas con posición en mm, página del carrito | N2 (+ migraciones) | D164 |
| 5.7 | Selector de Packs sencillo | N2 | D165 |
| 5.8 | Cierra el pedido hasta el total (envío, IVA, recargo) | N3 | D166–D167 |
| 5.9 | Cierre de fase: Gauntlet (2 rondas) e informe | — | — |

## Rondas de revisión

Todas las hizo el instructor (`.claude/agents/instructor.md`), un subagente independiente del constructor.

| Revisión | Veredicto | Defectos |
|---|---|---|
| N3 pasos 5.1–5.2 | No listo | 3 altos, corregidos (D155) |
| N3 paso 5.3 | No listo | 3 altos, corregidos (D157) |
| N3 paso 5.4 | No listo | 2 altos, corregidos (D159) |
| N3 paso 5.5 | Listo con limitaciones | 3 medios y 8 bajos, corregidos salvo B5, riesgo asumido (D162) |
| N3 pasos 5.6–5.8 | No listo | 1 alto y 2 medios, corregidos (D167) |
| Gauntlet ronda 1 (fase completa, en navegador) | No lista | 1 alto, 2 medios y 1 bajo, corregidos (ver abajo) |
| Gauntlet ronda 2 (solo lo corregido) | _pendiente_ | _pendiente_ |

## Defectos encontrados y corregidos en el cierre de la fase

| Defecto | Gravedad | Corrección | Comprobación |
|---|---|---|---|
| La maqueta del carrito se estiraba desde 640 px: foto recortada y capas descolocadas | Alta | La maqueta se dibuja a la proporción de su foto y no se estira con la fila (`tienda.css`) | Ronda 2 en 768 y 1366 px |
| Se suponía que las fotos de maqueta eran 5:6 sin comprobarlo | Media | El carrito ya no depende de esa proporción; la ayuda de la subida pide fotos 5:6 | Ronda 2 |
| Un rectángulo de zona con otra proporción que la zona real hacía que el visor y el carrito mostraran tamaños distintos | Media | Regla en Catálogo → Prenda → Fotos: el rectángulo debe tener la proporción de la zona (margen del 5 %) | Test `test_el_rectangulo_de_una_zona_tiene_que_tener_su_proporcion` |
| Desbordamiento horizontal de 4 px en el carrito en móvil | Baja | `.carrito-rejilla` con `minmax(0, 1fr)` | Ronda 2: `scrollWidth` = 390 |
| Una foto que no cargaba dejaba promesas sin capturar y la carga colgada en el visor | Baja | Se captura el fallo y se reintenta al volver a pintar | Pasada de navegador sin errores de página |

## Pruebas superadas

- **Suite completa en SQLite:** 630 de 630.
- **MariaDB estricta (configuración XAMPP):** 625 superados y 2 saltados (copias SQLite). Se ejecutó tras el paso 5.8; los cambios posteriores no tocan la base de datos.
- **Migraciones de la fase en MariaDB estricta:** se crean, se deshacen y se rehacen.
- **Navegador (Playwright, 390 y 1366 px):**
  - configurador → carrito;
  - pack → carrito (99,00 €, el caso K1);
  - carrito → cierre como invitado (81,82 + 4,90 de envío = 86,72 de base, 18,21 de IVA y 104,93 € de total);
  - logo cuadrado en una zona dibujada con otra proporción, que se guarda en 90 × 90 mm.
- **Criterio de aceptación con dos zonas** (ronda 1 del Gauntlet): frontal con un diseño 2:1 y espalda con uno 1:2.
  - Se guardan en 280 × 140 mm y 200 × 400 mm.
  - El precio es el mismo en el configurador y en el carrito: 323,68 €.
  - El cierre suma bien: envío 6,90, base 274,40, IVA 57,62 y total 332,02.
  - En 390 px la maqueta es correcta; en escritorio fallaba, se corrigió y se verifica en la ronda 2.

## Pruebas no ejecutadas

- **Dominio de Herd (`.test`):** se probó con `artisan serve` y las imágenes desviadas. La revisión en Herd la hace el cliente.
- **Fotos de maqueta reales y foto trasera:** el catálogo de ejemplo no las tiene. Se usaron fotos de prueba.
- **Suite dentro de la imagen de Docker:** sigue pendiente desde la fase 4.
- **Interfaz en inglés:** solo se ha probado en los tests (`/en/cart`, `/en/cart/checkout`, `/en/packs/…`), no en el navegador.
- **Correos reales:** verificación, cambio de correo y aviso de borrado se prueban con notificaciones en cola en los tests. No se han enviado por SMTP.

## Riesgos pendientes

| Riesgo | Gravedad | Cuándo |
|---|---|---|
| IVA y recargo del envío según el criterio del asesor | Media | Antes de facturar (fase 6) |
| Confirmar el correo del invitado con un enlace al crear el pedido (D166) | Media | Fase 6 |
| Un cliente con pedidos no se borra: se anonimiza (D158) | Media | Fase 6 |
| En un ordenador compartido, el carrito de quien no entró pasa a quien entra (D161) | Baja | Asumido |
| Si se suben los días del carrito, un carrito de invitado puede durar más que su cookie (D162, B5) | Baja | Asumido |
| Packs sin ajustador ni zonas extra (D165) | Baja | Si el cliente lo pide |
| Las fotos de maqueta tienen que ser 5:6 y sus zonas proporcionadas; se pide en el backend, pero las fotos ya subidas no se revisan | Baja | Revisión del catálogo en Herd |

## Despliegue

1. Fusionar `fase-5-cliente` en `main` (después de `fase-4-disenos`) y reconstruir la imagen.
2. Ejecutar `php artisan migrate`. Las nuevas son: clientes y direcciones, carritos, posición de las zonas, y columnas pendientes de usuarios y clientes.
3. Comprobar que el contenedor de tareas programadas ejecuta `carritos:limpiar` (03:35) antes de `disenos:limpiar` (03:40).
4. En Catálogo → Zonas, revisar la posición de cada zona (frontal y pecho: 7,5 cm bajo el cuello; pecho: −10 cm del centro; espalda: 10 cm).
5. En Ajustes → General, revisar los días del carrito (30 por defecto) y quién indica el recargo de equivalencia.
6. Revisar las fotos de maqueta: proporción 5:6 y zonas proporcionadas.
