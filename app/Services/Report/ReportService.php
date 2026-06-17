<?php

namespace App\Services\Report;

use App\Models\Invoice;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;

class ReportService
{
    public function getInvoiceAnalytics(int $orgId, array $filters): array
    {
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
                customers.first_name,
                customers.last_name,
                customers.type as customer_type,
                COUNT(*) as total_count,
                SUM(invoices.payable_amount) as total_amount
            ')
            ->groupBy('customers.id', 'customers.first_name', 'customers.last_name', 'customers.type')
            ->orderByDesc('total_amount')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'customer_name' => trim($row->first_name.' '.$row->last_name),
                    'customer_type' => $row->customer_type,
                    'total_count' => $row->total_count,
                    'total_amount' => $row->total_amount,
                ];
            });

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

        return [
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
        ];
    }

    public function filteredInvoiceQuery(int $orgId, array $filters): Builder
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

    public function statusFilterValues(string $status): array
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

    public function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    /**
     * Write a single invoice row to the CSV handle.
     */
    public function writeCsvRow($handle, Invoice $invoice): void
    {
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
}
