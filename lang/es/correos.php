<?php

// Correos del pedido por estado (paso 6.4): textos por defecto, editables en Ajustes → Correos.
// Huecos: :nombre, :numero, :estado, :total, :fecha_entrega, :transportista, :seguimiento.
return [
    'pagado_asunto' => 'Hemos recibido tu pedido :numero',
    'pagado_texto' => "¡Gracias, :nombre! Tu pedido :numero por :total está pagado y ya está en nuestras manos.\n\nAhora revisaremos tu diseño antes de imprimirlo. Te avisaremos en cada paso.\n\nEntrega estimada: :fecha_entrega.",
    'diseno_en_revision_asunto' => 'Estamos revisando tu diseño · pedido :numero',
    'diseno_en_revision_texto' => "Hola, :nombre. Nuestro equipo está revisando el diseño de tu pedido :numero para que se imprima perfecto.\n\nSi necesitamos algo, te escribiremos.",
    'requiere_cambios_asunto' => 'Necesitamos tu ayuda con el pedido :numero',
    'requiere_cambios_texto' => "Hola, :nombre. Para imprimir tu pedido :numero necesitamos que revises tu diseño.\n\nEntra en tu pedido y verás lo que te pedimos.",
    'en_produccion_asunto' => 'Tu pedido :numero ya se está imprimiendo',
    'en_produccion_texto' => "Hola, :nombre. Tu pedido :numero ha entrado en producción.\n\nEntrega estimada: :fecha_entrega.",
    'enviado_asunto' => 'Tu pedido :numero va de camino',
    'enviado_texto' => "Hola, :nombre. Tu pedido :numero ya está en reparto con :transportista.\n\nNúmero de seguimiento: :seguimiento.",
    'entregado_asunto' => 'Tu pedido :numero está entregado',
    'entregado_texto' => "Hola, :nombre. Tu pedido :numero consta como entregado. ¡Esperamos que te encante!\n\nSi algo no está bien, escríbenos desde tu pedido.",
    'cancelado_asunto' => 'Tu pedido :numero está cancelado',
    'cancelado_texto' => "Hola, :nombre. Tu pedido :numero se ha cancelado.\n\nSi no lo esperabas, escríbenos y lo vemos.",
    'nota_asunto' => 'Novedades de tu pedido :numero',
    'nota_texto' => 'Hola, :nombre. Tenemos una novedad sobre tu pedido :numero:',
    'enlace_asunto' => 'Tu enlace al pedido :numero',
    'enlace_texto' => 'Hola, :nombre. Aquí tienes el enlace para ver tu pedido :numero.',
    'ver_pedido' => 'Ver mi pedido',
    'sin_fecha' => 'te la confirmaremos pronto',
    'prueba_asunto' => 'Correo de prueba de :tienda',
    'prueba_texto' => 'Si lees esto, el correo de la tienda sale bien por SMTP.',
];
