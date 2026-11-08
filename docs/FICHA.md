# NextGen Apparel — Ficha de arranque

> Fuente de verdad del proyecto. Versión 1.0 — 6 de noviembre de 2026.
> Las cifras marcadas como *ejemplo* son ilustrativas: los valores reales se configuran en el backend.

---

## 1. Qué es y para quién

Portal web para **calcular, vender y gestionar pedidos de prendas personalizadas con impresión DTI** (tecnología Innuro, evolución del DTF; D146) (camisetas, polos y sudaderas). Va dirigido a **pequeños diseñadores** que quieren producir sus colecciones: suben uno o varios diseños en PNG, eligen prenda, color, zonas de impresión y tallas, y el portal calcula el precio, cobra por Redsys, genera la factura y la orden de trabajo. NextGen Apparel imprime y envía.

**Compra en 3 pasos (D105):** 1) Sube tu diseño · 2) Escoge las prendas · 3) Cierra el pedido. Además, un **selector de Packs** con ofertas cerradas (§ 6 bis) como segunda vía de venta.

**Clientes:** particulares, autónomos y empresas.
**Idiomas:** español e inglés.
**Servidor:** dedicado de OVH (6+ núcleos, 8 GB+ de RAM), con Docker.

---

## 2. Alcance por versiones

### Versión 1 — Vender y producir
- Base técnica: Docker, entorno de pruebas y producción, roles, idiomas, log de errores, copias de seguridad, autosuficiencia.
- Catálogo con productos variables, stock y SEO técnico.
- Motor de precios: prendas, zonas, tramos, ofertas, mínimos, envío e impuestos (incluido el recargo de equivalencia).
- Diseños: subida de PNG, comprobación de calidad, declaración de derechos, revisión humana.
- Configurador y maqueta 2D, con el flujo de 3 pasos.
- **Packs**: ofertas configurables desde el backend con selector en la tienda (§ 6 bis).
- Pedidos: estados, visor de seguimiento, plazos estimados, cancelación, incidencias, hoja de compra, **repetir pedido**.
- Documentos: presupuesto, factura, factura rectificativa, orden de trabajo, paquete ZIP de producción.
- Pagos: Redsys y Bizum.
- Panel de ventas básico.
- Páginas legales y banner de cookies.

### Versión 2 — Vender más
- Asistente IA de venta (Claude u OpenAI).
- Asistente SEO con IA y blog.
- Carritos abandonados con cupón, campañas de nueva compra, reactivación.
- Panel de ventas avanzado con márgenes.
- Integración con APIs de transportistas y de proveedor de prendas.
- Pliegos DTI automáticos y tarifas por grupo de cliente.

> La base de datos se diseña desde el inicio para soportar la versión 2.

---

## 3. Tareas previas (cliente)

| Tarea | Necesaria para | Estado |
|---|---|---|
| Fotos frontal y trasera de cada prenda y color, con encuadre uniforme (manual y/o API de proveedor); fotos con modelo opcionales | Fase 5 (maqueta) | Pendiente |
| Tabla de medidas por talla de cada prenda | Fase 2 (guía de tallas) | Pendiente |
| Medidas máximas de impresión: frontal, pecho, espalda grande | Fase 3 (precios) y fase 4 (calidad) | Pendiente |
| Comercio Redsys con claves de pruebas; solicitar Bizum | Fase 7 | Pendiente |
| Proveedor SMTP y dominio con SPF, DKIM y DMARC | Fase 6 | Pendiente |
| Dominio definitivo | Puesta en marcha | Pendiente |
| Revisión del asesor: condiciones de venta, derechos sobre diseños, exclusión del desistimiento, recargo de equivalencia, Verifactu, IGIC si se envía a Canarias | Fases 6 y 10 | Pendiente |
| Datos de partida del catálogo: prendas, colores, tallas, composición, peso por talla, coste, proveedor | Fase 2 | Pendiente |
| Tarifas de transportistas por zona y peso o caja | Fase 3 | Pendiente |
| Plazos de producción por familia de prenda | Fase 6 | Pendiente |

