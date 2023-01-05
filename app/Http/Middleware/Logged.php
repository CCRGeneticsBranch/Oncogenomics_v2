<?php

namespace App\Http\Middleware;

use App\Models\User;
use Log,Closure,View;

class Logged 
{
    public function handle($request, Closure $next)
    {    
        $logged_user = User::getCurrentUser();
        if ($logged_user == null) {
            //return redirect()->route('login');        
            return redirect('/login');
        }
        return $next($request);
    }    
}
