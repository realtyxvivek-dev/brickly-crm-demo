<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\LeadReopenService;
use Illuminate\Http\Request;

class LeadReopenController extends Controller
{
    public function __invoke(Request $request, Lead $lead, LeadReopenService $service)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $data = $request->validate(['assigned_to' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:2000'], 'version' => ['required', 'string', 'max:64']]);
        $service->reopen($lead->id, $request->user(), $data);
        return response()->json(['success' => true, 'message' => 'Lead reopened as New.']);
    }
}
