<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtCookieAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // List of public routes that don't need token verification
        $publicRoutes = [
            'api/login',
            'api/register',
        ];

        // Skip token check for public routes
        if (in_array($request->path(), $publicRoutes)) {
            return $next($request);
        }

        // Handle refresh token endpoint separately
        if ($request->path() === 'api/refresh') {
            if ($request->cookie('refresh_token')) {
                $request->headers->set('Authorization', 'Bearer ' . $request->cookie('refresh_token'));
                return $next($request);
            }
            return response()->json([
                'status' => 'error',
                'message' => 'Refresh token required'
            ], 401);
        }

        // For all other routes, require access token
        if ($request->cookie('access_token')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->cookie('access_token'));
            return $next($request);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Access token required'
        ], 401);
    }
}