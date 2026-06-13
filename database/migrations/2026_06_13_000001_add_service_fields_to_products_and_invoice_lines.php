<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('item_type', 20)->default('goods')->after('organization_id');
            $table->string('isic_code')->nullable()->after('item_category');
            $table->string('service_category', 1000)->nullable()->after('isic_code');
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('item_type', 20)->default('goods')->after('line_id');
            $table->string('isic_code')->nullable()->after('item_category');
            $table->string('service_category', 1000)->nullable()->after('isic_code');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['item_type', 'isic_code', 'service_category']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['item_type', 'isic_code', 'service_category']);
        });
    }
};
