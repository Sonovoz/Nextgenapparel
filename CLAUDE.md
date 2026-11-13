# NextGen Apparel — instrucciones del proyecto

Portal de pedidos de prendas personalizadas con impresión DTI (camisetas, polos y sudaderas) para pequeños diseñadores. Laravel + Filament, en Docker sobre un servidor dedicado de OVH.

**Lee siempre antes de trabajar:**
- `docs/FICHA.md`: alcance, modelo de datos, reglas de negocio y fases. Es la fuente de verdad.
- `docs/DECISIONES.md`: decisiones ya tomadas y su motivo (lee las últimas; las antiguas, cuando toques su parte). No las reabras sin que lo pida el usuario.
- `docs/HISTORIAL.md`: una línea por entrega, de la más reciente a la más antigua. Dice dónde está el proyecto.
- `docs/GAUNTLET.md`: solo como lista de comprobación de la auditoría final (D171).

## Reglas permanentes

1. **Idioma:** responde y documenta en castellano de España. La interfaz del portal es bilingüe (es/en).
2. **Lógica antes que interfaz.** Ninguna pantalla se construye sobre lógica sin tests. El modelo de datos y el motor de precios se validan primero.
3. **La IA nunca calcula precios, mínimos ni impuestos.** Los calcula el motor de precios en PHP, de forma determinista y con tests. La IA solo consulta y explica sus resultados; en el backend puede **proponer** un porcentaje de descuento o copiar cifras de una tarifa, que el motor convierte en importes y el equipo aprueba antes de guardar (D228, D233).
4. **Todo lo configurable se configura en el backend:** reglas de mínimos, precios, ofertas, envíos, plazos, estados visibles para el cliente, plantillas de correo, SMTP y proveedor de IA. No se fijan valores de negocio en el código.
5. **Backend con las funciones y la organización de WooCommerce** (Pedidos, Productos variables, Clientes, Ajustes → Impuestos/Envío/Pagos/Correos, Analíticas, roles). El equipo lleva años con WooCommerce y debe reconocer las funciones. Se parece en funciones, no es una copia visual.
6. **Autosuficiencia:** colas con reintentos, tareas programadas, comprobación de salud, alertas por correo, copias de seguridad automáticas y funcionamiento sin dependencias críticas (si la IA cae, el portal sigue vendiendo).
7. **Dinero sin errores de redondeo:** importes en céntimos (enteros); precios guardados sin IVA más su tipo de impuesto, mostrados con IVA.
8. **Alcance por versiones:** construir la versión 1 completa antes de cualquier función de la versión 2. La base de datos se diseña pensando en ambas.
9. **No inventes resultados:** pruebas, métricas o auditorías no ejecutadas se declaran como no ejecutadas.
10. **Secretos fuera del código:** claves de Redsys solo en variables de entorno. La contraseña del SMTP y la clave de IA se pueden guardar en el panel, siempre cifradas con la APP_KEY y sin volver a mostrarse (D227); si están en el `.env`, manda el `.env`.
11. **Una sola sesión trabaja en una rama a la vez.** Antes de empezar: `git fetch origin '+refs/heads/*:refs/remotes/origin/*'` y partir de la rama más reciente (nunca de una copia local vieja). Al cerrar una fase, actualiza el estado en `docs/FICHA.md` (sección «Estado»).
12. **Reutiliza Clave de Alma** (`Sonovoz/Clavedealma`, misma pila: Laravel 13, Filament 5) antes de escribir desde cero: Redsys y Ajustes → Pagos, facturas y rectificativas con Veri*Factu, PDF, devoluciones, SMTP desde el panel, textos legales, registro de seguridad, 2FA del equipo e instalación en OVH. Se porta y se adapta a un solo vendedor (sin artistas ni comisiones), con sus tests (D171).

## Cómo se entrega (método de Clave de Alma, D180)

**La CI en verde es la única puerta.** Sin revisiones a mano.

1. **Cada entrega = una rama y una PR** contra `main`, pequeña. Entra con la CI en verde (`.github/workflows/tests.yml`: Pint, Larastan, tests en SQLite y en MariaDB estricta).
2. **Tests**: uno por regla de negocio o fallo real. En local, solo Pint y el test nuevo; el resto lo pasa la CI.
3. **Sin pasadas de navegador, sin instructor y sin segundas revisiones**, salvo que el cliente lo pida. `docs/GAUNTLET.md` solo para la auditoría final (fase 9).
4. **Documentación**: una línea por entrega en `docs/HISTORIAL.md`; `docs/DECISIONES.md` solo para decisiones de negocio, en 2-3 líneas. Las correcciones van en el commit.
5. **Informe al cliente**: 3-5 líneas (qué incluye, qué no se ha probado, la PR).

## Pila técnica

- PHP 8.x, Laravel (última versión estable), Filament (backend), Livewire (frontend).
- MariaDB/MySQL, Redis (colas, caché, sesiones); tareas programadas con `schedule:run` (cron del servidor), que también procesa la cola cada minuto.
- Imagick (análisis de PNG y maquetas), generación de PDF, ZIP nativo.
- Local: Herd + SQLite.
- **Servidor de OVH (regla para todos los proyectos de OVH, D207):** el servidor tiene contenedores compartidos: **`php-web` con PHP 8.3**, **`mariadb` 10.11** y **`redis`**, detrás de **Nginx Proxy Manager** (`npm`), más phpMyAdmin, `db-backup` y filebrowser. El proyecto no lleva contenedores propios: corre en `php-web`, usa una base y un usuario propios en `mariadb` (los facilita el cliente y van **solo en el `.env`**, nunca en el código ni en el chat), Redis con prefijo y base propios por entorno, y las tareas y colas con un cron del servidor (`schedule:run`). Pruebas y producción, cada uno con su base y su `.env`. Pasos en `docs/DESPLIEGUE_OVH.md`; *Herramientas → Servidor* comprueba PHP, extensiones, base de datos y Redis.
