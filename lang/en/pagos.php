<?php

// Paying the order (phase 7, D181).
return [
    'pagar_titulo' => 'Pay for your order: :total',
    'metodo' => 'Payment method',
    'metodo_tarjeta' => 'Credit or debit card',
    'metodo_bizum' => 'Bizum',
    'pagar' => 'Pay now',
    'pagar_nota' => 'You pay on your bank\'s secure page (Redsys): your payment details never go through our website.',
    'modo_pruebas' => 'test mode: nothing is charged',
    'redirigiendo' => 'Taking you to the secure payment page…',
    'continuar' => 'Continue',
    'cobrado' => 'Thank you! We have received the payment for your order.',
    'comprobando' => 'We are checking the payment with the bank. You will see it here in a few minutes and receive an email.',
    'no_disponible' => 'We cannot take payments right now. Please try again in a few minutes.',
    'error_caducada' => 'The payment did not go through: the card has expired.',
    'error_sin_saldo' => 'The payment did not go through: insufficient funds.',
    'error_cvv' => 'The payment did not go through: the security code (CVV) is incorrect.',
    'error_no_admite' => 'The payment did not go through: this card does not allow this payment.',
    'error_titular' => 'The payment did not go through: we could not verify you are the cardholder.',
    'error_rechazado' => 'The bank declined the payment. Try again or use another method.',
    'error_cancelado' => 'You cancelled the payment. You can pay whenever you like from here.',
];
