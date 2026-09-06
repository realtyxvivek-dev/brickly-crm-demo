<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InterestedProjectName;
use Illuminate\Http\Request;

class InterestedProjectNameController extends Controller
{
    /**
     * Get all active interested project names.
     */
    public function index()
    {
        $projects = InterestedProjectName::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }
}
