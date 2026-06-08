<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('seller_snapshot')->nullable()->after('irn');
            $table->json('buyer_snapshot')->nullable()->after('seller_snapshot');
            $table->json('line_snapshot')->nullable()->after('buyer_snapshot');
            $table->json('tax_snapshot')->nullable()->after('line_snapshot');
            $table->string('pdf_hash', 64)->nullable()->after('tax_snapshot');
            $table->string('xml_hash', 64)->nullable()->after('pdf_hash');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'seller_snapshot',
                'buyer_snapshot',
                'line_snapshot',
                'tax_snapshot',
                'pdf_hash',
                'xml_hash',
            ]);
        });
    }
};
