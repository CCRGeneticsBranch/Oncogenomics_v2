<?php

namespace App\Http\Middleware;

use App\Models\User;
use Log,Closure,View;

class AuthorizedProject 
{
    public function handle($request, Closure $next)
    {
        $logged_user = User::getCurrentUser();
        if ($logged_user == null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your session has expired. Please sign in again.'], 401);
            }
            return redirect('/');
        }
        $project_id = $request->route('project_id');
        if (!User::hasProject($project_id)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => "Project $project_id not found or unauthorized"], 403);
            }
            return response()->view('pages/error', ['message' => "Project $project_id not found or unauthorized"]);
        }
 
        return $next($request);
    }
}
