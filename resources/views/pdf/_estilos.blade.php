{{-- Diseño común de los PDF (D205, D215): premium y sobrio. Jost (la letra de la web, TTF estáticos en
     resources/fonts/pdf, licencia OFL), cabecera oscura con el logo en blanco, mucho aire y el azul de la
     marca solo en detalles. dompdf: CSS sencillo, tablas para maquetar y solo pesos normal/negrita por familia. --}}
    @font-face { font-family: 'Jost'; font-weight: normal; font-style: normal; src: url('{{ resource_path('fonts/pdf/Jost-Regular.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Jost'; font-weight: bold; font-style: normal; src: url('{{ resource_path('fonts/pdf/Jost-SemiBold.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Jost Light'; font-weight: normal; font-style: normal; src: url('{{ resource_path('fonts/pdf/Jost-Light.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Jost Medium'; font-weight: normal; font-style: normal; src: url('{{ resource_path('fonts/pdf/Jost-Medium.ttf') }}') format('truetype'); }
    @page { margin: 14mm 15mm 24mm 15mm; }
    body { font-family: 'Jost', 'DejaVu Sans', sans-serif; font-size: 9pt; color: #2A2A35; line-height: 1.28; }
    table { width: 100%; border-collapse: collapse; }
    p { margin: 0; }
    a { color: #2D4BFF; text-decoration: none; }
    /* Cabecera oscura */
    .hero { background: #0B0B10; border-radius: 3mm; padding: 7mm 8mm 6.5mm; }
    .hero td { border: 0; padding: 0; vertical-align: middle; }
    .logo { height: 11mm; }
    .doc-tipo { font-family: 'Jost Medium'; font-size: 7pt; letter-spacing: 3px; text-transform: uppercase; color: #8EA0FF; }
    .doc-numero { font-family: 'Jost Light'; font-size: 22pt; color: #FFFFFF; line-height: 1.1; margin: 1mm 0 1.5mm; letter-spacing: 0.5px; }
    .doc-datos { font-size: 7.8pt; color: #A7A2B5; }
    .hero-linea { height: 0.6mm; background: #2D4BFF; width: 22mm; margin-top: 5mm; }
    /* Bienvenida emocional */
    .bienvenida { margin: 8mm 0 1mm; }
    .titular { font-family: 'Jost Light'; font-size: 19pt; color: #0B0B10; line-height: 1.2; letter-spacing: -0.2px; }
    .entradilla { font-size: 9.8pt; color: #4A4A58; margin-top: 3mm; line-height: 1.38; }
    /* Títulos de sección */
    h2, .seccion { font-family: 'Jost Medium'; font-size: 7pt; letter-spacing: 2.6px; text-transform: uppercase; color: #8A8A99; margin: 8mm 0 3mm; font-weight: normal; }
    .etiqueta { font-family: 'Jost Medium'; font-size: 6.6pt; letter-spacing: 2px; text-transform: uppercase; color: #8A8A99; margin: 0 0 1.4mm; }
    .bloques td { border: 0; vertical-align: top; padding: 0; }
    .bloque { background: #F6F6F9; border-radius: 2mm; padding: 4mm 4.5mm; }
    /* Tablas de conceptos */
    .lista th { text-align: left; font-family: 'Jost Medium'; font-size: 6.6pt; letter-spacing: 1.6px; text-transform: uppercase; color: #8A8A99; padding: 0 2.5mm 2.2mm; border-bottom: 0.4mm solid #0B0B10; font-weight: normal; }
    .lista td { border-bottom: 0.25mm solid #E6E6EC; padding: 3mm 2.5mm; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .tenue { color: #6B6B7B; }
    .pequena { font-size: 7.4pt; }
    .fuerte { font-weight: bold; color: #0B0B10; }
    /* Totales */
    .totales { page-break-inside: avoid; }
    .totales td { border: 0; padding: 1mm 3mm; }
    .totales .total td { background: #0B0B10; color: #FFFFFF; font-family: 'Jost Medium'; font-size: 12pt; padding: 3.5mm 3mm; }
    .totales .total td.num { font-family: 'Jost'; font-weight: bold; font-size: 13pt; }
    /* Notas y disposición */
    .nota { background: #F1F3FF; border-radius: 2mm; padding: 4mm 5mm; margin-top: 6mm; page-break-inside: avoid; }
    .disposicion { border: 0.3mm solid #E6E6EC; border-radius: 2mm; padding: 4mm 5mm; margin-top: 5mm; page-break-inside: avoid; }
    .disposicion .titulo { font-family: 'Jost Medium'; font-size: 10.5pt; color: #0B0B10; }
    /* Pie con enlaces */
    .pie { position: fixed; bottom: -16mm; left: 0; right: 0; border-top: 0.25mm solid #E6E6EC; padding-top: 2.5mm; font-size: 6.9pt; color: #8A8A99; line-height: 1.6; }
    .pie td { border: 0; padding: 0; vertical-align: top; }
    .pie a { color: #2D4BFF; }
    .pagina:after { content: counter(page); }
    .lista th, .lista td.corto { white-space: nowrap; }
    .desglose th { letter-spacing: 1px; }
    /* «Tu colección»: foto de cada cara en una tarjeta clara, con su nombre debajo */
    .prenda { page-break-inside: avoid; border-bottom: 0.25mm solid #E6E6EC; padding: 4.5mm 0; }
    .prenda td { border: 0; padding: 0; vertical-align: top; }
    .foto-prenda { width: 38mm; background: #F4F4F7; border-radius: 2.5mm; padding: 2mm 2mm 1.6mm; text-align: center; }
    .foto-prenda img { width: 38mm; height: 45.6mm; }
    .foto-cara { font-family: 'Jost Medium'; font-size: 6.4pt; letter-spacing: 1.8px; text-transform: uppercase; color: #8A8A99; margin-top: 1.2mm; }
    .nombre { font-family: 'Jost Medium'; font-size: 11pt; color: #0B0B10; }
    .importe { font-family: 'Jost Medium'; font-size: 11.5pt; color: #0B0B10; }
