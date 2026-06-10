<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InvoicePdfService
{
    /**
     * Generate a PDF stream for the given invoice.
     */
    public function generate(Invoice $invoice)
    {
        // Load all necessary relationships for the PDF
        $invoice->load([
            'organization',
            'customer',
            'lines',
            'taxTotals',
            'paymentMeans',
        ]);

        $qrCodeSrc = null;
        if ($invoice->irn) {
            // Generate standard FIRS compliance validation URL
            $validationUrl = 'https://firs.gov.ng/verify?irn='.$invoice->irn;

            // Build base64 SVG for the PDF. SVG is better for PDFs and doesn't require Imagick.
            $qrCodeImage = QrCode::format('svg')->size(150)->generate($validationUrl);
            $qrCodeSrc = 'data:image/svg+xml;base64,'.base64_encode($qrCodeImage);
        }

        $logoSvg = <<<'SVG'
<svg width="26" height="26" viewBox="0 0 26 26" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M3 5.5L13 2L23 5.5V13C23 18.5 18.5 22.5 13 24C7.5 22.5 3 18.5 3 13V5.5Z" stroke="#0a1d43" stroke-width="1.6" stroke-linejoin="round"/>
    <path d="M8 12.5L11.5 16L18 9.5" stroke="#0a1d43" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG;
        $logoSrc = 'data:image/svg+xml;base64,'.base64_encode($logoSvg);

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'qrCodeSrc', 'logoSrc'));

        // Output as A4
        $pdf->setPaper('a4', 'portrait');

        $pdfBinary = $pdf->output();
        $status = $invoice->status?->value ?? $invoice->status;

        if (in_array($status, ['signed', 'pending_transmit', 'transmit_failed', 'transmitted', 'confirmed']) && ! $invoice->pdf_hash) {
            $invoice->forceFill(['pdf_hash' => hash('sha256', $pdfBinary)])->save();
        }

        return Response::make($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice_'.$invoice->invoice_number.'.pdf"',
        ]);
    }
}