---

## 4. Backend al estilo WooCommerce

| WooCommerce | NextGen Apparel |
|---|---|
| Escritorio | Panel de ventas y pedidos por estado |
| Pedidos (estados, notas privadas y al cliente, acciones en bloque) | Igual, con estados propios y órdenes de trabajo |
| Productos variables (atributos, variaciones con SKU, precio, stock, peso) | Prendas con atributos color × talla |
| Clientes | Igual, con biblioteca de diseños y ficha fiscal |
| Cupones | Reglas de precio, mínimos y ofertas (calibrador) y cupones |
| Ajustes → General | Datos de la empresa, idiomas, moneda |
| Ajustes → Impuestos | Clases de impuesto por zona (IVA, recargo de equivalencia, IGIC) |
| Ajustes → Envío | Zonas de envío y métodos (por kilo o por caja) |
| Ajustes → Pagos | Redsys y Bizum, modo pruebas o real |
| Ajustes → Correos electrónicos | Plantillas por evento, es/en, configuración SMTP |
| Analíticas | Informes de ventas con exportación CSV/Excel |
| Herramientas → Estado | Log de errores, salud del sistema, copias de seguridad |
| Roles: Administrador, Gestor de la tienda | Superadministrador, Administrador, Gestor de la tienda, Producción |

---

## 5. Modelo de datos (borrador para validar)

Importes en céntimos (enteros). Campos traducibles marcados con *(es/en)*.

### Catálogo
- **familias**: id, nombre *(es/en)*, slug *(es/en)*, plazo_base_dias, orden. — *Camisetas, Polos, Sudaderas.*
- **prendas**: id, familia_id, proveedor_id, nombre *(es/en)*, descripcion *(es/en)*, composicion *(es/en)*, gramaje, detalles_tecnicos *(es/en)*, activa, campos SEO *(es/en)*.
- **atributos / valores_atributo**: color (con código hex), talla.
- **variantes**: id, prenda_id, color_id, talla_id, sku, precio_base, coste, peso_gramos, stock, stock_minimo, activa.
- **zonas_impresion**: id, nombre *(es/en)* (frontal, pecho, espalda grande), ancho_max_cm, alto_max_cm, cara (delantera/trasera).
- **prenda_zona**: prenda_id, zona_id, precio_zona, coste_zona — zonas disponibles por prenda.
- **fotos_maqueta**: prenda_id, color_id, cara, imagen, rectangulo_zona (x, y, ancho, alto por zona).
- **fotos_galeria**: prenda_id, color_id (opcional), tipo (modelo / detalle / plana), imagen, orden, origen (manual / api).
- **tabla_medidas**: prenda_id, talla_id, largo_cm, ancho_cm, manga_cm (para la guía de tallas).
- **proveedores**: id, nombre, tipo_conector (manual / api), configuracion.
- **packs**: id, nombre *(es/en)*, slug *(es/en)*, descripcion *(es/en)*, precio (céntimos, sin IVA) + tipo de impuesto, reparto (fijo / el cliente reparte), activo, desde / hasta (opcional), orden, imagen, campos SEO *(es/en)*.
- **lineas_pack**: pack_id, prenda_id, unidades, colores_permitidos (uno fijo o varios a elegir), reparto_tallas (si es fijo: talla → unidades; si no, tallas permitidas).
- **pack_zona**: pack_id, zona_id — zonas cuya impresión incluye el precio.

### Clientes
- **clientes**: id, usuario_id, tipo (particular / autónomo / empresa), nombre o razón social, nif, recargo_equivalencia (sí/no), idioma, consentimiento_marketing (fecha y origen).
- **direcciones**: cliente_id, tipo (facturación / envío), datos, zona_envio_id.
- **disenos**: id, cliente_id, archivo, ancho_px, alto_px, tiene_transparencia, perfil_color, declaracion_derechos (fecha), estado.

