<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockHeavyRoutesDuringReliefMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!file_exists(storage_path('framework/crm-relief-mode'))) {
            return $next($request);
        }

        $payload = [
            'message' => 'This section is temporarily paused while server load normalizes. Please try again later.',
            'relief_mode' => true,
            'retry_after_seconds' => 900,
        ];

        if ($request->expectsJson() || $request->is('api/*') || $request->is('admin/dashboard/data')) {
            return response()
                ->json($payload, 503)
                ->header('Retry-After', '900')
                ->header('Cache-Control', 'no-store');
        }

        return response(
            '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Temporarily Limited</title><style>body{font-family:Arial,sans-serif;background:#f6faf7;color:#0b2118;margin:0;display:grid;min-height:100vh;place-items:center}.box{max-width:520px;background:#fff;border:1px solid #d8e5dd;border-radius:14px;padding:28px;box-shadow:0 18px 50px rgba(13,45,32,.08)}h1{font-size:24px;margin:0 0 10px}p{line-height:1.55;color:#4b5f55}.pill{display:inline-block;background:#e6f4ec;color:#075936;border-radius:999px;padding:7px 12px;font-weight:700;font-size:12px;margin-bottom:14px}</style></head><body><main class="box"><div class="pill">Temporary relief mode</div><h1>Try again later</h1><p>This dashboard/report section is paused while server load normalizes. Please use login and lightweight CRM pages for now.</p><p>Retry after 15 minutes.</p></main></body></html>',
            503,
            [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Retry-After' => '900',
                'Cache-Control' => 'no-store',
            ]
        );
    }
}
