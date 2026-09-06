<?php

namespace App\Http\Middleware;

use App\Services\PhonePrivacyService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MaskCustomerPhoneResponses
{
    public function __construct(private readonly PhonePrivacyService $privacy)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        if (!$this->privacy->shouldMask($user) || $this->isAuditedRevealRoute($request)) {
            return $response;
        }

        if ($response instanceof JsonResponse) {
            $decoded = $response->getData(true);
            if (is_array($decoded)) {
                $response->setData($this->privacy->maskPhoneFields($decoded, $user));
            }

            return $response;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type'));
        if ($contentType === '' || str_contains($contentType, 'text/') || str_contains($contentType, 'javascript')) {
            $response->setContent((string) $this->privacy->maskText($response->getContent(), $user));
        }

        return $response;
    }

    private function isAuditedRevealRoute(Request $request): bool
    {
        return $request->routeIs([
            'api.mcube.outbound-call.fallback',
            'api.leads.whatsapp-direct',
        ]);
    }
}
