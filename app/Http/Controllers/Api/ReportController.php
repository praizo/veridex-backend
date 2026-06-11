<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Get filtered invoice analytics for the active organization.
     */
    public function invoiceSummary(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;
        $filters = $request->validate([
            'customer_type' => ['nullable', 'string', 'in:all,business,individual,government'],
            'status' => ['nullable', 'string'],
            'payment_status' => ['nullable', 'string'],
            'tax_category_id' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $filters['customer_type'] ??= 'business';

        $baseQuery = $this->filteredInvoiceQuery($orgId, $filters);

        $statusSummary = (clone $baseQuery)
            ->selectRaw('
                COUNT(*) as total_count,
                SUM(payable_amount) as total_amount,
                SUM(tax_inclusive_amount - tax_exclusive_amount) as total_vat,
                invoices.status as status
            ')
            ->groupBy('invoices.status')
            ->get();

        $paymentSummary = (clone $baseQuery)
            ->selectRaw('
                COUNT(*) as total_count,
                SUM(payable_amount) as total_amount,
                invoices.payment_status as payment_status
            ')
            ->groupBy('invoices.payment_status')
            ->get();

        $topCustomers = (clone $baseQuery)
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->selectRaw('
                customers.name as customer_name,
                customers.type as customer_type,
                COUNT(*) as total_count,
                SUM(invoices.payable_amount) as total_amount
            ')
            ->groupBy('customers.id', 'customers.name', 'customers.type')
            ->orderByDesc('total_amount')
            ->limit(8)
            ->get();

        $monthlyTrend = (clone $baseQuery)
            ->select(['issue_date', 'payable_amount', 'tax_inclusive_amount', 'tax_exclusive_amount'])
            ->get()
            ->groupBy(fn ($invoice) => optional($invoice->issue_date)->format('Y-m') ?? 'Unknown')
            ->map(fn ($invoices, $period) => [
                'period' => $period,
                'total_count' => $invoices->count(),
                'total_amount' => $invoices->sum('payable_amount'),
                'total_vat' => $invoices->sum(fn ($invoice) => $invoice->tax_inclusive_amount - $invoice->tax_exclusive_amount),
            ])
            ->sortKeys()
            ->values();

        return response()->json([
            'data' => $statusSummary,
            'status_summary' => $statusSummary,
            'payment_summary' => $paymentSummary,
            'top_customers' => $topCustomers,
            'monthly_trend' => $monthlyTrend,
            'summary' => [
                'count' => $statusSummary->sum('total_count'),
                'amount' => $statusSummary->sum('total_amount'),
                'vat' => $statusSummary->sum('total_vat'),
                'average_invoice_value' => $statusSummary->sum('total_count') > 0
                    ? round($statusSummary->sum('total_amount') / $statusSummary->sum('total_count'), 2)
                    : 0,
            ],
        ]);
    }

    /**
     * Backward-compatible B2C summary endpoint.
     */
    public function b2cSummary(Request $request): JsonResponse
    {
        $request->merge(['customer_type' => 'individual']);

        return $this->invoiceSummary($request);
    }

    /**
     * Export invoices for the organization as a CSV file.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $orgId = $request->user()->current_organization_id;

        $filters = $request->validate([
            'customer_type' => ['nullable', 'string', 'in:all,business,individual,government'],
            'status' => ['nullable', 'string'],
            'payment_status' => ['nullable', 'string'],
            'tax_category_id' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $filters['customer_type'] ??= 'business';

        $response = new StreamedResponse(function () use ($orgId, $filters) {
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
            $query = $this->filteredInvoiceQuery($orgId, $filters)
                ->with('customer')
                ->latest('invoices.created_at');

            $query->chunk(100, function ($invoices) use ($handle) {
                foreach ($invoices as $invoice) {
                    $vatTotal = $invoice->tax_inclusive_amount - $invoice->tax_exclusive_amount;

                    fputcsv($handle, [
                        $invoice->invoice_number,
                        $this->enumValue($invoice->status),
                        $this->enumValue($invoice->payment_status),
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

    private function filteredInvoiceQuery(int $orgId, array $filters)
    {
        $query = Invoice::where('invoices.organization_id', $orgId);

        if (! empty($filters['customer_type']) && $filters['customer_type'] !== 'all') {
            $query->whereHas('customer', function ($q) use ($filters) {
                $q->where('type', $filters['customer_type']);
            });
        }

        if (! empty($filters['status'])) {
            $query->whereIn('invoices.status', $this->statusFilterValues($filters['status']));
        }

        if (! empty($filters['payment_status'])) {
            $query->where('invoices.payment_status', $filters['payment_status']);
        }

        if (! empty($filters['tax_category_id'])) {
            $query->whereHas('taxTotals', function ($q) use ($filters) {
                $q->where('tax_category_id', $filters['tax_category_id']);
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('invoices.issue_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('invoices.issue_date', '<=', $filters['date_to']);
        }

        return $query;
    }

    private function statusFilterValues(string $status): array
    {
        return match ($status) {
            'validated' => [
                'validated',
                'pending_signing',
                'sign_failed',
                'signed',
                'pending_transmit',
                'transmit_failed',
                'transmitted',
            ],
            'signed' => [
                'signed',
                'pending_transmit',
                'transmit_failed',
                'transmitted',
            ],
            'transmitted' => ['transmitted'],
            default => [$status],
        };
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
