<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\BlacklistedNumber;
use Illuminate\Http\Request;

class BlacklistController extends Controller
{
    public function index()
    {
        $blacklisted = BlacklistedNumber::with('blacklistedBy')
            ->latest('blacklisted_at')
            ->get();

        return response()->json($blacklisted);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
            'reason' => 'required|string',
        ]);

        $blacklisted = BlacklistedNumber::create([
            'phone' => $validated['phone'],
            'reason' => $validated['reason'],
            'blacklisted_by' => auth()->id(),
            'blacklisted_at' => now(),
        ]);

        return response()->json($blacklisted->load('blacklistedBy'), 201);
    }

    public function destroy($id)
    {
        $blacklisted = BlacklistedNumber::findOrFail($id);
        $blacklisted->delete();

        return response()->json(['message' => 'Blacklisted number removed successfully']);
    }
}
