<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        // Belum login
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Role tidak diizinkan
        if (!in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'Forbidden',
                'required_role' => $roles,
                'your_role' => $user->role,
            ], 403);
        }

        return $next($request);
    }
}
