<?php

namespace App\DTOs\Invoice;

final readonly class LegalMonetaryTotalDTO
{
    public function __construct(
        public float $line_extension_amount,
        public float $tax_exclusive_amount,
        public float $tax_inclusive_amount,
        public float $payable_amount,
        public float $allowance_total_amount = 0.0,
        public float $charge_total_amount = 0.0,
        public float $prepaid_amount = 0.0,
        public float $payable_rounding_amount = 0.0,
    ) {}
}
