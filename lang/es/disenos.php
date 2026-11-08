<?php

return [
    'nivel_correcta' => 'Calidad excelente',
    'nivel_buena' => 'Buena calidad',
    'nivel_justa' => 'Calidad justa',
    'nivel_baja' => 'Calidad baja',
    'mensaje_correcta' => 'Se imprimirá nítido a este tamaño.',
    'mensaje_buena' => 'Se imprimirá bien, pero podría ganar nitidez.',
    'mensaje_justa' => 'Ojo, tu diseño no tiene la calidad que buscas: puede verse algo borroso o con escalones.',
    'mensaje_baja' => 'A este tamaño se verá borroso o pixelado. Te recomendamos no continuar sin mejorarlo.',
    'consejo' => 'Hasta :medida de ancho se imprime con calidad excelente.',
    'consejo_mejorar' => 'Redúcelo o sube una versión más grande: para este tamaño necesitas unos :px px de ancho.',
    'ppp' => ':ppp ppp a este tamaño',
    'vectorial' => 'Calidad perfecta a cualquier tamaño.',
    'texto' => 'Texto: se imprime nítido a cualquier tamaño.',
    'fondo' => 'Tu imagen tiene fondo: se imprimiría el rectángulo entero. Para el pedido, súbela en PNG con fondo transparente.',
    'fondo_blanco' => 'Parece que tu imagen tiene fondo blanco: en prendas de color se vería un recuadro. Para el pedido, súbela en PNG con fondo transparente.',
    'formato_pedido' => 'Para el pedido necesitaremos tu diseño en PNG.',
    // Instrucciones para mejorar el diseño con IA (D143); «:medida» es el tamaño de impresión.
    'ia_texto' => 'Voy a adjuntar una imagen que quiero imprimir en una prenda. Mejora su calidad y prepárala para impresión textil siguiendo estas instrucciones:

1. Conserva fielmente el diseño original: su composición, colores, proporciones y detalles. No cambies logotipos, letras, símbolos ni añadas elementos nuevos.
2. Mejora la nitidez, la definición y los bordes. Reduce la pixelación, el ruido y los defectos de compresión, evitando un aspecto artificial o excesivamente enfocado.
3. Prepara el diseño con la mayor resolución real disponible. El objetivo es alcanzar 300 píxeles por pulgada al tamaño final de impresión. Mi tamaño deseado es: :medida.
4. Si la imagen original no tiene calidad suficiente para ese tamaño, indícamelo y dime qué tamaño máximo aproximado permitiría imprimir a 300 ppp. No afirmes que se han recuperado detalles que no existen en el original.
5. Entrégame el diseño en formato PNG, con fondo transparente si el diseño lo permite. No incluyas la camiseta, una maqueta, sombras, marcos ni fondos que no formen parte del diseño.
6. Comprueba que las letras sean legibles y que no falten detalles. Si algún elemento no puede reproducirse fielmente, avísame.
7. Indica las dimensiones finales del archivo en píxeles y el tamaño aproximado al que puede imprimirse a 300 ppp.',
    // Errores al subir el diseño (fase 4).
    'error_ilegible' => 'No hemos podido leer el archivo. Comprueba que es un PNG válido y vuelve a subirlo.',
    'error_no_png' => 'Para el pedido necesitamos tu diseño en PNG. Expórtalo como PNG, a ser posible con fondo transparente.',
    'error_pesado' => 'El archivo pesa más de :mb MB. Expórtalo de nuevo en PNG sin capas ni metadatos.',
    'error_enorme' => 'La imagen mide más de :px píxeles por lado. Redúcela: para imprimir a 300 ppp basta con mucho menos.',
    'error_pequeno' => 'Tu diseño mide :ancho × :alto píxeles sin contar los márgenes transparentes. Necesitamos al menos :px píxeles por lado.',
    'error_procesar' => 'La imagen es demasiado grande para procesarla. Redúcela o expórtala de nuevo en PNG de 8 bits por canal.',
    'error_vacio' => 'La imagen es totalmente transparente: no hay nada que imprimir.',
];