### Reglas
- **reglas_minimo**: id, nombre, tipo (unidades_total / unidades_por_talla / importe_minimo), valor, ámbito (catálogo / familia / prenda), ambito_id, vigencia, prioridad, activa.
- **reglas_precio**: id, nombre *(es/en)*, tipo (tramo_cantidad / combinada / descuento), condiciones (JSON validado), efecto (precio_fijo / porcentaje / importe), ámbito, vigencia, prioridad, activa.
- **cupones**: código, tipo, valor, usos máximos, un uso por cliente, caducidad.
- **zonas_envio / tarifas_envio**: zona (países, provincias, códigos postales), método (por_kilo / por_caja), tramos, precio, plazo_transportista_dias.
- **cajas**: nombre, capacidad_prendas, peso_max, precio.
- **clases_impuesto / tipos_impuesto**: zona fiscal, tipo de IVA, recargo de equivalencia, IGIC.
- **calendario_laboral**: festivos.

### Pedidos y documentos
- **carritos / lineas_carrito**: persistentes para clientes registrados, restaurables.
- **pedidos**: numero, cliente_id, estado_interno, idioma, direcciones (copia), subtotal, descuentos, envio, impuestos, total, desglose_calculo (JSON: cada paso del motor de precios), fecha_estimada_entrega, transportista, numero_seguimiento.
- **lineas_pedido**: pedido_id, prenda_id, color_id, reparto_tallas, precio_unitario, total.
- **impresiones_linea**: linea_pedido_id, zona_id, diseno_id, ancho_cm, alto_cm, posicion (mm desde el cuello y desde el centro de la prenda, D99), ppp_efectivo, estado_revision.
- **maquetas_linea**: linea_pedido_id, cara (delantera / trasera), ruta, generada_en. Una imagen por cara con todos sus diseños compuestos sobre la prenda y el color del pedido (D98). Se congela al pagar y se regenera si el equipo aprueba un cambio de diseño.
- **historial_estados**: pedido_id, estado, usuario_id, fecha, nota, notificado_cliente.
- **notas_pedido**: pedido_id, tipo (privada / cliente), texto, autor.
- **presupuestos**: numero, cliente_id, contenido, validez, estado.
- **pagos**: pedido_id, metodo (redsys / bizum), referencia, importe, estado, respuesta_redsys.
- **reembolsos**: pago_id, importe, motivo, estado.
- **facturas**: serie, numero (correlativo), tipo (completa / rectificativa), pedido_id, datos fiscales (copia), base, impuestos, total, huella_registro (Verifactu), pdf.
- **ordenes_trabajo**: pedido_id, estado, paquete_zip.
- **incidencias**: pedido_id, motivo, fotos, resolucion (reimpresión / reembolso parcial / rechazo), estado.
- **hojas_compra**: fecha, pedidos incluidos, líneas por variante, estado.

### Sistema
- **registro_errores**, **registro_actividad**, **copias_seguridad**, **comprobaciones_salud**, **plantillas_correo** *(es/en)*, **estados_visibles** (estado interno → texto para el cliente *(es/en)*), **ajustes**.

---

## 6. Motor de precios

### Orden de cálculo
1. **Prendas:** precio base de cada variante × unidades.
2. **Impresión:** precio de cada zona × unidades de la línea.
3. **Tramos por cantidad.**
4. **Ofertas combinadas:** se evalúan todas las aplicables y se aplica **la mejor para el cliente**.
5. **Cupón**, si lo hay.
6. **Comprobación de mínimos:** si no se cumplen, el pedido no se puede confirmar y se explica qué falta.
7. **Envío:** por peso total o por número de cajas, según la zona de destino.
8. **Impuestos:** IVA, más el recargo de equivalencia si el cliente lo tiene.

Cada paso queda guardado en `desglose_calculo`, visible en el backend.

### Mensajes al cliente
- Oferta aplicada: «Cumples dos condiciones para un mejor precio: aplicamos *Combinado 15+15*.»
- Oferta cercana: «Te faltan 3 sudaderas para conseguir *Combinado 15+15*.»
- Mínimo no cumplido: «El pedido mínimo es de 15 prendas. Llevas 12.»

