<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role_id !== 3) { // 3 = Admin
            return response()->json([
                'message' => 'Unauthorized: Only admins can perform this action.'
            ], 403);
        }

        return $next($request);
    }
}