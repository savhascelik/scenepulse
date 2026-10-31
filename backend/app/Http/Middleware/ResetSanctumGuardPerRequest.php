<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevents Laravel's RequestGuard ('sanctum') from leaking a previously resolved
 * user across multiple requests within the same process (e.g., Octane or multi-actor tests)
 * when semi-public endpoints inspect $request->user('sanctum').
 */
class ResetSanctumGuardPerRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::forgetGuards();

        return $next($request);
    }
}
