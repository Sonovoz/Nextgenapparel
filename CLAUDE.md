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
3. **La IA nunca calcula precios, mínimos ni impuestos.** Los calcula el motor de precios en PHP, de forma determinista y con tests. La IA solo consulta y explica sus resultados.
4. **Todo lo configurable se configura en el backend:** reglas de mínimos, precios, ofertas, envíos, plazos, estados visibles para el cliente, plantillas de correo, SMTP y proveedor de IA. No se fijan valores de negocio en el código.
5. **Backend con las funciones y la organización de WooCommerce** (Pedidos, Productos variables, Clientes, Ajustes → Impuestos/Envío/Pagos/Correos, Analíticas, roles). El equipo lleva años con WooCommerce y debe reconocer las funciones. Se parece en funciones, no es una copia visual.
6. **Autosuficiencia:** colas con reintentos, tareas programadas, comprobación de salud, alertas por correo, copias de seguridad automáticas y funcionamiento sin dependencias críticas (si la IA cae, el portal sigue vendiendo).
7. **Dinero sin errores de redondeo:** importes en céntimos (enteros); precios guardados sin IVA más su tipo de impuesto, mostrados con IVA.
8. **Alcance por versiones:** construir la versión 1 completa antes de cualquier función de la versión 2. La base de datos se diseña pensando en ambas.
9. **No inventes resultados:** pruebas, métricas o auditorías no ejecutadas se declaran como no ejecutadas.
10. **Secretos fuera del código:** claves de Redsys, SMTP e IA solo en variables de entorno.
11. **Una sola sesión trabaja en una rama a la vez.** Antes de empezar: `git fetch origin '+refs/heads/*:refs/remotes/origin/*'` y partir de la rama más reciente (nunca de una copia local vieja). Al cerrar una fase, actualiza el estado en `docs/FICHA.md` (sección «Estado»).
12. **Reutiliza Clave de Alma** (`Sonovoz/Clavedealma`, misma pila: Laravel 13, Filament 5) antes de escribir desde cero: Redsys y Ajustes → Pagos, facturas y rectificativas con Veri*Factu, PDF, devoluciones, SMTP desde el panel, textos legales, registro de seguridad, 2FA del equipo e instalación en OVH. Se porta y se adapta a un solo vendedor (sin artistas ni comisiones), con sus tests (D171).

## Cómo se entrega y se verifica (D171, sustituye a D74)

Se sigue el método de Clave de Alma: **la CI es la puerta**, no las revisiones a mano.

1. **Cada entrega = una rama y una PR** contra `main`, pequeña y con un resultado visible o comprobable. Entra en `main` solo con la **CI en verde** (`.github/workflows/tests.yml`: Pint, Larastan, tests en SQLite con PHP 8.3 y 8.5, y migraciones y tests en MariaDB estricta como XAMPP).
2. **Tests útiles**: cada regla de negocio y cada fallo real tiene su test; los casos parecidos van juntos (proveedores de datos). No se prueba lo que ya garantiza el framework.
3. **Antes de abrir la PR**: tests afectados + Pint en local. La suite completa y MariaDB las pasa la CI (no se repiten a mano).
4. **Navegador** (una pasada, dominio `.test`) solo cuando la entrega trae una pantalla nueva o cambia un recorrido de compra.
5. **Instructor** (`.claude/agents/instructor.md`): **una revisión por fase**, antes de cerrarla, y solo de lo que toca dinero, facturas, permisos o datos personales. Sin segundas rondas salvo defectos altos. Sin Gauntlet de dos rondas: `docs/GAUNTLET.md` queda como lista de comprobación para la auditoría final (fase 9).
6. **Revisión del cliente en Herd** solo cuando hay algo visible, agrupando entregas, con un máximo de 3 puntos que comprobar.
7. **Documentación corta**: `docs/HISTORIAL.md` lleva **una línea por entrega** (fecha, qué, PR, decisión). `docs/DECISIONES.md` solo para decisiones de negocio o de arquitectura, en 2-3 líneas; las correcciones y sus porqués van en el mensaje del commit o de la PR, no en DECISIONES.
8. **Informe por entrega** (unas 5 líneas): qué incluye, qué no se ha podido probar y cómo verlo.
9. **Comprobaciones al mínimo (orden del cliente, 09/11):** en local solo Pint y, si acaso, el test nuevo; nada de pasadas de navegador ni segundas rondas del instructor salvo que el cliente lo pida. La CI decide.

## Pila técnica

- PHP 8.x, Laravel (última versión estable), Filament (backend), Livewire (frontend).
- MariaDB/MySQL, Redis (colas, caché, sesiones), contenedores de cola (`queue:work`) y de tareas programadas (`schedule:work`).
- Imagick (análisis de PNG y maquetas), generación de PDF, ZIP nativo.
- Local: Herd + SQLite. Servidor: Docker Compose con entornos de **pruebas** y **producción** en el mismo dedicado, detrás de Caddy (`docs/DESPLIEGUE_OVH.md`).
