<?php

// D249: guía «Prepara tu imagen para imprimir», para quien no sabe de diseño (ni qué es un PNG).
return [
    'ruta' => 'prepara-tu-imagen',
    'titulo_pagina' => 'Prepara tu imagen para imprimir · Guía fácil · Nextgenapparel',
    'titulo_corto' => 'Prepara tu imagen',
    'descripcion' => 'Lo que en el móvil se ve bien puede salir borroso en una camiseta. Te explicamos, sin tecnicismos, cómo preparar tu imagen para imprimir: tamaño en cm, PNG, fondo transparente, herramientas gratis y prompts para la IA.',

    'etiqueta' => 'Guía fácil para imprimir bien',
    'titular' => 'Lo que en el móvil se ve bien, puede no servir para imprimir',
    'entradilla' => 'La pantalla del móvil es pequeña y disimula mucho. Una camiseta no: tu diseño se verá a 25 cm, a la luz y de cerca. Aquí tienes, paso a paso y sin tecnicismos, cómo conseguir que salga nítido.',
    'ir_consejos' => 'Ver cómo mejorarla',
    'ir_comprobar' => 'Comprobar mi imagen',
    'demo_movil' => 'En el móvil',
    'demo_movil_texto' => 'Pequeña, se ve perfecta.',
    'demo_camiseta' => 'La misma imagen, en la camiseta a 25 cm',
    'demo_camiseta_texto' => 'Grande, se ven los cuadraditos.',

    'porque_etiqueta' => '¿Por qué pasa?',
    'porque_titulo' => 'Una imagen está hecha de puntitos',
    'porque_texto' => 'Cualquier foto o dibujo del ordenador está formado por puntitos de color, llamados píxeles. En la pantalla del móvil son tan pequeños que no los ves. Al imprimir, cada puntito ocupa un trozo de tela: si la imagen tiene pocos puntitos y la imprimes grande, se notan y sale borrosa o con escalones.',
    'porque_regla' => 'La regla sencilla: para que salga nítido hacen falta unos :por_cm puntitos por cada centímetro de camiseta (:ppp ppp).',
    'tabla_titulo' => 'Cuántos puntitos necesita tu imagen',
    'tabla_tamano' => 'Si la quieres imprimir a…',
    'tabla_puntos' => 'tu imagen necesita de ancho al menos…',
    'tabla_ejemplo' => ':nombre',
    'tabla_ejemplos' => ['Un logo pequeño en el pecho', 'Un logo mediano', 'Un dibujo en la frontal', 'Un dibujo grande en la espalda'],
    'tabla_nota' => 'No te preocupes por hacer cuentas: al subir tu imagen en nuestro configurador te decimos al momento, en cm, hasta qué tamaño sale nítida.',

    'png_etiqueta' => '¿Qué es un PNG?',
    'png_titulo' => 'PNG: el tipo de archivo que te pedimos',
    'png_texto' => 'Igual que un documento puede ser Word o PDF, una imagen puede ser JPG, PNG y otros. Lo sabes por el final de su nombre: «logo.png» o «foto.jpg». Te pedimos PNG porque puede tener el fondo transparente: lo que rodea a tu dibujo no se imprime.',
    'png_jpg' => 'Con JPG',
    'png_jpg_texto' => 'Siempre lleva fondo, normalmente blanco: en una camiseta de color se imprime un recuadro blanco alrededor de tu dibujo.',
    'png_png' => 'Con PNG transparente',
    'png_png_texto' => 'Solo se imprime tu dibujo, directamente sobre la tela, del color que sea.',
    'png_ojo' => 'Ojo: cambiar el nombre de «foto.jpg» a «foto.png» no lo convierte. Hay que abrirla en un programa y guardarla («exportarla») como PNG, por ejemplo en Photopea: Archivo → Exportar como → PNG.',

    'medir_etiqueta' => 'Compruébalo tú',
    'medir_titulo' => 'Cómo saber cuántos puntitos tiene tu imagen',
    'medir' => [
        ['Windows', 'Clic derecho sobre el archivo → Propiedades → pestaña Detalles → «Dimensiones» (por ejemplo, 1200 × 800).'],
        ['Mac', 'Ábrela con Vista Previa → menú Herramientas → Mostrar inspector.'],
        ['iPhone', 'En la app Fotos, abre la imagen y desliza hacia arriba (o toca la «i»).'],
        ['Android', 'En Google Fotos, abre la imagen → los tres puntos → Detalles.'],
    ],
    'medir_facil' => 'O lo más fácil: súbela en nuestro configurador y te decimos al momento hasta cuántos cm sale nítida.',

    'errores_etiqueta' => 'Lo que más vemos',
    'errores_titulo' => 'Cinco errores frecuentes',
    'errores' => [
        ['Capturas de pantalla', 'Una captura de una web o de una red social tiene muy pocos puntitos. Busca el archivo original.'],
        ['Imágenes de WhatsApp o de Google', 'Al enviarlas o publicarlas se comprimen y pierden calidad. Pide que te la manden como documento o busca la original.'],
        ['Agrandarla en el programa', 'Estirar una imagen pequeña no le añade detalle: sale igual de borrosa, pero más grande. Para ampliarla bien hace falta la IA (más abajo te contamos cómo).'],
        ['Fondo blanco', 'Si tu imagen tiene un fondo blanco, se imprime un recuadro blanco. Quítalo y guárdala como PNG.'],
        ['Detalles demasiado finos', 'Las líneas de menos de 1 mm de grosor y las letras de menos de 5 mm de alto se pierden en la tela. Engórdalas o hazlas más grandes.'],
    ],

    'pasos_etiqueta' => 'Paso a paso',
    'pasos_titulo' => 'Cómo mejorar tu imagen',
    'pasos' => [
        ['Busca el original', 'Si es un logo, pídeselo a quien lo diseñó en su formato de origen (AI, SVG, PDF o EPS). Con ese archivo se puede sacar un PNG del tamaño que quieras, siempre nítido.'],
        ['Si solo tienes una imagen pequeña, amplíala con IA', 'Programas como Upscayl (gratis) o tu asistente de IA pueden ampliarla rellenando el detalle. Revisa siempre que el diseño no haya cambiado.'],
        ['Quita el fondo', 'Con Adobe Express o Photopea, en un par de clics. Comprueba que los bordes del dibujo quedan limpios.'],
        ['Guárdala en PNG, al tamaño de impresión', 'Prepárala a los centímetros a los que la vas a imprimir y a :ppp ppp, y expórtala como PNG con fondo transparente.'],
    ],

    'herramientas_etiqueta' => 'Recursos gratis',
    'herramientas_titulo' => 'Herramientas gratis que te pueden ayudar',
    'herramientas' => [
        ['Photopea', 'https://www.photopea.com', 'Como Photoshop, pero en el navegador y gratis. Para guardar en PNG, cambiar el tamaño y quitar fondos.'],
        ['Canva', 'https://www.canva.com', 'Para crear un diseño desde cero, eligiendo el tamaño en cm. Descarga en PNG (el fondo transparente es de su versión de pago).'],
        ['Adobe Express', 'https://www.adobe.com/express/feature/image/remove-background', 'Quita el fondo de una imagen en segundos, gratis.'],
        ['Upscayl', 'https://upscayl.org', 'Programa gratuito para ampliar imágenes con IA en tu ordenador (Windows, Mac y Linux).'],
        ['GIMP', 'https://www.gimp.org', 'Editor de imágenes completo y gratuito para el ordenador.'],
        ['Inkscape', 'https://inkscape.org', 'Para logos y dibujos en vectores: exporta PNG a cualquier tamaño sin perder calidad.'],
    ],
    'herramientas_nota' => 'No tenemos relación con estas herramientas y sus condiciones pueden cambiar.',
    'abrir' => 'Abrir :nombre',

    'ia_etiqueta' => 'Con tu asistente de IA',
    'ia_titulo' => 'Prompts para pedírselo a la IA',
    'ia_texto' => 'Si usas ChatGPT, Gemini, Copilot u otra IA que trabaje con imágenes, adjunta tu imagen y copia uno de estos textos. Dinos a cuántos cm la quieres imprimir y los completamos por ti.',
    'ia_cm' => 'Ancho de impresión',
    'ia_copiar' => 'Copiar',
    'ia_copiado' => 'Copiado',
    'ia_prompts' => [
        ['Mejorar y ampliar', 'Te adjunto una imagen. Necesito imprimirla en una camiseta a :cm cm de ancho. Hazla de nuevo a :px píxeles de ancho (:ppp ppp a ese tamaño), con los bordes nítidos, sin cambiar el diseño ni los colores, con el fondo transparente y en formato PNG.'],
        ['Quitar el fondo', 'Quita el fondo de esta imagen y déjalo transparente, sin tocar el dibujo ni sus bordes. Entrégamela en formato PNG, del mismo tamaño o más grande.'],
        ['Redibujar un logo', 'Redibuja este logo con líneas limpias y colores planos, idéntico al original, sin añadir ni quitar nada. Hazlo a :px píxeles de ancho, con el fondo transparente, en PNG (y si puedes, también en SVG).'],
        ['Crear un diseño desde cero', 'Crea una ilustración para imprimir en una camiseta a :cm cm de ancho: [describe aquí tu idea]. Colores planos y con contraste, sin degradados muy suaves ni letras pequeñas, fondo transparente, :px píxeles de ancho y en formato PNG.'],
    ],
    'ia_nota' => 'Algunas IA entregan imágenes más pequeñas de lo que pides o sin fondo transparente: si es así, amplíala con Upscayl y quita el fondo con Adobe Express. Revisa siempre el resultado y usa solo imágenes que tengas derecho a usar.',

    'lista_etiqueta' => 'Antes de subirla',
    'lista_titulo' => 'Tu lista rápida',
    'lista' => ['Es un PNG.', 'El fondo es transparente.', 'Sale nítida al tamaño que quieres (te lo dice nuestro configurador).', 'Sin líneas de menos de 1 mm ni letras de menos de 5 mm de alto.', 'Es tuya o tienes permiso para usarla.'],
    'final_titulo' => 'Súbela y te decimos al momento si está lista',
    'final_texto' => 'Nuestro configurador mide tu imagen y te dice, en cm, hasta qué tamaño sale nítida y en qué zona queda mejor.',
    'final_boton' => 'Crear mi colección',

    'enlace_guia' => '¿Cómo mejorar tu imagen? Guía fácil',
];
