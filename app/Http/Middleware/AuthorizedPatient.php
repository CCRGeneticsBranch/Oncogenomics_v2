<?php

namespace App\Http\Middleware;

use App\Models\User;
use Log,Closure,View;

class AuthorizedPatient 
{
    public function handle($request, Closure $next)
    {    
        $patient_id = $request->route('patient_id');
        if (!User::hasPatient($patient_id)) {
            return response()->view('pages/error', ['message' => "Patient $patient_id not found or unauthorized"]);
        }
 
        return $next($request);
    }
}
