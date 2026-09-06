<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Log only for authenticated users and specific actions
        if (auth()->check() && $this->shouldLog($request)) {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => $this->getAction($request),
                'model_type' => $this->getModelType($request),
                'model_id' => $this->getModelId($request),
                'description' => $this->getDescription($request),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        $methods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        return in_array($request->method(), $methods);
    }

    private function getAction(Request $request): string
    {
        return match($request->method()) {
            'POST' => 'created',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default => 'accessed',
        };
    }

    private function getModelType(Request $request): string
    {
        $path = $request->path();
        if (str_contains($path, 'leads')) return 'Lead';
        if (str_contains($path, 'users')) return 'User';
        if (str_contains($path, 'site-visits')) return 'SiteVisit';
        if (str_contains($path, 'follow-ups')) return 'FollowUp';
        return 'Unknown';
    }

    private function getModelId(Request $request): ?int
    {
        return $request->route('id') ?? $request->route('lead') ?? $request->route('user') ?? null;
    }

    private function getDescription(Request $request): string
    {
        $action = $this->getAction($request);
        $model = $this->getModelType($request);
        return ucfirst($action) . " {$model}";
    }
}

