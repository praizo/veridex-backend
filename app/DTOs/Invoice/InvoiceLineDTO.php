<?php

namespace App\DTOs\Invoice;

final readonly class InvoiceLineDTO
{
    public function __construct(
        public string $line_id,
        public float $invoiced_quantity,
        public float $line_extension_amount,
        public string $item_name,
        public float $price_amount,
        public string $tax_category_id,
        public float $tax_percent,
        public string $unit_code = 'EA',
        public ?string $item_description = null,
        public ?string $hs_code = null,
        public ?string $item_category = null,
        public ?string $item_standard_id = null,
        public float $price_base_quantity = 1.0,
        public string $tax_scheme_id = 'VAT',
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
