<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CrmDangerController extends Controller
{
    /**
     * Delete all leads (hard delete). Requires password from config.
     */
    public function deleteAllLeads(Request $request)
    {
        $request->validate(['password' => 'required|string']);

        $expected = config('crm.danger_delete_all_leads_password');
        if ($expected === '' || $expected !== $request->input('password')) {
            Log::warning('CRM delete-all-leads: invalid password attempt', [
                'user_id' => auth()->id(),
            ]);
            return response()->json(['message' => 'Invalid authorization.'], 403);
        }

        $count = Lead::withTrashed()->count();
        Lead::withTrashed()->forceDelete();

        Log::info('CRM delete-all-leads: all leads deleted', [
            'user_id' => auth()->id(),
            'deleted_count' => $count,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'All leads have been permanently deleted.',
            'deleted_count' => $count,
        ]);
    }
}
