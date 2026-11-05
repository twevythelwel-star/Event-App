<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogSlowRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = hrtime(true);
        $response = $next($request);
        $elapsedMs = (int) round((hrtime(true) - $started) / 1_000_000);
        $threshold = (int) config('app.slow_request_threshold_ms', 2000);
        if ($elapsedMs >= $threshold) {
            Log::warning('http.slow_request', ['method' => $request->method(), 'path' => '/'.$request->path(),
                'status' => $response->getStatusCode(), 'duration_ms' => $elapsedMs]);
        }
        return $response;
    }
}
