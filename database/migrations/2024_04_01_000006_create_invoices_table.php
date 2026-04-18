<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Core NRS Fields
            $table->string('invoice_number');
            $table->string('irn', 100)->nullable()->unique()->comment('Invoice Reference Number from NRS');
            $table->string('status')->default('draft');
            $table->string('payment_status')->default('PENDING');
            
            // UBL Basic Info
            $table->string('invoice_type_code', 10)->default('380');
            $table->date('issue_date');
            $table->time('issue_time')->nullable();
            $table->date('due_date')->nullable();
            $table->string('document_currency_code', 3)->default('NGN');
            $table->string('tax_currency_code', 3)->nullable();
            $table->text('note')->nullable();
            $table->date('tax_point_date')->nullable();
            $table->string('accounting_cost')->nullable();
            $table->string('buyer_reference')->nullable();
            $table->string('order_reference')->nullable();
            
            // Delivery
            $table->date('actual_delivery_date')->nullable();
            $table->date('delivery_period_start')->nullable();
            $table->date('delivery_period_end')->nullable();
            
            // Payment Terms
            $table->text('payment_terms_note')->nullable();
            
            // Legal Monetary Totals
            $table->decimal('line_extension_amount', 15, 2);
            $table->decimal('tax_exclusive_amount', 15, 2);
            $table->decimal('tax_inclusive_amount', 15, 2);
            $table->decimal('allowance_total_amount', 15, 2)->default(0);
            $table->decimal('charge_total_amount', 15, 2)->default(0);
            $table->decimal('prepaid_amount', 15, 2)->default(0);
            $table->decimal('payable_rounding_amount', 15, 2)->default(0);
            $table->decimal('payable_amount', 15, 2);
            
            $table->timestamps();

            // Compound unique for organization and invoice_number
            $table->unique(['organization_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
