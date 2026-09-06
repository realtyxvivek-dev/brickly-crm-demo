<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\SiteVisit;
use Illuminate\Http\Request;

class DeadLeadsController extends Controller
{
    /**
     * Show dead leads page
     */
    public function index(Request $request)
    {
        $user = $request->user();
        // Create API token for the authenticated user to use in frontend
        $token = $user->createToken('crm-web-token')->plainTextToken;
        return view('admin.dead-leads', ['api_token' => $token]);
    }
}

