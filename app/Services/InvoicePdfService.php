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

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'qrCodeSrc'));

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
