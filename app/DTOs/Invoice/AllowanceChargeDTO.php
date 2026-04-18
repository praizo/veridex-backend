<?php

namespace App\DTOs\Invoice;

final readonly class AllowanceChargeDTO
{
    public function __construct(
        public bool $charge_indicator,
        public float $amount,
        public ?string $reason_code = null,
        public ?string $reason_text = null,
        public ?float $multiplier_factor_numeric = null,
        public ?float $base_amount = null,
        public ?string $tax_category_id = null,
        public ?float $tax_percent = null,
        public ?string $tax_scheme_id = null,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}