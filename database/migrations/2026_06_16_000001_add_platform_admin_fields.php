<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('platform_role')->nullable()->after('onboarding_completed_at')->index();
            $table->timestamp('suspended_at')->nullable()->after('platform_role');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('platform_status')->default('active')->after('service_id')->index();
            $table->string('onboarding_status')->default('pending')->after('platform_status')->index();
            $table->timestamp('verified_at')->nullable()->after('onboarding_status');
            $table->timestamp('suspended_at')->nullable()->after('verified_at');
            $table->text('admin_notes')->nullable()->after('suspended_at');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->unsignedBigInteger('organization_id')->nullable()->change();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->unsignedBigInteger('organization_id')->nullable(false)->change();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'platform_status',
                'onboarding_status',
                'verified_at',
                'suspended_at',
                'admin_notes',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['platform_role', 'suspended_at']);
        });
    }
};
