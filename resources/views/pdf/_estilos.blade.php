{{-- Diseño común de los PDF (D205): blanco, tinta casi negra, el azul de la marca solo en detalles. dompdf: CSS sencillo. --}}
    @page { margin: 16mm 16mm 22mm 16mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 8.6pt; color: #2B2B36; line-height: 1.45; }
    table { width: 100%; border-collapse: collapse; }
    .cabecera td { border: 0; padding: 0; vertical-align: top; }
    .logo { height: 10mm; }
    .doc-tipo { font-size: 6.8pt; letter-spacing: 2px; text-transform: uppercase; color: #2D4BFF; font-weight: bold; margin: 0; }
    .doc-numero { font-size: 16pt; font-weight: bold; color: #0B0B10; margin: 1mm 0 1mm; }
    .doc-datos { font-size: 7.8pt; color: #6B6B7B; margin: 0; }
    .linea-marca { height: 0.9mm; background: #2D4BFF; margin: 5mm 0 7mm; }
    h2 { font-size: 7pt; letter-spacing: 1.6px; text-transform: uppercase; color: #6B6B7B; margin: 7mm 0 2.5mm; font-weight: bold; }
    .etiqueta { font-size: 6.6pt; letter-spacing: 1.4px; text-transform: uppercase; color: #8A8A99; margin: 0 0 1.2mm; font-weight: bold; }
    .bloques td { border: 0; vertical-align: top; padding: 0; }
    .bloque { background: #F6F6F9; padding: 3.5mm 4mm; }
    .bloque p { margin: 0; }
    .lista th { text-align: left; font-size: 6.8pt; letter-spacing: 1px; text-transform: uppercase; color: #6B6B7B; background: #F3F3F6; padding: 2.2mm 2.5mm; font-weight: bold; }
    .lista td { border-bottom: 0.3mm solid #E6E6EC; padding: 2.6mm 2.5mm; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .tenue { color: #6B6B7B; }
    .pequena { font-size: 7.2pt; }
    .totales td { border: 0; padding: 1.2mm 2.5mm; }
    .totales .total td { background: #0B0B10; color: #FFFFFF; font-size: 10.5pt; font-weight: bold; padding: 3mm 2.5mm; }
    .nota { border-left: 0.9mm solid #2D4BFF; background: #F1F3FF; padding: 3mm 4mm; margin-top: 6mm; }
    .pie { position: fixed; bottom: -14mm; left: 0; right: 0; border-top: 0.3mm solid #E6E6EC; padding-top: 2.5mm; font-size: 6.8pt; color: #8A8A99; }
    .pie td { border: 0; padding: 0; }
    .pagina:after { content: counter(page); }
    .lista th, .lista td.corto { white-space: nowrap; }
    .desglose th { letter-spacing: 0.4px; }
