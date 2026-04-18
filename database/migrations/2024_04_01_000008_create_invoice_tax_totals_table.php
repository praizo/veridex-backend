<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_tax_totals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('tax_amount', 15, 2);
            $table->decimal('taxable_amount', 15, 2)->nullable();
            $table->string('tax_category_id', 10)->default('S');
            $table->decimal('tax_percent', 5, 2)->default(7.5);
            $table->string('tax_scheme_id', 10)->default('VAT');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_tax_totals');
    }
};
