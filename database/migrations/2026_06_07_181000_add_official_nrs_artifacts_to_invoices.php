<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('official_pdf_path')->nullable()->after('pdf_hash');
            $table->string('official_pdf_hash', 64)->nullable()->after('official_pdf_path');
            $table->string('official_xml_path')->nullable()->after('xml_hash');
            $table->string('official_xml_hash', 64)->nullable()->after('official_xml_path');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'official_pdf_path',
                'official_pdf_hash',
                'official_xml_path',
                'official_xml_hash',
            ]);
        });
    }
};
