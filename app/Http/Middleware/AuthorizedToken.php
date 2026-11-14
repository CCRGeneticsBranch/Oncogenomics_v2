<?php

namespace App\Http\Middleware;

use App\Services\SiteTokenAuthenticator;
use Closure;
use Illuminate\Http\Request;

class AuthorizedToken
{
    public function handle(Request $request, Closure $next)
    {
        if (!SiteTokenAuthenticator::valid(SiteTokenAuthenticator::fromRequest($request))) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }
        return $next($request);
    }
}