### Casos de prueba (*ejemplo*, con precios sin IVA)

Supuestos: camiseta 4,00 €; polo 7,00 €; sudadera 12,00 €; frontal 3,00 €; espalda grande 4,00 €; pecho 2,00 €. Mínimo: 15 prendas por pedido. Tramo: ≥ 50 prendas, −10 % en prendas. Oferta combinada: ≥ 15 camisetas + ≥ 15 polos o sudaderas, −12 % en todo el pedido.

| # | Pedido | Resultado esperado |
|---|---|---|
| P1 | 15 camisetas negras, frontal | 15 × (4,00 + 3,00) = **105,00 €** |
| P2 | 14 camisetas, frontal | **No confirmable**: «Te falta 1 prenda para el mínimo» |
| P3 | 15 camisetas frontal + 15 polos pecho | (105,00 + 135,00) × 0,88 = **211,20 €**; mensaje de oferta aplicada |
| P4 | 10 camisetas + 5 sudaderas, frontal | Cumple el mínimo (15) sin oferta: 70,00 + 75,00 = **145,00 €**; mensaje «Te faltan 5 camisetas y 10 sudaderas para *Combinado 15+15*» |
| P5 | 50 camisetas, frontal + espalda | Tramo: 50 × 4,00 × 0,90 + 50 × 7,00 = **530,00 €** |
| P6 | 50 camisetas frontal + 15 sudaderas frontal (65 prendas) | Sin descuentos: prendas 380,00 + impresión 195,00 = 575,00 €. Tramo (−10 % en prendas): 342,00 + 195,00 = 537,00 €. Combinada (−12 % en todo): 575,00 × 0,88 = 506,00 €. Se aplica **la combinada (506,00 €)** por ser mejor para el cliente; mensaje «Cumples dos condiciones…» |
| P7 | P1 para un autónomo con recargo de equivalencia | 105,00 + 22,05 (IVA 21 %) + 5,46 (recargo 5,2 %) = **132,51 €** |

> **Fase 6:** en el pago, exigir el código postal cuando el país tenga zonas con prefijos (sin él se aplica la zona general).

#### Envíos y cupones (*ejemplo*, sin IVA)

Supuestos: Península (ES) por peso: hasta 2 kg 4,90 €, hasta 5 kg 6,90 €, hasta 10 kg 9,90 €, 0,80 € por kilo de más, gratis desde 500,00 €. Baleares (CP 07…) por cajas: 12,00 € por caja de 50 prendas o 20 kg. Pesos: camiseta 150 g, sudadera 480 g.

| # | Caso | Resultado esperado |
|---|---|---|
| E1 | P1 a Madrid (2,25 kg) | Envío **6,90 €**, con IVA como la mercancía; aviso «Te faltan 395,00 € (sin IVA) para el envío gratis» |
| E2 | P6 a Sevilla (506,00 €) | Envío **gratis** |
| E3 | 50 camisetas a Baleares / 65 prendas a Baleares | **1 caja (12,00 €)** / **2 cajas (24,00 €)** |
| E4 | Pedido a Francia sin zona configurada | **No confirmable**: «Todavía no enviamos a ese destino» |
| E5 | 25 sudaderas a Madrid (12 kg) | 9,90 + 2 × 0,80 = **11,50 €** |
| C1 | P1 con cupón «verano10» del 10 % (sin distinguir mayúsculas) | 105,00 − 10,50 = **94,50 €** |
| C2 | Cupón caducado, inactivo, agotado, ya usado o por debajo de su mínimo | No se aplica, se explica el motivo y **el pedido sigue siendo confirmable** |

> Los casos P1–P7, K1–K4, E1–E5 y C1–C2 son tests automáticos (`tests/Feature/Precios/MotorPreciosTest.php`). Las reglas de combinación entre tramo y oferta (excluyentes o acumulables) se confirmarán con el cliente; por defecto son **excluyentes** y se aplica la mejor.

---

## 6 bis. Packs (D105–D107)

