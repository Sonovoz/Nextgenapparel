<?php

// Pagar el pedido (fase 7, D181).
return [
    'pagar_titulo' => 'Paga tu pedido: :total',
    'metodo' => 'Método de pago',
    'metodo_tarjeta' => 'Tarjeta de crédito o débito',
    'metodo_bizum' => 'Bizum',
    'pagar' => 'Pagar ahora',
    'pagar_nota' => 'Pagas en la página segura de tu banco (Redsys): tus datos de pago no pasan por nuestra web.',
    'modo_pruebas' => 'modo de pruebas: no se cobra nada',
    'redirigiendo' => 'Te llevamos a la página de pago segura…',
    'continuar' => 'Continuar',
    'cobrado' => '¡Gracias! Hemos recibido el pago de tu pedido.',
    'comprobando' => 'Estamos comprobando el pago con el banco. En unos minutos lo verás aquí y te llegará un correo.',
    'no_disponible' => 'Ahora mismo no podemos cobrar. Vuelve a intentarlo en unos minutos.',
    'error_caducada' => 'El pago no se ha hecho: la tarjeta está caducada.',
    'error_sin_saldo' => 'El pago no se ha hecho: la tarjeta no tiene saldo suficiente.',
    'error_cvv' => 'El pago no se ha hecho: el código de seguridad (CVV) no es correcto.',
    'error_no_admite' => 'El pago no se ha hecho: esta tarjeta no admite este pago.',
    'error_titular' => 'El pago no se ha hecho: no se ha podido verificar que eres el titular.',
    'error_rechazado' => 'El banco ha rechazado el pago. Prueba otra vez o con otro método.',
    'error_cancelado' => 'Has cancelado el pago. Puedes pagar cuando quieras desde aquí.',
];
