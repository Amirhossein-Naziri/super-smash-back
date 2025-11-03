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
        try {
            // Only handle CORS for API requests
            $path = $request->path();
            if (!str_starts_with($path, 'api/') && $request->getMethod() !== 'OPTIONS') {
                // Skip CORS for non-API requests (except OPTIONS)
                return $next($request);
            }

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

            // Add CORS headers to all API responses
            if ($response instanceof Response) {
                return $response
                    ->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With, Origin')
                    ->header('Access-Control-Max-Age', '86400');
            }

            return $response;
        } catch (\Exception $e) {
            // Log error but don't break the request
            \Log::error('CORS Middleware Error: ' . $e->getMessage());
            
            // Try to continue anyway
            try {
                $response = $next($request);
                if ($response instanceof Response) {
                    return $response
                        ->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With, Origin')
                        ->header('Access-Control-Max-Age', '86400');
                }
                return $response;
            } catch (\Exception $e2) {
                \Log::error('CORS Middleware Critical Error: ' . $e2->getMessage());
                // Return error response with CORS headers
                return response()->json(['error' => 'Internal server error'], 500)
                    ->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With, Origin');
            }
        }
    }
}

