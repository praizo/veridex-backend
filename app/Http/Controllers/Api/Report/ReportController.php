<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\InvoiceSummaryRequest;
use App\Models\Organization;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Report\ReportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * Get filtered invoice analytics for the active organization.
     */
    public function invoiceSummary(InvoiceSummaryRequest $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;
        $this->authorize('viewReports', Organization::findOrFail($orgId));

        $analytics = $this->reportService->getInvoiceAnalytics($orgId, $request->filters());

        return response()->json([
            'data' => $analytics['status_summary'],
            'status_summary' => $analytics['status_summary'],
            'payment_summary' => $analytics['payment_summary'],
            'top_customers' => $analytics['top_customers'],
            'monthly_trend' => $analytics['monthly_trend'],
            'summary' => $analytics['summary'],
        ]);
    }

    /**
     * Backward-compatible B2C summary endpoint.
     */
    public function b2cSummary(InvoiceSummaryRequest $request): JsonResponse
    {
        $request->merge(['customer_type' => 'individual']);

        return $this->invoiceSummary($request);
    }

    /**
     * Export invoices for the organization as a CSV file.
     */
    public function exportCsv(InvoiceSummaryRequest $request): StreamedResponse
    {
        $orgId = $request->user()->current_organization_id;
        $filters = $request->filters();
        $organization = Organization::findOrFail($orgId);

        $this->activityLog->logQueued(
            $request->user(),
            'report.invoices.exported',
            $organization,
            "Invoice report CSV exported for {$organization->name}.",
            ['filters' => $filters],
        );

        $response = new StreamedResponse(function () use ($orgId, $filters) {
            $handle = fopen('php://output', 'w');

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

            $query = $this->reportService->filteredInvoiceQuery($orgId, $filters)
                ->with('customer')
                ->latest('invoices.created_at');

            $query->chunk(100, function ($invoices) use ($handle) {
                foreach ($invoices as $invoice) {
                    $this->reportService->writeCsvRow($handle, $invoice);
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
