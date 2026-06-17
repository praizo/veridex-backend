<?php

namespace App\Services\Platform;

use App\DTOs\Platform\PlatformAnalyticsFiltersDTO;
use App\Enums\InvoiceStatus;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\NrsApiLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PlatformAnalyticsService
{
    public function __construct(
        private readonly PlatformSystemService $systemService,
    ) {}

    public function summary(): array
    {
        return [
            'organizations' => [
                'total' => Organization::count(),
                'onboarded' => Organization::where('onboarding_status', 'onboarded')->count(),
                'pending' => Organization::where('onboarding_status', 'pending')->count(),
                'verified' => Organization::whereNotNull('verified_at')->count(),
                'new_today' => Organization::whereDate('created_at', today())->count(),
                'new_this_month' => Organization::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            ],
            'invoices' => [
                'total' => Invoice::count(),
                'created_today' => Invoice::whereDate('created_at', today())->count(),
                'created_this_month' => Invoice::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
                'draft' => Invoice::where('status', InvoiceStatus::DRAFT->value)->count(),
                'signed' => Invoice::where('status', InvoiceStatus::SIGNED->value)->count(),
                'transmitted' => Invoice::where('status', InvoiceStatus::TRANSMITTED->value)->count(),
                'failed' => Invoice::whereIn('status', [
                    InvoiceStatus::VALIDATION_FAILED->value,
                    InvoiceStatus::SIGN_FAILED->value,
                    InvoiceStatus::TRANSMIT_FAILED->value,
                ])->count(),
                'total_value' => (float) Invoice::sum('payable_amount'),
            ],
            'users' => [
                'total' => User::count(),
                'active' => User::whereNotNull('email_verified_at')->whereNull('suspended_at')->count(),
                'new_this_month' => User::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            ],
            'system' => [
                'failed_jobs' => $this->systemService->failedJobsCount(),
                'nrs_failures' => NrsApiLog::where('status_code', '>=', 400)->count(),
                'mail_failures' => $this->systemService->failedJobsCount('%Mail%'),
                'slow_jobs' => 0,
            ],
        ];
    }

    public function analytics(PlatformAnalyticsFiltersDTO $filters): array
    {
        $invoiceQuery = Invoice::query()
            ->when($filters->dateFrom, fn (Builder $query) => $query->whereDate('issue_date', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn (Builder $query) => $query->whereDate('issue_date', '<=', $filters->dateTo))
            ->when($filters->organizationId, fn (Builder $query) => $query->where('organization_id', $filters->organizationId));

        $invoices = (clone $invoiceQuery)->get(['organization_id', 'status', 'payable_amount', 'issue_date']);

        return [
            'invoice_volume' => $invoices
                ->groupBy(fn (Invoice $invoice) => optional($invoice->issue_date)->format('Y-m') ?? 'undated')
                ->map(fn ($items, string $period) => ['period' => $period, 'total_count' => $items->count()])
                ->values(),
            'invoice_value' => $invoices
                ->groupBy(fn (Invoice $invoice) => optional($invoice->issue_date)->format('Y-m') ?? 'undated')
                ->map(fn ($items, string $period) => ['period' => $period, 'total_value' => (float) $items->sum('payable_amount')])
                ->values(),
            'organization_onboarding' => $this->organizationTrend($filters),
            'invoice_status_breakdown' => $invoices
                ->groupBy(fn (Invoice $invoice) => is_string($invoice->status) ? $invoice->status : $invoice->status->value)
                ->map(fn ($items, string $status) => ['status' => $status, 'total_count' => $items->count(), 'total_value' => (float) $items->sum('payable_amount')])
                ->values(),
            'top_organizations' => (clone $invoiceQuery)
                ->select('organizations.name as organization_name', DB::raw('count(*) as total_count'), DB::raw('sum(payable_amount) as total_value'))
                ->join('organizations', 'organizations.id', '=', 'invoices.organization_id')
                ->groupBy('organizations.id', 'organizations.name')
                ->orderByDesc('total_value')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'organization_name' => $row->organization_name,
                    'total_count' => (int) $row->total_count,
                    'total_value' => (float) $row->total_value,
                ]),
            'nrs_trend' => $this->nrsTrend($filters),
            'user_growth' => $this->userGrowth($filters),
            'activity_volume' => $this->activityTrend($filters),
        ];
    }

    private function organizationTrend(PlatformAnalyticsFiltersDTO $filters)
    {
        return Organization::query()
            ->when($filters->dateFrom, fn (Builder $query) => $query->whereDate('created_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn (Builder $query) => $query->whereDate('created_at', '<=', $filters->dateTo))
            ->get(['created_at'])
            ->groupBy(fn (Organization $organization) => $organization->created_at->format('Y-m'))
            ->map(fn ($items, string $period) => ['period' => $period, 'total_count' => $items->count()])
            ->values();
    }

    private function nrsTrend(PlatformAnalyticsFiltersDTO $filters)
    {
        return NrsApiLog::query()
            ->when($filters->dateFrom, fn (Builder $query) => $query->whereDate('created_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn (Builder $query) => $query->whereDate('created_at', '<=', $filters->dateTo))
            ->when($filters->organizationId, fn (Builder $query) => $query->where('organization_id', $filters->organizationId))
            ->get(['status_code', 'created_at'])
            ->groupBy(fn (NrsApiLog $log) => $log->created_at->format('Y-m-d'))
            ->map(fn ($items, string $period) => [
                'period' => $period,
                'success' => $items->where('status_code', '<', 400)->count(),
                'failed' => $items->where('status_code', '>=', 400)->count(),
            ])
            ->values();
    }

    private function userGrowth(PlatformAnalyticsFiltersDTO $filters)
    {
        return User::query()
            ->when($filters->dateFrom, fn (Builder $query) => $query->whereDate('created_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn (Builder $query) => $query->whereDate('created_at', '<=', $filters->dateTo))
            ->get(['created_at'])
            ->groupBy(fn (User $user) => $user->created_at->format('Y-m'))
            ->map(fn ($items, string $period) => ['period' => $period, 'total_count' => $items->count()])
            ->values();
    }

    private function activityTrend(PlatformAnalyticsFiltersDTO $filters)
    {
        return ActivityLog::query()
            ->when($filters->dateFrom, fn (Builder $query) => $query->whereDate('created_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn (Builder $query) => $query->whereDate('created_at', '<=', $filters->dateTo))
            ->when($filters->organizationId, fn (Builder $query) => $query->where('organization_id', $filters->organizationId))
            ->get(['created_at'])
            ->groupBy(fn (ActivityLog $log) => $log->created_at->format('Y-m-d'))
            ->map(fn ($items, string $period) => ['period' => $period, 'total_count' => $items->count()])
            ->values();
    }
}
