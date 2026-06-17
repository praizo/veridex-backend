<?php

namespace App\Providers;

use App\Events\AccountSecurityAlertRequested;
use App\Events\InvoicePaymentStatusUpdated;
use App\Events\OtpRequested;
use App\Events\PlatformInvoiceUpdated;
use App\Events\PlatformOrganizationUpdated;
use App\Events\PlatformUserUpdated;
use App\Events\TeamMemberAdded;
use App\Events\TeamMemberRemoved;
use App\Events\TeamMemberRoleChanged;
use App\Listeners\SendAccountSecurityAlert;
use App\Listeners\SendOtpEmail;
use App\Listeners\SendTeamInvitationEmail;
use App\Listeners\SendTeamMemberRemovedEmail;
use App\Listeners\SendTeamRoleChangedEmail;
use App\Listeners\WriteInvoicePaymentActivityLog;
use App\Listeners\WritePlatformActivityLog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        foreach ([
            'viewPlatformDashboard',
            'managePlatformOrganizations',
            'managePlatformUsers',
            'managePlatformInvoices',
            'viewPlatformActivityLogs',
            'viewPulse',
            'viewTelescope',
        ] as $ability) {
            Gate::define($ability, fn ($user) => $user?->isSuperAdmin() === true);
        }

        // OTP email dispatch (async via queued listener)
        Event::listen(OtpRequested::class, SendOtpEmail::class);

        // Account security alerts (async via queued listener)
        Event::listen(AccountSecurityAlertRequested::class, SendAccountSecurityAlert::class);

        // Team invitation email dispatch (async via queued listener)
        Event::listen(TeamMemberAdded::class, SendTeamInvitationEmail::class);
        Event::listen(TeamMemberRoleChanged::class, SendTeamRoleChangedEmail::class);
        Event::listen(TeamMemberRemoved::class, SendTeamMemberRemovedEmail::class);

        Event::listen(InvoicePaymentStatusUpdated::class, WriteInvoicePaymentActivityLog::class);
        Event::listen(PlatformOrganizationUpdated::class, WritePlatformActivityLog::class);
        Event::listen(PlatformUserUpdated::class, WritePlatformActivityLog::class);
        Event::listen(PlatformInvoiceUpdated::class, WritePlatformActivityLog::class);
    }
}
