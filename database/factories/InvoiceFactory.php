<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'customer_id' => Customer::factory(),
            'created_by' => User::factory(),
            'invoice_number' => 'INV-'.fake()->unique()->numerify('#######'),
            'status' => InvoiceStatus::DRAFT,
            'payment_status' => PaymentStatus::PENDING,
            'invoice_type_code' => '380',
            'invoice_kind' => 'B2B',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'document_currency_code' => 'NGN',
            'tax_currency_code' => 'NGN',
            'line_extension_amount' => 10000,
            'tax_exclusive_amount' => 10000,
            'tax_inclusive_amount' => 10750,
            'allowance_total_amount' => 0,
            'charge_total_amount' => 0,
            'prepaid_amount' => 0,
            'payable_rounding_amount' => 0,
            'payable_amount' => 10750,
        ];
    }
}
