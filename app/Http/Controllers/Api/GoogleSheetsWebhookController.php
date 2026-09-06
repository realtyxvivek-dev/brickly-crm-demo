<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoogleSheetsConfig;
use App\Models\GoogleSheetsRequestLog;
use App\Services\GoogleSheetsLeadIntakeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleSheetsWebhookController extends Controller
{
    public function __construct(
        private readonly GoogleSheetsLeadIntakeService $leadIntakeService,
    ) {
    }

    public function receive(Request $request, string $integration)
    {
        $config = GoogleSheetsConfig::with('columnMappings')
            ->where('inbound_slug', $integration)
            ->first();

        if (!$config) {
            Log::warning('Google Sheets webhook hit with unknown integration slug', [
                'integration' => $integration,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Integration not found.',
            ], 404);
        }

        $requestId = (string) Str::uuid();
        $providedKey = trim((string) $request->header('X-API-Key', ''));

        if ($providedKey === '' || !$config->inbound_api_key || !hash_equals((string) $config->inbound_api_key, $providedKey)) {
            GoogleSheetsRequestLog::create([
                'google_sheets_config_id' => $config->id,
                'request_id' => $requestId,
                'request_ip' => $request->ip(),
                'raw_payload' => $request->all(),
                'status' => 'auth_failed',
                'is_test' => false,
                'error_message' => 'Invalid API key.',
                'response_payload' => [
                    'status' => 'error',
                    'message' => 'Invalid API key.',
                ],
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid API key.',
                'request_id' => $requestId,
            ], 401);
        }

        $result = $this->leadIntakeService->handle($config, $request->all());

        GoogleSheetsRequestLog::create([
            'google_sheets_config_id' => $config->id,
            'request_id' => $requestId,
            'request_ip' => $request->ip(),
            'raw_payload' => $request->all(),
            'status' => $this->normalizeLogStatus($result),
            'is_test' => false,
            'error_message' => $result['status'] === 'error' ? ($result['message'] ?? 'Processing failed.') : null,
            'lead_id' => $result['lead_id'] ?? null,
            'response_payload' => $result,
        ]);

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'lead_id' => $result['lead_id'],
            'duplicate' => $result['duplicate'],
            'assigned_to' => $result['assigned_to'],
            'request_id' => $requestId,
            'errors' => $result['errors'] ?? null,
        ], $result['http_status']);
    }

    private function normalizeLogStatus(array $result): string
    {
        if (($result['status'] ?? null) === 'ok' && ($result['duplicate'] ?? false)) {
            return 'duplicate';
        }

        if (($result['status'] ?? null) === 'ok') {
            return 'success';
        }

        if (($result['http_status'] ?? 500) === 410) {
            return 'inactive';
        }

        if (($result['http_status'] ?? 500) === 422) {
            return 'validation_failed';
        }

        return 'processing_failed';
    }
}
