<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('payload');
            $table->unsignedTinyInteger('max_attempts')->default(5)->after('attempts');
            $table->timestamp('consumed_at')->nullable()->after('verified_at');
        });

        DB::table('otp_codes')
            ->where('type', 'registration')
            ->update(['payload' => null]);
    }

    public function down(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'max_attempts', 'consumed_at']);
        });
    }
};
