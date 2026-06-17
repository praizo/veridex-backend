<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;

class AuthSessionService
{
    public function clear(Request $request): void
    {
        $accessToken = $request->user()?->currentAccessToken();
        if ($accessToken && method_exists($accessToken, 'delete')) {
            $accessToken->delete();
        }

        auth('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    public function login(Request $request, $user): void
    {
        auth('web')->login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
    }
}
