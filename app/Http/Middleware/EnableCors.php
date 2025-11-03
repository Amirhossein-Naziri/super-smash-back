<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnableCors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight OPTIONS requests first
        if ($request->getMethod() === 'OPTIONS') {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With, Origin')
                ->header('Access-Control-Max-Age', '86400');
        }

        // Continue with the request
        $response = $next($request);

        // Add CORS headers to all responses
        return $response
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With, Origin')
            ->header('Access-Control-Max-Age', '86400');
    }
}

