<?php

namespace App\Services\Platform;

use App\DTOs\Platform\PlatformListFiltersDTO;
use App\DTOs\Platform\UpdatePlatformInvoiceDTO;
use App\Events\PlatformInvoiceUpdated;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Platform\Concerns\PaginatesPlatformResults;
use Illuminate\Database\Eloquent\Builder;

class PlatformInvoiceService
{
    use PaginatesPlatformResults;

    public function list(PlatformListFiltersDTO $filters)
    {
        $query = Invoice::query()->with(['organization', 'customer']);

        $query
            ->when($filters->organizationId, fn (Builder $query, int $organizationId) => $query->where('organization_id', $organizationId))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters->dateFrom, fn (Builder $query, string $date) => $query->whereDate('issue_date', '>=', $date))
            ->when($filters->dateTo, fn (Builder $query, string $date) => $query->whereDate('issue_date', '<=', $date))
            ->when($filters->search, function (Builder $query, string $search) {
                $query->where(function (Builder $nested) use ($search) {
                    $nested->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('irn', 'like', "%{$search}%")
                        ->orWhereHas('organization', fn (Builder $orgQuery) => $orgQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($search) {
                            $customerQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            });

        $this->applySort($query, $filters, [
            'created_at' => 'created_at',
            'issue_date' => 'issue_date',
            'invoice_number' => 'invoice_number',
            'status' => 'status',
            'payable_amount' => 'payable_amount',
        ], 'created_at');

        return $query->paginate($filters->perPage);
    }

    public function find(string $value): Invoice
    {
        return Invoice::where('uuid', $value)->orWhere('id', $value)->firstOrFail();
    }

    public function show(string $value): Invoice
    {
        return $this->find($value)->load(['organization', 'customer', 'lines', 'taxTotals', 'nrsSubmissions', 'stateTransitions']);
    }

    public function update(User $actor, Invoice $invoice, UpdatePlatformInvoiceDTO $dto): Invoice
    {
        $before = $this->auditSnapshot($invoice->only(['status', 'payment_status']));

        if ($dto->hasStatus) {
            $invoice->status = $dto->status;
        }

        if ($dto->hasPaymentStatus) {
            $invoice->payment_status = $dto->paymentStatus;
        }

        $invoice->save();

        PlatformInvoiceUpdated::dispatch(
            actor: $actor,
            invoice: $invoice,
            before: $before,
            after: $this->auditSnapshot($invoice->only(['status', 'payment_status'])),
            reason: $dto->reason,
        );

        return $invoice->fresh(['organization', 'customer']);
    }

    private function auditSnapshot(array $values): array
    {
        return collect($values)
            ->map(function ($value) {
                if ($value instanceof \BackedEnum) {
                    return $value->value;
                }

                if ($value instanceof \DateTimeInterface) {
                    return $value->format(DATE_ATOM);
                }

                return $value;
            })
            ->all();
    }
}
