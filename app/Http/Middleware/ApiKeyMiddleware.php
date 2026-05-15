<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = config('app.api_key');

        if (!$apiKey) {
            return $next($request);
        }

        if ($request->header('X-API-KEY') !== $apiKey) {
            return response()->json([
                'message' => 'Unauthorized. Invalid API Key.'
            ], 401);
        }

        return $next($request);
    }
}
