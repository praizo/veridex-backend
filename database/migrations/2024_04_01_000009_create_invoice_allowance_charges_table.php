<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_allowance_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->boolean('charge_indicator')->default(false)->comment('true = charge, false = allowance');
            $table->string('reason_code', 10)->nullable();
            $table->string('reason_text')->nullable();
            $table->decimal('multiplier_factor_numeric', 10, 4)->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('base_amount', 15, 2)->nullable();
            $table->string('tax_category_id', 10)->nullable();
            $table->decimal('tax_percent', 5, 2)->nullable();
            $table->string('tax_scheme_id', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_allowance_charges');
    }
};
