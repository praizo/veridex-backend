<?php

namespace App\DTOs\Invoice;

final readonly class TaxTotalDTO
{
    public function __construct(
        public float $tax_amount,
        public string $tax_category_id,
        public float $tax_percent,
        public ?float $taxable_amount = null,
        public string $tax_scheme_id = 'VAT',
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}