<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('role')->index();
            $table->string('status')->default('active')->index();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (Schema::hasColumn('users', 'platform_role')) {
            DB::table('users')
                ->whereNotNull('platform_role')
                ->orderBy('id')
                ->select(['id', 'platform_role', 'created_at', 'updated_at'])
                ->chunkById(100, function ($users) {
                    foreach ($users as $user) {
                        DB::table('platform_admins')->updateOrInsert(
                            ['user_id' => $user->id],
                            [
                                'role' => $user->platform_role,
                                'status' => 'active',
                                'created_at' => $user->created_at ?? now(),
                                'updated_at' => $user->updated_at ?? now(),
                            ]
                        );
                    }
                });

            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['platform_role']);
                $table->dropColumn('platform_role');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'platform_role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('platform_role')->nullable()->after('onboarding_completed_at')->index();
            });
        }

        DB::table('platform_admins')
            ->where('status', 'active')
            ->orderBy('id')
            ->select(['id', 'user_id', 'role'])
            ->chunkById(100, function ($admins) {
                foreach ($admins as $admin) {
                    DB::table('users')
                        ->where('id', $admin->user_id)
                        ->update(['platform_role' => $admin->role]);
                }
            });

        Schema::dropIfExists('platform_admins');
    }
};
