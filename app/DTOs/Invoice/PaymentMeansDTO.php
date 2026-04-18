<?php

namespace App\DTOs\Invoice;

final readonly class PaymentMeansDTO
{
    public function __construct(
        public string $payment_means_code = '1',
        public ?string $payee_financial_account_id = null,
        public ?string $payee_financial_account_name = null,
        public ?string $financial_institution_branch_id = null,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}