Ofertas cerradas que el equipo crea en **Productos → Packs** y el cliente elige en el **selector de Packs** de la tienda. *Ejemplo:* «Pack joven»: 10 camisetas negras (2 L, 5 XL, 3 XXL) con impresión frontal por **99,00 €**.

- **Composición:** una o varias prendas con sus unidades. En cada pack se decide si el reparto es **fijo** (colores y tallas cerrados) o si **el cliente reparte** las unidades entre las tallas (y elige color entre los permitidos). El total de unidades no cambia.
- **Precio:** importe cerrado (en céntimos, sin IVA, con su tipo de impuesto; se muestra con IVA). Incluye las prendas y la impresión de las **zonas fijadas** en el pack. Añadir otra zona se cobra aparte con la tarifa normal de esa zona × unidades.
- **Visibilidad:** público en la tienda, con fechas opcionales de inicio y fin.
- **Ofertas:** el precio del pack ya es la oferta: **no admite cupones, tramos ni ofertas combinadas**. Si en el mismo carrito hay otras prendas sueltas, a esas sí se les aplican sus reglas.
- **Mínimos:** un pack cumple por sí solo el pedido mínimo.
- **Flujo:** el cliente elige el pack, sube su diseño (con el ajustador y el aviso de calidad), reparte tallas si el pack lo permite y cierra el pedido.
- **Motor de precios:** el pack es una línea con precio cerrado; el motor suma los extras de zona y aplica envío e impuestos como al resto. Se prueba con casos propios (K1–K4) en la fase 3.

| # | Caso (*ejemplo*, precios sin IVA) | Resultado esperado |
|---|---|---|
| K1 | «Pack joven» 81,82 € (99,00 € con IVA), reparto fijo | **81,82 €**; el reparto de tallas no se puede cambiar |
| K2 | K1 + espalda grande (4,00 €) | 81,82 + 10 × 4,00 = **121,82 €** |
| K3 | K1 con un cupón del 10 % | El cupón **no se aplica** al pack; mensaje «Los packs ya tienen su precio especial» |
| K4 | Pack con reparto libre de 10 unidades; el cliente reparte 9 | **No confirmable**: «Te falta repartir 1 prenda» |

---

## 7. Diseños y calidad

- Solo **PNG**. Al subirlo se exige marcar: «Declaro que tengo los derechos sobre este diseño».
- La calidad se calcula como **ppp efectivo = píxeles ÷ (cm de impresión ÷ 2,54)** según el tamaño elegido en el configurador.

| ppp efectivo | Resultado |
|---|---|
| ≥ 300 | Correcto |
| 200–299 | Aviso leve: «Se imprimirá bien, pero podría ganar nitidez» |
| 150–199 | Aviso: «Ojo, tu diseño no tiene la calidad que buscas», con consejos para mejorarlo |
| < 150 | Aviso fuerte; se recomienda no continuar sin mejorarlo |

*(Umbrales configurables en Ajustes → General, D101.)* Se comprueba también: transparencia, fondo blanco no deseado, perfil de color y medidas mínimas.

- **Aviso inmediato en la ficha (D100):** el visor ya calcula la calidad al tamaño elegido con el ajustador, sin subir el diseño. La vista previa admite también JPG, SVG o WebP para probar, pero avisa de que el pedido necesita un PNG con fondo transparente.

- **Revisión humana** tras el pago: aprobado / requiere cambios (con enlace para subir otro diseño) / rechazado por derechos de autor o marca (con reembolso).

---

## 8. Pedidos

### Estados
| Estado interno | Visible para el cliente *(editable es/en)* | Puede cancelar |
|---|---|---|
| Pendiente de pago | Pendiente de pago | Sí |
| Pagado | Pedido recibido | Sí |
| Diseño en revisión | Revisando tu diseño | Sí |
| Requiere cambios | Necesitamos tu ayuda | Sí |
| En producción | Pedido en proceso de impresión | **No** |
| Enviado | Pedido en reparto | No |
| Entregado | Entregado | No |
| Cancelado / Reembolsado | Cancelado / Reembolsado | — |

