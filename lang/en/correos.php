<?php

// Order emails by status (step 6.4): default texts, editable in Ajustes → Correos.
return [
    'pagado_asunto' => 'We have received your order :numero',
    'pagado_texto' => "Thank you, :nombre! Your order :numero for :total is paid and in our hands.\n\nWe will now review your design before printing it. We will keep you posted at every step.\n\nEstimated delivery: :fecha_entrega.",
    'diseno_en_revision_asunto' => 'We are reviewing your design · order :numero',
    'diseno_en_revision_texto' => "Hi :nombre. Our team is reviewing the design of your order :numero so it prints perfectly.\n\nIf we need anything, we will write to you.",
    'requiere_cambios_asunto' => 'We need your help with order :numero',
    'requiere_cambios_texto' => "Hi :nombre. To print your order :numero we need you to review your design.\n\nOpen your order to see what we need.",
    'en_produccion_asunto' => 'Your order :numero is being printed',
    'en_produccion_texto' => "Hi :nombre. Your order :numero has gone into production.\n\nEstimated delivery: :fecha_entrega.",
    'enviado_asunto' => 'Your order :numero is on its way',
    'enviado_texto' => "Hi :nombre. Your order :numero is out for delivery with :transportista.\n\nTracking number: :seguimiento.",
    'entregado_asunto' => 'Your order :numero has been delivered',
    'entregado_texto' => "Hi :nombre. Your order :numero shows as delivered. We hope you love it!\n\nIf anything is wrong, write to us from your order.",
    'cancelado_asunto' => 'Your order :numero has been cancelled',
    'cancelado_texto' => "Hi :nombre. Your order :numero has been cancelled.\n\nIf you did not expect this, write to us and we will look into it.",
    'nota_asunto' => 'Update on your order :numero',
    'nota_texto' => 'Hi :nombre. We have an update on your order :numero:',
    'enlace_asunto' => 'Your link to order :numero',
    'enlace_texto' => 'Hi :nombre. Here is the link to view your order :numero.',
    'ver_pedido' => 'View my order',
    'sin_fecha' => 'we will confirm it soon',
    'prueba_asunto' => 'Test email from :tienda',
    'prueba_texto' => 'If you can read this, the shop email goes out fine over SMTP.',
];
