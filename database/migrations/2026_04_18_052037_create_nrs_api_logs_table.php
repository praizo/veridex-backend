<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nrs_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('irn')->nullable()->index();
            $table->string('endpoint');
            $table->string('method', 10);
            $table->json('request_payload')->nullable();
            $table->json('response_body')->nullable();
            $table->integer('status_code')->index();
            $table->decimal('latency_ms', 10, 2)->nullable();
            $table->string('error_message')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nrs_api_logs');
    }
};