- Cada cambio de estado puede enviar un correo configurable y una nota visible para el cliente.
- Cambio de estado individual o en bloque desde la lista de pedidos.
- Al pasar a «Enviado» se piden transportista y número de seguimiento.
- **Cancelación por el cliente:** reembolso por Redsys (automático o con aprobación, según ajustes), factura rectificativa y correo.

### Plazos estimados
Fecha estimada = plazo base de la familia + días extra por tramo de unidades + plazo del transportista, contando solo días laborables. Se puede ajustar a mano en cada pedido, con aviso al cliente.

### Visor del cliente
Línea de progreso con fechas, maquetas, resumen del pedido, factura en PDF, seguimiento del envío, fecha estimada, botón cancelar (si procede), botón **repetir pedido** y apertura de **incidencias** con fotos.

### Maquetas del pedido (D98)
- Al confirmar el pedido, el servidor compone con Imagick **una imagen por cara** (delantera y trasera) de cada línea: la foto de la prenda en el color pedido con todos sus diseños en la posición y tamaño elegidos, con el mismo fundido con la tela que el visor.
- Se adjuntan en el **PDF de confirmación del pedido** que recibe el cliente y en la **orden de trabajo** del equipo, y se muestran en el visor del cliente y en la ficha del pedido del backend.
- Es la referencia de **lo que el cliente aprobó**: si hay un cambio de diseño, se genera una versión nueva y la anterior queda en el historial.
- Se generan en la cola con reintentos; si fallan, el pedido sigue adelante y se avisa por la alerta de errores.

### Producción
- **Orden de trabajo** en PDF por pedido, con las maquetas de cada cara y las medidas de cada impresión.
- **Paquete ZIP**: diseños originales (`PED-0042_L1_frontal.png`), orden de trabajo y maquetas.
- **Hoja de compra**: agrupa las variantes necesarias de todos los pedidos pagados, descontando el stock.

---

## 8 bis. Ficha de producto

Referencia de estilo: elegante y limpia, con mucho espacio en blanco, fotos grandes y tipografía cuidada. Prototipo visual en el canvas «NextGen Apparel — Ficha de producto».

- **Galería:** vista delantera y trasera que cambia con el color elegido, botón para mostrar las **zonas de impresión** sobre la prenda y miniaturas (delantera, trasera, foto con modelo, detalle del tejido).
- **Información:** familia, nombre, precio **«Desde X €/ud. · IVA incluido»**, aviso del pedido mínimo, colores, tallas disponibles (solo informativas), enlace a la guía de tallas y zonas de impresión con su medida máxima.
- **Acciones:** «Personalizar con mi diseño» (abre el configurador) y «Solicitar presupuesto».
- **Pasos:** sube tu diseño → elige tallas y cantidades → lo imprimimos y sigues tu pedido.
- **Acordeones:** detalles de la prenda, zonas de impresión y calidad, plazos y envío, personalización y garantía.
- **Solo precios:** sin precios tachados, sin porcentajes de descuento y sin mensajes de financiación. Las ofertas y tramos se explican en el configurador.
- **Cantidades por talla:** se eligen **en el configurador**, no en la ficha.
- **Fotos:** las sube el equipo y/o llegan de una API de proveedor; ambos orígenes conviven. Cada prenda necesita fotos planas (delantera y trasera, por color) para la maqueta; las fotos con modelo son opcionales. Si no hay, la galería usa las planas.


> «Solicitar presupuesto» en la ficha llega en la fase 6, con los presupuestos en PDF.
---

## 9. Documentos y pagos

- **Presupuesto** en PDF, con validez configurable; convertible en pedido.
- **Factura** completa en PDF, numeración correlativa por serie, enviada por correo en el idioma del cliente.
- **Factura rectificativa** en cancelaciones, reembolsos e incidencias.
- **Registro de facturación preparado para Verifactu** (huella encadenada); el envío a la AEAT se activa cuando corresponda.
- **Redsys**: cobro del 100 % al formalizar; notificación del pago verificada por firma; tarea que cuadra pagos sin notificación. **Bizum** si el banco lo activa.

