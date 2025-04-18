<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModeratorOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role_id, [2, 3])) { // 2 = Moderador, 3 = Admin
            return response()->json([
                'message' => 'Unauthorized: Only moderators and admins can perform this action.'
            ], 403);
        }

        return $next($request);
    }
}