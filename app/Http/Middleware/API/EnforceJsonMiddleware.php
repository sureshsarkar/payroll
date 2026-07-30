<?php

namespace App\Http\Middleware\API;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceJsonMiddleware {
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response {
        if (!$request->isJson() && $request->header('Accept') !== 'application/json') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only JSON requests are allowed.',
            ], 415);
        }
        return $next($request);
    }
}