---

## 10. Autosuficiencia y sistema

- Tareas programadas: copias de seguridad, limpieza de diseños sin usar, cuadre de pagos, caducidad de presupuestos y cupones.
- Colas con reintentos para correo, PDF, ZIP, maquetas e IA.
- Contenedores con reinicio automático, SSL renovado automáticamente, rotación de logs, vigilancia del disco.
- **Comprobación de salud** (base de datos, colas, SMTP, disco, copias) con alerta por correo.
- **Log de errores** agrupado, con gravedad, contexto y estado resuelto/pendiente; alertas de errores críticos.
- **Registro de actividad** de los administradores.
- **Copias de seguridad**: base de datos diaria y archivos incremental diaria, copia fuera del servidor (OVH Backup/Object Storage), cifradas, retención configurable (*ejemplo*: 7 diarias, 4 semanales, 12 mensuales), prueba de restauración periódica, estado visible en el backend. Restauración completa por procedimiento documentado.

---

## 11. Panel de ventas (versión 1)

Ventas de hoy, la semana y el mes con comparativa; número de pedidos; ticket medio; prendas vendidas; pedidos por estado; lo más vendido (prenda, color, talla, zona); ofertas aplicadas; clientes nuevos y recurrentes; filtros por fecha y exportación CSV/Excel.

---

## 12. SEO técnico (versión 1)

URLs traducidas (`/es/...`, `/en/...`), `hreflang`, `canonical`, sitemap por idioma, `robots.txt`, datos estructurados (Product, Offer, BreadcrumbList, Organization), Open Graph, Core Web Vitals. Páginas privadas fuera del índice.

---

## 13. Legal (versión 1, con revisión del asesor)

Aviso legal, privacidad, cookies y condiciones de venta (es/en); banner de cookies con consentimiento real; exclusión del desistimiento para productos personalizados (art. 103 c TRLGDCU) y garantía legal ante defectos; cláusula de derechos sobre los diseños; casilla de consentimiento de marketing sin marcar por defecto.

---

## 14. Versión 2 (descripción para no perderla)

- **Asistente IA de venta**: solo clientes registrados; guía en pasos lógicos; consulta el motor de precios mediante herramientas; nunca inventa precios; límite de gasto; se oculta si la API falla. Proveedor configurable (Claude u OpenAI).
- **Asistente SEO con IA**: panel tipo Yoast por producto, categoría, página y entrada; genera borradores de título, metadescripción, descripción, texto alternativo y traducción a partir de los datos reales; aprobación obligatoria; generación en bloque.
- **Blog** bilingüe con categorías y panel SEO.
- **Carritos abandonados**: secuencia configurable (*ejemplo*: 1 h, 24 h, 72 h con cupón), maqueta del diseño del cliente en el correo, enlace que restaura el carrito, parada automática al comprar.
- **Nueva compra**: correo tras la entrega, sugerencia del mismo diseño en otra prenda, reactivación tras X días, campañas por temporada y segmento; estadísticas de recuperación.
- **Correo de marketing** con remitente separado y enlace de baja.
- **Integraciones**: APIs de transportistas (seguimiento automático) y de proveedor de prendas.
- **Panel avanzado** con márgenes reales.
- **Pliegos DTI** automáticos y **tarifas por grupo de cliente**.

---

## 15. Fases de la versión 1 y criterios de aceptación

