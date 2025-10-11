<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;

class CacheMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, $ttl = 300)
    {
        // فقط برای GET requests
        if ($request->isMethod('get')) {
            $cacheKey = 'api_' . md5($request->fullUrl());
            
            // بررسی cache
            if (Cache::has($cacheKey)) {
                $cachedResponse = Cache::get($cacheKey);
                return Response::json($cachedResponse['data'])
                    ->header('X-Cache', 'HIT')
                    ->header('X-Cache-TTL', $ttl);
            }
            
            // اجرای request
            $response = $next($request);
            
            // ذخیره در cache
            if ($response->getStatusCode() === 200) {
                $responseData = json_decode($response->getContent(), true);
                Cache::put($cacheKey, ['data' => $responseData], $ttl);
                $response->header('X-Cache', 'MISS');
            }
            
            return $response;
        }
        
        return $next($request);
    }
}
