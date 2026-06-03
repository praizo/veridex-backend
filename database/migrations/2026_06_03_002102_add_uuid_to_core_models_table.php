<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected array $tables = ['users', 'organizations', 'customers', 'products', 'invoices'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasColumn($table, 'uuid')) {
                Schema::table($table, function (Blueprint $tableBlueprint) {
                    $tableBlueprint->uuid('uuid')->nullable();
                });
            }
            
            \Illuminate\Support\Facades\DB::table($table)->whereNull('uuid')->orderBy('id')->chunk(100, function ($records) use ($table) {
                foreach ($records as $record) {
                    \Illuminate\Support\Facades\DB::table($table)->where('id', $record->id)->update(['uuid' => (string) \Illuminate\Support\Str::uuid()]);
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
