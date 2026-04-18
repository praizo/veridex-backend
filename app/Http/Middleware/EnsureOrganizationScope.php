<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Organization;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Product;

class EnsureOrganizationScope
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            if ($orgId = $user->currentOrganizationId()) {
                // Add global scopes to Models using standard macros or just rely on traits
                // For simplicity in Phase 1, we will apply this mainly at the controller level
                // but setting a request attribute helps too
                $request->attributes->set('organization_id', $orgId);
            } else {
                return response()->json(['message' => 'No active organization'], 403);
            }
        }

        return $next($request);
    }
}
