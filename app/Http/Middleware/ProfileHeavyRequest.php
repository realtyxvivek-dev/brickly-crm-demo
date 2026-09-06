<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ProfileHeavyRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('performance_monitor.enabled', false)) {
            return $next($request);
        }

        $startedAt = microtime(true);
        $queries = [];
        $queryCount = 0;
        $queryTimeMs = 0.0;
        $slowQueryThresholdMs = (float) config('performance_monitor.slow_query_threshold_ms', 75);

        $dispatcher = app('events');
        $listener = function (QueryExecuted $event) use (&$queries, &$queryCount, &$queryTimeMs, $slowQueryThresholdMs): void {
            $queryCount++;
            $queryTimeMs += (float) $event->time;

            if ($event->time < $slowQueryThresholdMs) {
                return;
            }

            $queries[] = [
                'time_ms' => round((float) $event->time, 2),
                'sql' => Str::limit(preg_replace('/\s+/', ' ', $event->sql), 240),
            ];
        };

        $dispatcher->listen(QueryExecuted::class, $listener);

        try {
            /** @var Response $response */
            $response = $next($request);
        } finally {
            $durationMs = round((microtime(true) - $startedAt) * 1000, 2);

            $shouldLog = $durationMs >= (float) config('performance_monitor.request_threshold_ms', 800)
                || $queryCount >= (int) config('performance_monitor.query_count_threshold', 25)
                || $queryTimeMs >= (float) config('performance_monitor.query_time_threshold_ms', 250);

            if ($shouldLog) {
                Log::channel(config('performance_monitor.log_channel', 'performance'))->info('heavy_request_profile', [
                    'route_name' => optional($request->route())->getName(),
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'full_url' => $request->fullUrl(),
                    'user_id' => optional($request->user())->id,
                    'status_code' => isset($response) ? $response->getStatusCode() : null,
                    'duration_ms' => $durationMs,
                    'query_count' => $queryCount,
                    'query_time_ms' => round($queryTimeMs, 2),
                    'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
                    'slow_queries' => array_slice($queries, 0, (int) config('performance_monitor.max_logged_queries', 5)),
                ]);
            }
        }

        return $response;
    }
}
