<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoogleSheetsConfig;
use App\Services\GoogleSheetsLeadIntakeService;
use Illuminate\Http\Request;

class GoogleSheetsLeadController extends Controller
{
    public function __construct(private readonly GoogleSheetsLeadIntakeService $leadIntakeService)
    {
    }

    /**
     * Store a new lead from Google Apps Script
     */
    public function store(Request $request)
    {
        $sheetId = GoogleSheetsConfig::extractSheetId((string) $request->input('sheet_id', ''));
        $config = GoogleSheetsConfig::with('columnMappings')
            ->where('sheet_id', $sheetId)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            return response()->json([
                'status' => 'error',
                'message' => 'Google Sheet configuration not found or inactive.',
            ], 404);
        }

        $result = $this->leadIntakeService->handle($config, $request->all());

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'lead_id' => $result['lead_id'],
            'duplicate' => $result['duplicate'],
            'assigned_to' => $result['assigned_to'],
            'errors' => $result['errors'] ?? null,
        ], $result['http_status']);
    }
}
