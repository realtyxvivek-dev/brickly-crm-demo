<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    public function index()
    {
        // Automation section now only shows assignment rules overview
        // All lead import functionality has been moved to Lead Import section
        return view('crm.automation.index');
    }
}

