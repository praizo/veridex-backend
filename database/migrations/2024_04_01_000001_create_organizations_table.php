<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->uuid('nrs_business_id')->nullable()->unique()->comment('From NRS Entity Registration');
            $table->string('tin', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->string('street_name')->nullable();
            $table->string('city_name')->nullable();
            $table->string('postal_zone', 20)->nullable();
            $table->string('country_code', 2)->default('NG');
            $table->text('business_description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
