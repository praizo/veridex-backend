<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('tax_category_id', 50)->change();
        });

        Schema::table('invoice_tax_totals', function (Blueprint $table) {
            $table->string('tax_category_id', 50)->change();
        });

        Schema::table('invoice_allowance_charges', function (Blueprint $table) {
            $table->string('tax_category_id', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('tax_category_id', 10)->change();
        });

        Schema::table('invoice_tax_totals', function (Blueprint $table) {
            $table->string('tax_category_id', 10)->change();
        });

        Schema::table('invoice_allowance_charges', function (Blueprint $table) {
            $table->string('tax_category_id', 10)->nullable()->change();
        });
    }
};
