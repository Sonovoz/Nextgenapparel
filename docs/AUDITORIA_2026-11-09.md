# Auditoría del proyecto · 9 de noviembre de 2026

Comparación con **Clave de Alma** (`Sonovoz/Clavedealma`), del mismo equipo y con la misma pila, que se construyó en 3 días (3–6 de noviembre) con menos vueltas. Decisión resultante: **D171**.

## 1. Datos

| | Nextgenapparel | Clave de Alma |
| --- | --- | --- |
| Días | 3 (6–8 nov.), fases 1–5 y paso 6.1 | 3 (3–6 nov.), tienda completa en OVH |
| Código de la aplicación | 15 600 líneas | 23 700 líneas |
| Tests | 476 funciones (656 casos) | 257 |
| PR fusionadas | 6 (ramas de fase enteras) | 37 (una por entrega) |
| Documentación de proceso | 160 KB (DECISIONES 76 KB con 170 entradas, FICHA, GAUNTLET, 5 informes) | 64 KB de ADR (29) + historial de una línea por PR |
| Rondas de «no listo» con correcciones | 9 (registradas como decisiones) | ninguna (la CI en verde es la puerta) |
| Verificación | instructor por paso N3, MariaDB y navegador en local, Gauntlet de 2 rondas por fase | CI en cada PR: Pint, Larastan, tests |

## 2. Qué frena a Nextgenapparel

1. **Verificación a mano y repetida.** Cada paso N3 suma revisión adversarial, suite completa en SQLite y en MariaDB, y pasada de navegador; si sale «no listo», todo otra vez. Es trabajo que la CI hace sola en cada PR.
2. **La CI existente estaba incompleta**: sin Larastan, sin MariaDB y **sin Imagick**, así que desde la fase 4 los tests de diseños no podían pasar en GitHub. Las comprobaciones se hacían en local y no dejaban rastro en la PR.
3. **Ramas de fase enteras** (cientos de cambios por PR) que se fusionan tarde: la fase 5 sigue sin fusionar y la 6 se apoya en ella.
4. **Varias sesiones sin sincronizar**: una sesión trabajó con una copia vieja y estuvo a punto de repetir la fase 4 ya cerrada en otra.
5. **Documentación que crece con cada corrección**: DECISIONES mezcla decisiones con registros de defectos (D148, D151, D152, D155, D157, D159, D162, D167, D170); entradas de 10–15 líneas que hay que leer al empezar cada sesión.
6. **Se construye lo que ya existe**: lo que falta (pedidos en el backend, correos, facturas con Veri*Factu, PDF, Redsys, textos legales, OVH) ya está hecho y probado en Clave de Alma con la misma versión de Laravel y Filament.

## 3. Lo que sí funciona y se mantiene

- Las reglas de negocio de la FICHA, el motor de precios en céntimos con tests y la lógica antes que la interfaz.
- Tests por regla y por fallo real.
- Una revisión adversarial antes de cerrar algo que toca dinero o datos personales (ahora una por fase).

## 4. Nuevas directrices (D171, ya en `CLAUDE.md`)

1. **CI como puerta**: cada entrega es una rama y una PR pequeña que entra en `main` con la CI en verde (Pint, Larastan, SQLite con PHP 8.3 y 8.5, migraciones y tests en MariaDB estricta como XAMPP).
2. **En local, solo los tests afectados y Pint**; navegador solo con pantalla nueva o cambio en el recorrido de compra.
3. **Instructor: una revisión por fase**, solo de dinero, facturas, permisos y datos personales. Sin Gauntlet de dos rondas: `GAUNTLET.md` queda para la auditoría final.
4. **Documentación corta**: `docs/HISTORIAL.md` (una línea por entrega) y DECISIONES solo para decisiones, en 2–3 líneas; las correcciones, en el commit.
5. **Sesiones**: antes de trabajar, `git fetch` de todas las ramas y partir de la más reciente; una sola sesión por rama.
6. **Reutilizar Clave de Alma** antes de escribir desde cero (plan en `docs/FICHA.md` § 15 bis).

## 5. Pendiente para que la CI sea la puerta de verdad

- Ejecutar la CI nueva en GitHub y generar la línea base de Larastan (`phpstan-baseline.neon`); después, quitar `continue-on-error` del paso de Larastan. **No se ha podido ejecutar aquí**: Composer no descarga desde GitHub en este entorno.
- Fusionar la PR de la fase 5 y abrir la de la fase 6 para dejar de acumular cambios fuera de `main`.
