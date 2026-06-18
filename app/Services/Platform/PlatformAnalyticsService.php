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

    /**
     * Analytics data using DB-level aggregation instead of loading full tables into PHP.
     *
     * Previous implementation used ->get() on every table then grouped/mapped in PHP.
     * At scale (100k+ invoices, growing NRS logs) this would OOM or timeout.
     * Now all grouping and aggregation happens in SQL.
     */
    public function analytics(PlatformAnalyticsFiltersDTO $filters): array
    {
        return [
            'invoice_volume' => $this->invoiceVolume($filters),
            'invoice_value' => $this->invoiceValue($filters),
            'organization_onboarding' => $this->organizationTrend($filters),
            'invoice_status_breakdown' => $this->invoiceStatusBreakdown($filters),
            'top_organizations' => $this->topOrganizations($filters),
            'nrs_trend' => $this->nrsTrend($filters),
            'user_growth' => $this->userGrowth($filters),
            'activity_volume' => $this->activityTrend($filters),
        ];
    }

    private function invoiceBaseQuery(PlatformAnalyticsFiltersDTO $filters): Builder
    {
        return Invoice::query()
            ->when($filters->dateFrom, fn (Builder $query) => $query->whereDate('issue_date', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn (Builder $query) => $query->whereDate('issue_date', '<=', $filters->dateTo))
            ->when($filters->organizationId, fn (Builder $query) => $query->where('organization_id', $filters->organizationId));
    }

    private function invoiceVolume(PlatformAnalyticsFiltersDTO $filters): array
    {
        $period = $this->datePeriodExpression('issue_date', 'month');

        return $this->invoiceBaseQuery($filters)
            ->selectRaw("{$period} as period, COUNT(*) as total_count")
            ->whereNotNull('issue_date')
            ->groupByRaw($period)
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => ['period' => $row->period, 'total_count' => (int) $row->total_count])
            ->values()
            ->all();
    }

    private function invoiceValue(PlatformAnalyticsFiltersDTO $filters): array
    {
        $period = $this->datePeriodExpression('issue_date', 'month');

        return $this->invoiceBaseQuery($filters)
            ->selectRaw("{$period} as period, SUM(payable_amount) as total_value")
            ->whereNotNull('issue_date')
            ->groupByRaw($period)
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => ['period' => $row->period, 'total_value' => (float) $row->total_value])
            ->values()
            ->all();
    }

    private function invoiceStatusBreakdown(PlatformAnalyticsFiltersDTO $filters): array
    {
        return $this->invoiceBaseQuery($filters)
            ->selectRaw('status, COUNT(*) as total_count, SUM(payable_amount) as total_value')
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'total_count' => (int) $row->total_count,
                'total_value' => (float) $row->total_value,
            ])
            ->values()
            ->all();
    }

    private function topOrganizations(PlatformAnalyticsFiltersDTO $filters): array
    {
        return $this->invoiceBaseQuery($filters)
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
            ])
            ->all();
    }

    private function organizationTrend(PlatformAnalyticsFiltersDTO $filters): array
    {
        $period = $this->datePeriodExpression('created_at', 'month');

        return Organization::query()
            ->when($filters->dateFrom, fn (Builder $query) => $query->whereDate('created_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn (Builder $query) => $query->whereDate('created_at', '<=', $filters->dateTo))
            ->selectRaw("{$period} as period, COUNT(*) as total_count")
            ->groupByRaw($period)
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => ['period' => $row->period, 'total_count' => (int) $row->total_count])
            ->values()
            ->all();
    }

    private function nrsTrend(PlatformAnalyticsFiltersDTO $filters): array
    {
        $period = $this->datePeriodExpression('created_at', 'day');

        return NrsApiLog::query()
            ->when($filters->dateFrom, fn (Builder $query) => $query->whereDate('created_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn (Builder $query) => $query->whereDate('created_at', '<=', $filters->dateTo))
            ->when($filters->organizationId, fn (Builder $query) => $query->where('organization_id', $filters->organizationId))
            ->selectRaw("{$period} as period")
            ->selectRaw('SUM(CASE WHEN status_code < 400 THEN 1 ELSE 0 END) as success')
            ->selectRaw('SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as failed')
            ->groupByRaw($period)
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => $row->period,
                'success' => (int) $row->success,
                'failed' => (int) $row->failed,
            ])
            ->values()
            ->all();
    }

    private function userGrowth(PlatformAnalyticsFiltersDTO $filters): array
    {
        $period = $this->datePeriodExpression('created_at', 'month');

        return User::query()
            ->when($filters->dateFrom, fn (Builder $query) => $query->whereDate('created_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn (Builder $query) => $query->whereDate('created_at', '<=', $filters->dateTo))
            ->selectRaw("{$period} as period, COUNT(*) as total_count")
            ->groupByRaw($period)
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => ['period' => $row->period, 'total_count' => (int) $row->total_count])
            ->values()
            ->all();
    }

    private function activityTrend(PlatformAnalyticsFiltersDTO $filters): array
    {
        $period = $this->datePeriodExpression('created_at', 'day');

        return ActivityLog::query()
            ->when($filters->dateFrom, fn (Builder $query) => $query->whereDate('created_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn (Builder $query) => $query->whereDate('created_at', '<=', $filters->dateTo))
            ->when($filters->organizationId, fn (Builder $query) => $query->where('organization_id', $filters->organizationId))
            ->selectRaw("{$period} as period, COUNT(*) as total_count")
            ->groupByRaw($period)
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => ['period' => $row->period, 'total_count' => (int) $row->total_count])
            ->values()
            ->all();
    }

    private function datePeriodExpression(string $column, string $grain): string
    {
        $format = $grain === 'day' ? '%Y-%m-%d' : '%Y-%m';
        $postgresFormat = $grain === 'day' ? 'YYYY-MM-DD' : 'YYYY-MM';
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => "to_char({$column}, '{$postgresFormat}')",
            'sqlite' => "strftime('{$format}', {$column})",
            'sqlsrv' => $grain === 'day'
                ? "FORMAT({$column}, 'yyyy-MM-dd')"
                : "FORMAT({$column}, 'yyyy-MM')",
            default => "DATE_FORMAT({$column}, '{$format}')",
        };
    }
}
