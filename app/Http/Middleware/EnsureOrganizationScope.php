<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationScope
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            if (! $user->hasCompletedOnboarding()) {
                return response()->json(['message' => 'Onboarding not completed. Please set up your business first.'], 403);
            }

            if ($orgId = $user->currentOrganizationId()) {
                $this->tenant->set($orgId);
                $request->attributes->set('organization_id', $orgId);
            } else {
                return response()->json(['message' => 'No active organization'], 403);
            }
        }

        try {
            return $next($request);
        } finally {
            $this->tenant->clear();
        }
    }
}
