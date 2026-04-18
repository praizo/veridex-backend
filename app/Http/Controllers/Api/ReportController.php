<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Get summary statistics for B2C invoices.
     */
    public function b2cSummary(Request $request): \Illuminate\Http\JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $summary = Invoice::where('organization_id', $orgId)
            ->whereHas('customer', function ($query) {
                $query->where('type', 'individual');
            })
            ->selectRaw('
                COUNT(*) as total_count,
                SUM(payable_amount) as total_amount,
                SUM(tax_inclusive_amount - tax_exclusive_amount) as total_vat,
                status
            ')
            ->groupBy('status')
            ->get();

        return response()->json([
            'data' => $summary,
            'summary' => [
                'count' => $summary->sum('total_count'),
                'amount' => $summary->sum('total_amount'),
                'vat' => $summary->sum('total_vat'),
            ]
        ]);
    }

    /**
     * Export invoices for the organization as a CSV file.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $orgId = $request->user()->current_organization_id;

        $response = new StreamedResponse(function () use ($orgId) {
            $handle = fopen('php://output', 'w');

            // Write CSV headers
            fputcsv($handle, [
                'Invoice Number',
                'Status',
                'Payment Status',
                'IRN',
                'Issue Date',
                'Due Date',
                'Currency',
                'Customer',
                'Customer TIN',
                'Subtotal (Tax Exclusive)',
                'VAT Total',
                'Grand Total (Payable)',
            ]);

            // Stream invoices in chunks to prevent memory exhaustion
            $query = Invoice::where('organization_id', $orgId)
                ->with('customer')
                ->latest();

            if ($request->has('customer_type')) {
                $query->whereHas('customer', function ($q) use ($request) {
                    $q->where('type', $request->customer_type);
                });
            }

            $query->chunk(100, function ($invoices) use ($handle) {
                    foreach ($invoices as $invoice) {
                        $vatTotal = $invoice->tax_inclusive_amount - $invoice->tax_exclusive_amount;

                        fputcsv($handle, [
                            $invoice->invoice_number,
                            $invoice->status,
                            $invoice->payment_status,
                            $invoice->irn ?? 'N/A',
                            $invoice->issue_date->format('Y-m-d'),
                            $invoice->due_date ? $invoice->due_date->format('Y-m-d') : 'N/A',
                            $invoice->document_currency_code,
                            $invoice->customer->name ?? 'N/A',
                            $invoice->customer->tin ?? 'N/A',
                            number_format($invoice->tax_exclusive_amount, 2, '.', ''),
                            number_format($vatTotal, 2, '.', ''),
                            number_format($invoice->payable_amount, 2, '.', ''),
                        ]);
                    }
                });

            fclose($handle);
        });

        $date = now()->format('Y_m_d_His');
        
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="invoices_export_'.$date.'.csv"');

        return $response;
    }
}
