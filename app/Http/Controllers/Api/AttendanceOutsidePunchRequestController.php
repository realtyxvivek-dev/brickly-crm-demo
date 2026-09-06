<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AttendanceAccessService;
use App\Services\AttendanceOutsidePunchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AttendanceOutsidePunchRequestController extends Controller
{
    public function __construct(
        protected AttendanceAccessService $attendanceAccessService,
        protected AttendanceOutsidePunchService $outsidePunchService
    ) {
    }

    public function store(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        $payload = $request->all();
        foreach (['latitude', 'longitude', 'office_location_id', 'geo_distance_meters'] as $key) {
            if (($payload[$key] ?? null) === '' || ($payload[$key] ?? null) === 'null' || ($payload[$key] ?? null) === 'undefined') {
                $payload[$key] = null;
            }
        }

        $validator = Validator::make($payload, [
            'punch_type' => 'required|string|in:in,out',
            'requested_at' => 'nullable|date',
            'client_timezone' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'office_location_id' => 'nullable|exists:office_locations,id',
            'geo_distance_meters' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first() ?: 'Outside punch request data is invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $outsideRequest = $this->outsidePunchService->createRequest($request->user(), $validator->validated());
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => collect($exception->errors())->flatten()->first() ?: $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Outside punch request submitted.',
            'data' => $outsideRequest,
        ], 201);
    }
}
