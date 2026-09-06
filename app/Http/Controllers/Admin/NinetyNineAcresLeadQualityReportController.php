<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NinetyNineAcresLeadQualityReportController extends Controller
{
    public function __invoke(Request $request)
    {
        return redirect()->route('data-intelligence.lead-quality', array_merge($request->query(), [
            'source' => $request->query('source', '99acres'),
        ]));
    }
}
