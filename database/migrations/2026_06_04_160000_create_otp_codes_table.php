<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('code', 6);
            $table->enum('type', ['registration', 'login']);
            $table->json('payload')->nullable(); // Stores registration data for deferred user creation
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'type', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
