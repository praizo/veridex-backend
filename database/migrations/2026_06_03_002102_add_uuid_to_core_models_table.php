<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    protected array $tables = ['users', 'organizations', 'customers', 'products', 'invoices'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'uuid')) {
                Schema::table($table, function (Blueprint $tableBlueprint) {
                    $tableBlueprint->uuid('uuid')->nullable();
                });
            }

            DB::table($table)->whereNull('uuid')->orderBy('id')->chunk(100, function ($records) use ($table) {
                foreach ($records as $record) {
                    DB::table($table)->where('id', $record->id)->update(['uuid' => (string) Str::uuid()]);
                }
            });

            Schema::table($table, function (Blueprint $tableBlueprint) {
                $tableBlueprint->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'uuid')) {
                Schema::table($table, function (Blueprint $tableBlueprint) {
                    $tableBlueprint->dropColumn('uuid');
                });
            }
        }
    }
};
