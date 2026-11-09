<?php

namespace App\Dominio\Facturacion;

use App\Models\Factura;
use Barryvdh\DomPDF\Facade\Pdf;

/** El PDF de una factura (paso 6.5a), con dompdf como en Clave de Alma (ADR 0017). Se genera al pedirlo: la factura no cambia. */
final class PdfFactura
{
    public static function generar(Factura $factura): string
    {
        $factura->loadMissing('lineas', 'pedido', 'rectifica');
        // El QR de cotejo solo hace falta con Veri*Factu activado (hoy no): al activarlo se añade su librería.
        $qr = null;

        return Pdf::loadView('pdf.factura', ['factura' => $factura, 'qr' => $qr])
            ->setPaper('a4')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true])
            ->output();
    }
}
