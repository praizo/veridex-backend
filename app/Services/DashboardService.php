<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ActivityLog;
use App\Enums\InvoiceStatus;
use Illuminate\Support\Facades\DB;
use App\Services\Nrs\NrsClient;

class DashboardService
{
    public function __construct(
        protected NrsClient $nrsClient
    ) {}

    /**
     * Get summary statistics for the organization.
     */
    public function getStats(int $organizationId): array
    {
        return [
            'total_invoices' => Invoice::where('organization_id', $organizationId)->count(),
            'validated'      => Invoice::where('organization_id', $organizationId)
                                  ->where('status', InvoiceStatus::VALIDATED)
                                  ->count(),
            'signed'         => Invoice::where('organization_id', $organizationId)
                                  ->where('status', InvoiceStatus::SIGNED)
                                  ->count(),
            'transmitted'    => Invoice::where('organization_id', $organizationId)
                                  ->where('status', InvoiceStatus::TRANSMITTED)
                                  ->count(),
            'confirmed'      => Invoice::where('organization_id', $organizationId)
                                  ->where('status', InvoiceStatus::CONFIRMED)
                                  ->count(),
            'stuck_pending'  => Invoice::where('organization_id', $organizationId)
                                  ->whereIn('status', [
                                      InvoiceStatus::PENDING_VALIDATION,
                                      InvoiceStatus::PENDING_SIGNING,
                                      InvoiceStatus::PENDING_TRANSMIT
                                  ])
                                  ->where('updated_at', '<', now()->subMinutes(5))
                                  ->count(),
            'revenue'        => [
                'payable_amount_sum'     => Invoice::where('organization_id', $organizationId)
                                              ->where('status', InvoiceStatus::CONFIRMED)
                                              ->sum('payable_amount'),
                'tax_inclusive_amount_sum'=> Invoice::where('organization_id', $organizationId)
                                              ->where('status', InvoiceStatus::CONFIRMED)
                                              ->sum('tax_inclusive_amount'),
            ],
            'customers'      => Customer::where('organization_id', $organizationId)->count(),
            'products'       => Product::where('organization_id', $organizationId)->count(),
        ];
    }

    /**
     * Get recent activity logs for the organization.
     */
    public function getRecentActivity(int $organizationId, int $limit = 5)
    {
        return ActivityLog::where('organization_id', $organizationId)
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Check if the NRS (FIRS) gateway is online and responding.
     */
    public function checkNrsHealth(): array
    {
        $start = microtime(true);
        try {
            // Ping a lightweight resource endpoint
            $this->nrsClient->get('invoice/resources/countries');
            $latency = round((microtime(true) - $start) * 1000);
            
            return [
                'status'  => 'online',
                'latency' => "{$latency}ms",
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'offline',
                'error'  => $e->getMessage(),
            ];
        }
    }
}
