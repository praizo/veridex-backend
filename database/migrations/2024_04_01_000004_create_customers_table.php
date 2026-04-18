<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('business'); // business, individual, government
            $table->string('tin')->nullable();
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->string('street_name')->nullable();
            $table->string('city_name')->nullable();
            $table->string('postal_zone', 20)->nullable();
            $table->string('country_code', 2)->default('NG');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
