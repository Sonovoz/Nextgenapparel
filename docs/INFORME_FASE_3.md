# Informe de calidad — Fase 3 (Motor de precios)

Según la sección 14 de `docs/GAUNTLET.md`. Rama `fase-3-precios`, 8 de noviembre de 2026.

## Estado

**Terminado con limitaciones.** El criterio de aceptación («los casos P1–P7 y los de envío pasan como tests automáticos») se cumple. Limitaciones: el asesor debe confirmar el trato del envío y del recargo de equivalencia (D122); el cliente, la regla de mínimos con packs (D127); y falta la revisión en Herd.

| Criterio (ficha § 15) | Resultado | Cómo se comprobó |
|---|---|---|
| P1–P7, K1–K4 y envíos como tests automáticos | **Cumplido** | `tests/Feature/Precios/MotorPreciosTest.php` con importes exactos; pasa en SQLite y en MariaDB 10.11 estricta |

## Pasos

| Paso | Contenido | Nivel |
|---|---|---|
| 3.1 | Modelo: clases de impuesto, mínimos, ofertas, cupones, zonas de envío, tarifas, cajas y packs | N3 |
| 3.2 | Motor de precios (`App\Dominio\Precios\Motor`) con mensajes bilingües | N3 |
| 3.3 | Backend: Ajustes → Impuestos, Envío y Cajas; Marketing → Ofertas, Pedido mínimo, Cupones y Simulador; Productos → Packs | N3 |

## Rondas de revisión

- Tests y una pasada de navegador por paso.
- **Gauntlet de cierre en una sola ronda**, a petición del cliente para ahorrar recursos: el agente `instructor`, independiente, centrado en el dinero. Veredicto: «no listo», con 2 defectos críticos y 2 altos.
- Corregidos todos los defectos salvo dos pendientes (D122 y D127). **No hubo segunda ronda**: las correcciones se comprobaron con tests nuevos.

## Defectos encontrados y corregidos

| # | Gravedad | Defecto | Corrección | Test |
|---|---|---|---|---|
| 1 | Crítica | Un porcentaje de más del 100 % (error de tecleo) tumbaba todos los cálculos | El backend lo rechaza; el motor limita el descuento a la base | Sí, motor y backend |
| 2 | Crítica | Un pack con una prenda en la papelera daba error en cada cesta | El pack deja de venderse con aviso | Sí |
| 3 | Alta | Un pack con una prenda retirada se seguía vendiendo | Ídem | Sí |
| 4 | Alta | Cantidades con decimales cobraban una cosa y registraban otra | Solo enteros de 1 a 10 000 por talla | Sí, 4 casos |
| 5 | Media | El porcentaje se redondeaba por línea (2 céntimos de más) | Un redondeo sobre el total y reparto | Sí |
| 6 | Media | El envío tributaba siempre al tipo general | Reparto proporcional por tipo (D122) | Sí |
| 7 | Media | Un pack eximía a las sueltas también del mínimo por talla | El mínimo por talla se mantiene (D127, a confirmar) | Sí |
| 8 | Media | El importe mínimo se escribía en céntimos | Se escribe en euros | Sí |
| 9 | Baja | Ofertas con condiciones mal formadas | No se aplican | Sí |
| 10 | Baja | Tramos de envío repetidos daban un error SQL | Aviso en el campo | Sí |
| 11 | Baja | Impuestos sin recargo agrupados de más | Se agrupan por tipo de IVA | Cubierto |
| 12 | — | Motor: mensajes en inglés con palabras en español; un aviso de más con líneas inválidas; reparto de tallas de packs perdido al guardar | Corregidos durante la construcción | Sí |

## Pruebas superadas

- Suite completa: **336 tests** en SQLite; en MariaDB estricta, 334 superados y 2 saltados (copias SQLite).
- Migraciones en MariaDB estricta: crear, deshacer y rehacer.
- Navegador: las 8 pantallas nuevas del backend cargan sin errores en la consola. Los formularios de oferta combinada y de pack se revisaron con capturas.

## Pruebas no ejecutadas

- Revisión del cliente en Herd del simulador y de los packs.
- Simulador desde el navegador con datos reales: su cálculo está probado con Livewire.
- Validación fiscal por el asesor.

## Riesgos pendientes

| Riesgo | Gravedad | Situación |
|---|---|---|
| Trato fiscal del envío y del recargo | Media | Asesor (D122) |
| Mínimos con packs | Baja | Pregunta al cliente (D127) |
| CP vacío aplica la zona general | Baja | Se exigirá en el pago (fase 6) |
| Uso de cupones por cliente | Baja | La lista de usados la dará el pedido (fase 6) |
| Destinos fuera de la Península y Baleares | Baja | Cuando se decidan (D123) |

## Despliegue

1. Fusionar `fase-3-precios` en `main`. La migración crea las clases de impuesto y conserva el IVA ya configurado.
2. Crear en el backend las reglas reales: mínimos, ofertas, envíos, cajas y packs. Los de `EjemplosPreciosSeeder` son solo para pruebas.
3. Probar cada regla en Marketing → Simulador de precios antes de anunciarla.
