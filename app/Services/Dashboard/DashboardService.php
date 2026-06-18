<?php

namespace App\Services\Dashboard;

use App\Enums\InvoiceStatus;
use App\Exceptions\NrsApiException;
use App\Exceptions\NrsConnectionException;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\Nrs\NrsClient;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function __construct(
        protected NrsClient $nrsClient
    ) {}

    /**
     * Get summary statistics for the organization.
     *
     * Uses a single aggregation query for invoice stats and short-lived
     * caching (30s) to avoid 10+ queries on every dashboard load.
     */
    public function getStats(int $organizationId): array
    {
        return Cache::remember("dashboard:stats:{$organizationId}", 30, function () use ($organizationId) {
            $invoiceStats = Invoice::where('organization_id', $organizationId)
                ->selectRaw('COUNT(*) as total_invoices')
                ->selectRaw('SUM(status = ?) as validated', [InvoiceStatus::VALIDATED->value])
                ->selectRaw('SUM(status = ?) as signed', [InvoiceStatus::SIGNED->value])
                ->selectRaw('SUM(status = ?) as transmitted', [InvoiceStatus::TRANSMITTED->value])
                ->selectRaw('SUM(status = ?) as confirmed', [InvoiceStatus::CONFIRMED->value])
                ->selectRaw('SUM(status IN (?, ?, ?) AND updated_at < ?) as stuck_pending', [
                    InvoiceStatus::PENDING_VALIDATION->value,
                    InvoiceStatus::PENDING_SIGNING->value,
                    InvoiceStatus::PENDING_TRANSMIT->value,
                    now()->subMinutes(5),
                ])
                ->selectRaw('SUM(CASE WHEN status = ? THEN payable_amount ELSE 0 END) as revenue_payable', [InvoiceStatus::CONFIRMED->value])
                ->selectRaw('SUM(CASE WHEN status = ? THEN tax_inclusive_amount ELSE 0 END) as revenue_tax_inclusive', [InvoiceStatus::CONFIRMED->value])
                ->first();

            return [
                'total_invoices' => (int) $invoiceStats->total_invoices,
                'validated' => (int) $invoiceStats->validated,
                'signed' => (int) $invoiceStats->signed,
                'transmitted' => (int) $invoiceStats->transmitted,
                'confirmed' => (int) $invoiceStats->confirmed,
                'stuck_pending' => (int) $invoiceStats->stuck_pending,
                'revenue' => [
                    'payable_amount_sum' => (float) $invoiceStats->revenue_payable,
                    'tax_inclusive_amount_sum' => (float) $invoiceStats->revenue_tax_inclusive,
                ],
                'customers' => Customer::where('organization_id', $organizationId)->count(),
                'products' => Product::where('organization_id', $organizationId)->count(),
            ];
        });
    }

    /**
     * Get recent activity logs for the organization.
     */
    public function getRecentActivity(int $organizationId, int $limit = 5)
    {
        return ActivityLog::where('organization_id', $organizationId)
            ->with('user:id,uuid,first_name,last_name,email')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Check if the NRS (FIRS) gateway is online and responding.
     * Uses the documented self-health-check endpoint for APP readiness.
     */
    public function checkNrsHealth(): array
    {
        $start = microtime(true);
        try {
            $response = $this->nrsClient->get('api/v1/invoice/transmit/self-health-check');
            $latency = round((microtime(true) - $start) * 1000);

            return [
                'status' => 'online',
                'latency' => "{$latency}ms",
                'data' => $response->json(),
            ];
        } catch (\Exception $e) {
            $errorMessage = ($e instanceof NrsConnectionException || $e instanceof NrsApiException)
                ? $e->getMessage()
                : 'The official FIRS/NRS service is temporarily unreachable. Please check your internet connection or try again later.';

            return [
                'status' => 'offline',
                'error' => $errorMessage,
            ];
        }
    }
}
