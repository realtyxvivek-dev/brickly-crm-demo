<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\PabblyIntegrationSettings;
use App\Services\SourceAutomationService;
use App\Services\LeadIntakeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PabblyWebhookController extends Controller
{
    /**
     * Handle incoming webhook from Pabbly
     * 
     * Expected payload structure (flexible - will map common field names):
     * {
     *   "name": "John Doe",
     *   "phone": "1234567890",
     *   "email": "john@example.com",
     *   ... (any other custom fields)
     * }
     */
    public function store(Request $request)
    {
        // Check if integration is active
        $settings = PabblyIntegrationSettings::getSettings();
        
        if (!$settings->is_active) {
            Log::warning('Pabbly Webhook Rejected - Integration Disabled', [
                'ip' => $request->ip(),
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Pabbly integration is disabled',
            ], 403);
        }

        // Validate webhook secret if configured
        if ($settings->webhook_secret) {
            $providedSecret = $request->header('X-Pabbly-Secret') ?? $request->input('webhook_secret') ?? '';

            // Use hash_equals() for constant-time comparison to prevent timing attacks
            if (!hash_equals($settings->webhook_secret, $providedSecret)) {
                Log::warning('Pabbly Webhook Rejected - Invalid Secret', [
                    'ip' => $request->ip(),
                    'provided' => $providedSecret ? 'present' : 'missing',
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid webhook secret',
                ], 401);
            }
        }

        // Log incoming webhook — headers excluded to avoid leaking secrets
        Log::info('Pabbly Webhook Received', [
            'payload' => $request->except(['webhook_secret']),
            'ip' => $request->ip(),
        ]);

        try {
            // Map common field names from Pabbly payload
            $payload = $request->all();
            
            // Flexible field mapping - handle various field name formats
            $name = $this->getFieldValue($payload, ['name', 'customer_name', 'full_name', 'contact_name', 'Name', 'Customer Name']);
            $phone = $this->getFieldValue($payload, ['phone', 'mobile', 'phone_number', 'contact_number', 'Phone', 'Mobile']);
            $email = $this->getFieldValue($payload, ['email', 'email_address', 'Email', 'Email Address']);
            
            // Validate required fields
            $validator = Validator::make([
                'name' => $name,
                'phone' => $phone,
            ], [
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:255',
            ]);

            if ($validator->fails()) {
                Log::warning('Pabbly Webhook Validation Failed', [
                    'errors' => $validator->errors(),
                    'payload' => $payload,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Prepare lead data
            $leadData = [
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'source' => Lead::normalizeSource('pabbly'),
                'status' => 'new',
                'created_by' => 1, // System user or default admin - adjust as needed
            ];

            // Map optional fields if present
            $optionalFields = [
                'address' => ['address', 'street_address', 'Address'],
                'city' => ['city', 'City'],
                'state' => ['state', 'State'],
                'pincode' => ['pincode', 'pin_code', 'postal_code', 'zip', 'Pincode', 'Zip'],
                'property_type' => ['property_type', 'Property Type'],
                'budget_min' => ['budget_min', 'budget_minimum', 'min_budget'],
                'budget_max' => ['budget_max', 'budget_maximum', 'max_budget'],
                'requirements' => ['requirements', 'requirement', 'message', 'notes', 'Requirements', 'Message'],
                'notes' => ['notes', 'note', 'additional_notes', 'Notes'],
            ];

            foreach ($optionalFields as $dbField => $possibleKeys) {
                $value = $this->getFieldValue($payload, $possibleKeys);
                if ($value !== null) {
                    $leadData[$dbField] = $value;
                }
            }

            // Handle property_type enum validation
            if (isset($leadData['property_type'])) {
                $validPropertyTypes = ['apartment', 'villa', 'plot', 'commercial', 'other'];
                if (!in_array(strtolower($leadData['property_type']), $validPropertyTypes)) {
                    $leadData['property_type'] = 'other';
                } else {
                    $leadData['property_type'] = strtolower($leadData['property_type']);
                }
            }

            // Convert budget values to numeric if they're strings
            if (isset($leadData['budget_min']) && is_string($leadData['budget_min'])) {
                $leadData['budget_min'] = $this->parseNumericValue($leadData['budget_min']);
            }
            if (isset($leadData['budget_max']) && is_string($leadData['budget_max'])) {
                $leadData['budget_max'] = $this->parseNumericValue($leadData['budget_max']);
            }

            $intake = app(LeadIntakeService::class)->createOrReenquire($leadData, 'pabbly', 1);
            $lead = $intake['lead'];

            // Auto-assign via Automation Rule (if configured)
            try {
                if ($intake['was_created']) {
                    app(SourceAutomationService::class)->assignFromSource($lead, 'pabbly');
                }
            } catch (\Throwable $e) {
                Log::warning('PabblyWebhook: automation assign failed', [
                    'lead_id' => $lead->id,
                    'error'   => $e->getMessage(),
                ]);
            }

            // Record webhook call in settings
            PabblyIntegrationSettings::recordWebhook();

            Log::info('Pabbly Webhook Lead Created Successfully', [
                'lead_id' => $lead->id,
                'name' => $lead->name,
                'phone' => $lead->phone,
            ]);

            return response()->json([
                'status' => 'ok',
                'message' => $intake['was_created'] ? 'Lead created successfully' : 'Existing lead updated as re-enquiry',
                'lead_id' => $lead->id,
                'lead' => [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'phone' => $lead->phone,
                    'email' => $lead->email,
                    'source' => $lead->source,
                ],
                'duplicate' => $intake['was_duplicate'],
            ], $intake['was_created'] ? 201 : 200);

        } catch (\Exception $e) {
            Log::error('Pabbly Webhook Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process webhook. Please try again later.',
            ], 500);
        }
    }

    /**
     * Get field value from payload using multiple possible keys
     */
    private function getFieldValue(array $payload, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            if (isset($payload[$key]) && !empty($payload[$key])) {
                return (string) $payload[$key];
            }
        }
        return null;
    }

    /**
     * Parse numeric value from string (handles currency symbols, commas, etc.)
     */
    private function parseNumericValue($value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        // Remove currency symbols, commas, spaces
        $cleaned = preg_replace('/[₹$,\s]/', '', (string) $value);
        
        // Extract numbers
        if (preg_match('/(\d+\.?\d*)/', $cleaned, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }
}
