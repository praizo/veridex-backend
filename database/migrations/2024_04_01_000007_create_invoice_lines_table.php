<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('line_id');
            $table->decimal('invoiced_quantity', 15, 2);
            $table->string('unit_code', 10)->default('EA');
            $table->decimal('line_extension_amount', 15, 2);
            $table->string('item_name');
            $table->text('item_description')->nullable();
            $table->string('hs_code')->nullable();
            $table->string('item_category')->nullable();
            $table->string('item_standard_id')->nullable()->comment('E.g. HS Code');
            $table->decimal('price_amount', 15, 2);
            $table->decimal('price_base_quantity', 15, 2)->default(1);
            $table->string('tax_category_id', 10)->default('S');
            $table->decimal('tax_percent', 5, 2)->default(7.5);
            $table->string('tax_scheme_id', 10)->default('VAT');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
