<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE products MODIFY tax_category VARCHAR(64) NOT NULL DEFAULT "STANDARD_VAT"');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE products MODIFY tax_category VARCHAR(2) NOT NULL DEFAULT "S"');
    }
};
