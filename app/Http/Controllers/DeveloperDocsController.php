<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DeveloperDocsController extends Controller
{
    /**
     * Show API documentation page. Only accessible with correct access key in URL.
     */
    public function show(Request $request, string $accessKey)
    {
        $expectedKey = config('developer_docs.access_key');
        if ($accessKey !== $expectedKey) {
            abort(404);
        }

        $baseUrl = $request->getSchemeAndHttpHost() . '/api';
        return view('developer.docs', compact('baseUrl'));
    }
}