| Fase | Contenido | Se acepta cuando… |
|---|---|---|
| 1. Base | Laravel, Filament, Docker (pruebas y producción), roles, idiomas, log de errores, registro de actividad, copias de seguridad, comprobación de salud, alertas | Los contenedores arrancan desde cero con un comando; se hace y se restaura una copia; un error provocado aparece en el log y genera alerta |
| 2. Catálogo | Familias, prendas, variantes, zonas, fotos de maqueta, proveedores (manual), stock, SEO técnico | Se crea una prenda variable completa desde el backend y se ve en el frontend en es/en con su sitemap y datos estructurados |
| 3. Motor de precios | Tramos, ofertas, mínimos, cupones, envíos, impuestos, recargo de equivalencia, desglose, **packs (modelo, backend y precio)** | Los casos P1–P7, K1–K4 y los de envío pasan como tests automáticos |
| 4. Diseños | Subida PNG, cálculo de ppp efectivo, avisos, declaración de derechos, biblioteca de diseños | Tests con PNG reales de distintas resoluciones dan el aviso correcto según el tamaño de impresión |
| 5. Configurador y área de cliente | Flujo de 3 pasos, configurador, **selector de Packs**, maqueta 2D, carrito persistente, registro, área de cliente, visor de pedido, repetir pedido | Un cliente configura un pedido con dos zonas y ve la maqueta y el precio correctos en móvil y escritorio |
| 6. Pedidos y documentos | Estados, notas, acciones en bloque, plazos, cancelación, incidencias, presupuesto, factura, rectificativa, orden de trabajo, maquetas del pedido por cara, ZIP, hoja de compra, correos SMTP | El ciclo completo de un pedido genera todos los documentos y correos en el idioma del cliente; el PDF del cliente y la orden de trabajo llevan la maqueta de cada cara |
| 7. Pagos | Redsys (y Bizum) en pruebas, verificación de firma, cuadre de pagos, reembolsos | Pagos, fallos y reembolsos en el entorno de pruebas de Redsys funcionan y quedan registrados |
| 8. Panel de ventas y legal | Panel de ventas, exportación, páginas legales, banner de cookies | Los datos del panel cuadran con los pedidos de prueba |
| 9. Auditoría Gauntlet | Revisión adversarial según `docs/GAUNTLET.md` | Sin defectos críticos ni altos abiertos |
| 10. Puesta en marcha | Producción, dominio, Redsys real, SMTP real | Primer pedido real completado |

---

## 16. Estado

| Fase | Estado | Fecha |
|---|---|---|
| Planteamiento | Completado | 2026-11-06 |
| 1. Base | **Completada con limitaciones** (informe: `docs/INFORME_FASE_1.md`). Criterios de aceptación cumplidos en Docker; pendiente desplegar en OVH y verificar el paso 1.6 en Herd | 2026-11-06 |
| 2. Catálogo | **Completada con limitaciones** (informe: `docs/INFORME_FASE_2.md`). Criterio de aceptación cumplido con un test de principio a fin, en SQLite y en MariaDB estricta; pendiente la revisión del cliente en Herd de los últimos pasos y el despliegue en OVH | 2026-11-07 |
| 3. Motor de precios | **Completada con limitaciones** (informe: `docs/INFORME_FASE_3.md`). Casos P1–P7, K1–K4 y E1–E5 como tests en SQLite y MariaDB estricta; pendiente el asesor (IVA del envío y recargo). D127 confirmada y revisión en Herd hecha | 2026-11-08 |
| Visual V1 | Completado: sistema de diseño, cabecera, pie y portada premium (`docs/PLAN_VISUAL.md`); pendiente la revisión en Herd | 2026-11-08 |
| Configurador (adelanto de la fase 5) | **En curso** (D141, D142): el configurador es la página de producto, con 3 niveles (diseño, tallas y colores, resumen) y el precio en directo del motor; pendiente la subida real del PNG (fase 4), el carrito y el pago | 2026-11-08 |
| 4. Diseños | **En curso** (rama `fase-4-disenos`). Hecho: 4.1 (tabla `disenos`, disco privado, límites en Ajustes), 4.2 (análisis en el servidor, D147), 4.3 (subida con declaración de derechos, miniatura solo para su dueño), 4.4 (el configurador sube los logos al pasar a tallas y colores), las correcciones de la revisión N3 (D148) 4.5 (biblioteca en la tienda y en el backend, D149) y 4.6 (retención y copias, D150). Pendiente: cierre con Gauntlet; la prueba con PNG reales de clientes y la suite dentro de la imagen de Docker | 2026-11-09 |
