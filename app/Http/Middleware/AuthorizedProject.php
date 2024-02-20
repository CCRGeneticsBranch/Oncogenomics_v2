<?php

namespace App\Http\Middleware;

use App\Models\User;
use Log,Closure,View;

class AuthorizedProject 
{
    public function handle($request, Closure $next)
    {
        $logged_user = User::getCurrentUser();
        if ($logged_user == null)
            return Redirect::to('/');
        $project_id = $request->route('project_id');
        if (!User::hasProject($project_id)) {
            return response()->view('pages/error', ['message' => "Project $project_id not found or unauthorized"]);
        }
 
        return $next($request);
    }
}
