# Plan visual — NextGen Apparel

> **Actualización (D139, D140):** el cliente decidió un **ambiente de consola al 100 %** (tema oscuro, azul neón, titulares en Jost 800, lenguaje de juego) con una zona de juego en la portada. Lo que sigue sobre crema y serifa de alto contraste queda sustituido; la estructura, los dos caminos y los criterios de rendimiento y accesibilidad se mantienen.

A partir de las maquetas del cliente (8 de noviembre de 2026): la portada «Creamos marcas que comunican», la diapositiva editorial «DTI Textil para marcas que comunican» y el logotipo. Es la guía de diseño para todo el frontend; los cambios se registran en `docs/DECISIONES.md`.

## 1. Idea y objetivo

**«Creamos marcas que comunican».** En el primer segundo, un chico o una chica que quiere su colección debe entender:

- **qué hacemos:** imprimimos su marca en prendas de calidad;
- **cómo lo consigue**, por una de dos vías:
  1. **En 3 pasos:** sube tu logo → elige tus prendas → cierra el pedido.
  2. **Elige un pack** y sube tu diseño.
- **por qué fiarse:** la calidad del tejido, el precio claro, el pago seguro y el plazo visible.

**El objetivo de cada pantalla es avanzar hacia la compra.** Cada pantalla tiene una acción principal, nunca hay un callejón sin salida y hay como mucho tres toques hasta el carrito.

## 2. Lo que dicen las maquetas

| Elemento | Qué tomamos |
|---|---|
| **Logotipo** | Monograma «NG» en negro plateado con un toque de **azul eléctrico**. Logotipo en palo seco fino con la «x» azul. Debajo, una **línea con un punto azul**: la usaremos como separador de secciones, el sello visual de la marca |
| **Color** | Tinta casi negra, blanco y **crema cálido** de fondo. **Azul eléctrico** solo para la acción principal y los detalles. Las fotos aportan los tonos cálidos (marrón, terracota, verdes) |
| **Tipografía** | Titulares en una **serifa moderna de alto contraste** («Creamos marcas que comunican»). Textos y botones en un **palo seco limpio**. Rótulos en versalitas con espaciado («IMPRESIÓN DTI TEXTIL») |
| **Fotografía** | Luz natural cálida, prendas dobladas con estampados intensos y sombras de hojas: estilo de vida editorial. El **macro del tejido** demuestra la calidad (el canutillo del algodón con la tinta integrada) |
| **Contenido** | Ventajas con iconos de trazo fino: tacto suave, acabado plano, colores vibrantes, alta durabilidad, sin relieve ni plástico. Usos: creadores, eventos, empresas, hostelería. Categorías: camisetas, sudaderas, tote bags, uniformes, kits y packs |

## 3. Sistema de diseño

- **Tokens** en variables CSS:
  - `--tinta #111114`, `--papel #FFFFFF`, `--crema #F5F0E9`, `--azul #1E3BFF` (acción), `--azul-hover #1630D6`, `--linea #E6E1D9`, y verde y rojo solo para los estados.
  - El contraste del texto cumple AA.
- **Tipografía:**
  - una serifa display (tipo *Fraunces* o *Instrument Serif*) y un palo seco (tipo *Inter* o *Manrope*);
  - **alojadas en el propio servidor** en woff2, con precarga y sin Google Fonts, por la protección de datos;
  - escala fluida con `clamp()`; un titular de portada de 40 px en móvil y 84 px en escritorio.
- **Espaciado y forma:**
  - espaciado en múltiplos de 8;
  - radios de 4 px en tarjetas y fotos y de 999 px en botones de píldora;
  - sombras suaves y cálidas, solo para elevar lo que se arrastra o se abre.
- **Componentes Blade reutilizables:**
  - botón principal (azul) y secundario (borde);
  - tarjetas de prenda y de pack;
  - selector de pasos 1-2-3;
  - chips de talla y color;
  - separador de línea con punto;
  - menú móvil desplegable;
  - **barra de acción fija en móvil**;
  - avisos con el color del nivel (calidad, mínimo, oferta).
- **Iconos:** de trazo fino, en un SVG propio, coherentes con los de la diapositiva.

## 4. Portada (móvil primero)

1. **Cabecera fija y fina**, con fondo translúcido al bajar.
   - En móvil: logotipo, carrito y menú.
   - En escritorio: Prendas, Packs, Cómo funciona, Calidad, el selector ES/EN y el carrito.
