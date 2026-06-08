<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_sequences') || ! Schema::hasColumn('invoice_sequences', 'next_val')) {
            return;
        }

        Schema::table('invoice_sequences', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_sequences', 'period')) {
                $table->string('period', 16)->default(now()->format('Y'))->after('prefix');
            }

            if (! Schema::hasColumn('invoice_sequences', 'next_number')) {
                $table->unsignedBigInteger('next_number')->default(1)->after('period');
            }
        });

        DB::table('invoice_sequences')->update([
            'period' => now()->format('Y'),
            'next_number' => DB::raw('next_val'),
        ]);

        try {
            Schema::table('invoice_sequences', function (Blueprint $table) {
                $table->dropUnique('invoice_sequences_organization_id_prefix_unique');
            });
        } catch (Throwable) {
            // The fresh schema already uses the period-aware unique key.
        }

        Schema::table('invoice_sequences', function (Blueprint $table) {
            $table->unique(['organization_id', 'prefix', 'period']);
            $table->dropColumn('next_val');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoice_sequences') || Schema::hasColumn('invoice_sequences', 'next_val')) {
            return;
        }

        Schema::table('invoice_sequences', function (Blueprint $table) {
            $table->bigInteger('next_val')->default(1)->after('prefix');
        });

        DB::table('invoice_sequences')->update([
            'next_val' => DB::raw('next_number'),
        ]);

        try {
            Schema::table('invoice_sequences', function (Blueprint $table) {
                $table->dropUnique('invoice_sequences_organization_id_prefix_period_unique');
            });
        } catch (Throwable) {
            // Ignore if the unique key name differs in a local database.
        }

        Schema::table('invoice_sequences', function (Blueprint $table) {
            $table->unique(['organization_id', 'prefix']);
            $table->dropColumn(['period', 'next_number']);
        });
    }
};
