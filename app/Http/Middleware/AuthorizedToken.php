<?php

namespace App\Http\Middleware;

use App\Models\User;
use Log,Closure,View;

class AuthorizedToken
{
    public function handle($request, Closure $next)
    {    
        /*
        $token = $request->route('token');
        if ($token == null)
            return '{"status":"token required"}';

        if ($token != Config::get("site.token"))
            return '{"status":"invalid token"}';
        */
        return $next($request);
    }
}
