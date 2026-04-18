<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('hs_code')->nullable();
            $table->text('description')->nullable();
            $table->decimal('unit_price', 15, 2);
            $table->string('unit_code', 10)->default('EA');
            $table->string('tax_category', 2)->default('S');
            $table->decimal('tax_rate', 5, 2)->default(7.5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
