<?php

return [

    /*
    | Palabras que no pueden ser la URL de una familia: las usarán rutas fijas
    | de la tienda (carrito, cuenta, packs…) que comparten el prefijo /{idioma}/.
    */
    'urls_reservadas' => [
        'carrito', 'cart', 'cuenta', 'account', 'mi-cuenta', 'my-account', 'pedido', 'pedidos', 'order', 'orders',
        'checkout', 'pago', 'payment', 'packs', 'pack', 'personalizar', 'customise', 'customize', 'configurador',
        'buscar', 'search', 'legal', 'privacidad', 'privacy', 'cookies', 'aviso-legal', 'terminos', 'terms',
        'contacto', 'contact', 'ayuda', 'help', 'blog', 'login', 'registro', 'register', 'acceso', 'presupuesto', 'quote',
    ],

];
