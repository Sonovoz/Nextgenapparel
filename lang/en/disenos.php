<?php

return [
    'nivel_correcta' => 'Excellent quality',
    'nivel_buena' => 'Good quality',
    'nivel_justa' => 'Fair quality',
    'nivel_baja' => 'Low quality',
    'mensaje_correcta' => 'It will print sharp at this size.',
    'mensaje_buena' => 'It will print well, but it could be sharper.',
    'mensaje_justa' => "Careful, your design isn't at the quality you're after: it may look slightly blurry or jagged.",
    'mensaje_baja' => "At this size it will look blurry or pixelated. We recommend you don't continue without improving it.",
    'consejo' => 'Up to :medida wide it prints at excellent quality.',
    'consejo_mejorar' => 'Make it smaller or upload a larger version: for this size you need about :px px wide.',
    'ppp' => ':ppp dpi at this size',
    'vectorial' => 'Perfect quality at any size.',
    'texto' => 'Text: prints sharp at any size.',
    'fondo' => 'Your image has a background: the whole rectangle would be printed. For the order, upload it as a PNG with a transparent background.',
    'fondo_blanco' => 'Your image seems to have a white background: on coloured garments you would see a box. For the order, upload it as a PNG with a transparent background.',
    'formato_pedido' => "For the order we'll need your design as a PNG.",
    // Instrucciones para mejorar el diseño con IA (D143); «:medida» es el tamaño de impresión.
    'ia_texto' => 'I am going to attach an image that I want to print on a garment. Improve its quality and prepare it for textile printing following these instructions:

1. Faithfully preserve the original design: its composition, colours, proportions and details. Do not change logos, lettering or symbols, and do not add new elements.
2. Improve sharpness, definition and edges. Reduce pixelation, noise and compression artefacts, avoiding an artificial or over-sharpened look.
3. Prepare the design at the highest real resolution available. The goal is 300 pixels per inch at the final print size. My desired size is: :medida.
4. If the original image does not have enough quality for that size, tell me so and tell me the approximate maximum size that would allow printing at 300 ppi. Do not claim to have recovered details that do not exist in the original.
5. Give me the design as a PNG file, with a transparent background if the design allows it. Do not include the T-shirt, a mock-up, shadows, frames or backgrounds that are not part of the design.
6. Check that the lettering is legible and that no details are missing. If any element cannot be reproduced faithfully, let me know.
7. State the final file dimensions in pixels and the approximate size at which it can be printed at 300 ppi.',
    // Upload errors (phase 4).
    'error_ilegible' => 'We could not read the file. Check it is a valid PNG and upload it again.',
    'error_no_png' => 'We need your design as a PNG for the order. Export it as PNG, ideally with a transparent background.',
    'error_sin_ppp' => 'Your PNG does not state its resolution (DPI). Export it again with the resolution set, ideally 300 DPI at print size, and upload it again.',
    'error_pesado' => 'The file is larger than :mb MB. Export it again as a PNG without layers or metadata.',
    'error_enorme' => 'The image is more than :px pixels on one side. Make it smaller: printing at 300 dpi needs far less.',
    'error_pequeno' => 'Your design is :ancho × :alto pixels without its transparent margins. We need at least :px pixels per side.',
    'error_procesar' => 'The image is too large to process. Make it smaller or export it again as an 8-bit-per-channel PNG.',
    'error_vacio' => 'The image is fully transparent: there is nothing to print.',
    'error_sin_archivo' => 'Choose the PNG of your design.',
    'error_subida' => 'We did not receive the whole file. Try again; the file must be smaller than :mb MB.',
    'aviso_perfil' => 'Your file uses the «:perfil» colour profile. We print in sRGB, so some colours may shift slightly. If you can, export it in sRGB.',
    'comprobando' => 'Checking your design…',
    'error_original' => 'We need the original file of «:nombre» for the order. Choose it again in «My logo».',
    'error_red' => 'We could not check your design right now. Wait a moment and try again.',
    'error_servidor' => 'Something went wrong on our server while saving your design; it is not your file. Try again and, if it happens again, contact us.',
    'error_muchas' => 'You have uploaded many designs in a short time. Try again in a few minutes.',
    'error_conexion' => 'There is no connection to the shop. Check your internet connection and try again.',
    'error_no_disponible' => 'We cannot receive designs right now. Try again later; if it keeps happening, write to us.',
    'error_ocupado' => 'We are checking many designs at once. Wait a few seconds and try again.',
    'error_limite_dia' => 'You have uploaded many designs today. If you need more, write to us and we will help.',
    'biblioteca_titulo' => 'Your uploaded designs',
    'biblioteca_quitar' => 'Remove «:nombre» from your designs',
    'biblioteca_confirmar' => 'Remove «:nombre»? We will delete it from our server.',
    'biblioteca_usar' => 'Use «:nombre» on this garment',
    'error_caducada' => 'The page has expired after being idle. Reload it and try again.',
    'espera_subida' => 'Wait until we finish checking your design before changing it.',
    'error_cambiado' => 'You changed the design while we were checking it. Press «Next» again.',
];