2. **Portada principal:**
   - El titular «Creamos marcas que comunican» y la frase «Imprimimos tus diseños en las prendas que elijas».
   - **Dos caminos con el mismo peso:**
     - **«Crear mi colección»** (3 pasos), en azul;
     - **«Ver packs»**, el secundario.
   - Debajo, la **línea de confianza**: *Desde 15 prendas · Calidad DTI · Pago seguro · Envío en 48–72 h*. Los datos salen de la configuración, nunca escritos en el código.
   - La foto de estilo de vida con un acercamiento lento, y en móvil la foto arriba y el texto abajo.
3. **«Tu marca en 3 pasos»**, interactivo.
   - En el paso 1, **una zona para soltar el logo**: lo ve al momento sobre una camiseta (reutiliza «Tu marca, aquí») y el botón le lleva a elegir prendas con el logo ya cargado.
   - Es el enganche emocional en la primera visita.
4. **Packs:** carrusel deslizable en móvil con precio con IVA, unidades y botón «Elegir pack».
5. **Prendas:** las familias con su precio «Desde» (con impresión).
6. **Calidad:**
   - el macro del tejido con un deslizador de lupa;
   - las cuatro ventajas con sus iconos;
   - una frase de garantía.
7. **Para quién:** creadores y marcas, eventos y festivales, empresas y equipos, cada uno con un botón que lleva a una prenda o a un pack.
8. **Preguntas breves** (plazos, mínimos, qué archivo necesito) y una **llamada final** con los dos caminos.

## 5. Recorrido de compra (fases 4 a 7)

- **Selector de pasos siempre visible:** Diseño · Prendas · Pedido, con el paso actual y lo que falta.
- **Precio en directo** (motor de precios) y **avisos que ayudan**:
  - «te faltan 3 para el mínimo»;
  - «te faltan 5 para la oferta»;
  - el aviso de la calidad del archivo.
- **En móvil, barra fija abajo** con el total y la acción siguiente («Elegir prendas · 105,00 €»).
- **Ficha de producto:** el visor actual, más esta barra fija y el botón activo de «Personalizar».
- **Carrito y pago** en una sola página, con la maqueta del diseño en cada línea y el plazo estimado antes de pagar.

## 6. Movimiento y sensación premium

- Aparición suave al bajar y botones que se elevan un poco al pasar el ratón.
- **Transición entre el listado y la ficha:** la foto se transforma de una a otra con la *View Transitions API*.
- Giro y estampado en el visor (hecho).
- Todo respeta «reducir movimiento». No usamos librerías pesadas: CSS y JavaScript propios.

## 7. Rendimiento y accesibilidad (criterios de aceptación)

- **Móvil:**
  - LCP < 2,5 s;
  - CLS < 0,1;
  - Lighthouse ≥ 90 en rendimiento, accesibilidad, buenas prácticas y SEO.
- **Imágenes:** AVIF o WebP con `srcset`, la foto principal con `fetchpriority="high"` y el resto en carga diferida.
- **Accesibilidad:**
  - zonas táctiles de 44 px o más;
  - foco visible;
  - texto alternativo;
  - contraste AA;
  - todo usable con teclado.

## 8. Pasos

| Paso | Contenido | Cuándo |
|---|---|---|
| V1 | Sistema de diseño (tokens, tipografías alojadas, componentes), cabecera y pie, y **portada** completa | Ahora, antes de la fase 4 |
| V2 | Listados y ficha con el nuevo sistema; barra fija en móvil; transición entre listado y ficha | Con la fase 4 |
| V3 | Página de packs y recorrido de compra en 3 pasos | Fase 5 |
| V4 | Carrito, pago y área de cliente | Fases 5 a 7 |

Cada paso es de nivel N2: suite completa, una pasada de navegador en móvil y escritorio y la revisión en Herd, más Lighthouse en móvil desde el V1.

## 9. Lo que necesitamos del cliente

1. ~~**¿«DTI» o «DTF»?**~~ **Resuelto (D146): DTI.** La ficha y la web dicen DTF (impresión por transferencia directa a película). Las maquetas dicen «DTI Textil». Si «DTI» es una marca del proveedor (el sello «INNURO Print Partner Center»), hay que confirmar si se puede usar.
2. **Logotipo en SVG** (monograma y logotipo completo) y el azul exacto de la marca.
3. **Fotos propias o con licencia** para la portada y el macro del tejido. Las de las maquetas sirven de provisionales si son vuestras.
4. **«Proyectos»**, tote bags y uniformes aparecen en las maquetas pero no están en la versión 1. Hasta tener contenido, proponemos dejarlos fuera del menú.
5. **Nombre en pantalla:** «Nextgenapparel» en una palabra, como el logotipo, o «NextGen Apparel» como ahora.
