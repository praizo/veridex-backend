<?php

namespace App\Providers;

use App\Events\PlatformInvoiceUpdated;
use App\Events\PlatformOrganizationUpdated;
use App\Events\PlatformUserUpdated;
use App\Listeners\WritePlatformActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Product;
use App\Policies\CustomerPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\ProductPolicy;
use App\Support\Tenancy\TenantContext;
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
        $this->app->scoped(TenantContext::class);

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
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        foreach (
            [
                'viewPlatformDashboard',
                'managePlatformOrganizations',
                'managePlatformUsers',
                'managePlatformInvoices',
                'viewPlatformActivityLogs',
                'viewPulse',
                'viewTelescope',
            ] as $ability
        ) {
            Gate::define($ability, fn ($user) => $user?->isSuperAdmin() === true);
        }

        // Typed listeners are auto-discovered by Laravel. Only broad object listeners
        // need explicit mapping because discovery cannot infer their event classes.
        Event::listen(PlatformOrganizationUpdated::class, WritePlatformActivityLog::class);
        Event::listen(PlatformUserUpdated::class, WritePlatformActivityLog::class);
        Event::listen(PlatformInvoiceUpdated::class, WritePlatformActivityLog::class);
    }
}
