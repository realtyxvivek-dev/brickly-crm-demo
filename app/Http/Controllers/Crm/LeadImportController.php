<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Services\LeadImportService;
use App\Models\AssignmentRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeadImportController extends Controller
{
    protected $importService;

    public function __construct(LeadImportService $importService)
    {
        $this->importService = $importService;
    }

    public function showImportForm()
    {
        $rules = AssignmentRule::where('is_active', true)
            ->with(['ruleUsers.user', 'specificUser'])
            ->latest()
            ->get();

        return view('crm.automation.import', compact('rules'));
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
            'assignment_rule_id' => 'required|exists:assignment_rules,id',
        ]);

        try {
            $file = $request->file('csv_file');
            
            // Parse CSV
            $leads = $this->importService->parseCsvFile($file);

            if (empty($leads)) {
                return back()->withErrors(['csv_file' => 'No valid leads found in CSV file.']);
            }

            // Store file
            $fileName = 'imports/' . time() . '_' . $file->getClientOriginalName();
            Storage::putFileAs('public', $file, $fileName);

            // Import leads
            $batch = $this->importService->importFromCsv(
                $leads,
                $request->user()->id,
                $request->assignment_rule_id
            );

            // Update batch with file name
            $batch->update(['file_name' => $fileName]);

            return redirect()
                ->route('crm.automation.index')
                ->with('success', "Successfully imported {$batch->imported_leads} leads. {$batch->failed_leads} failed.");

        } catch (\Exception $e) {
            return back()->withErrors(['csv_file' => $e->getMessage()]);
        }
    }

    public function previewCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        try {
            $file = $request->file('csv_file');
            $leads = $this->importService->parseCsvFile($file);

            return response()->json([
                'success' => true,
                'total' => count($leads),
                'preview' => array_slice($leads, 0, 10), // First 10 rows
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function importSheets(Request $request)
    {
        // Google Sheets integration will be implemented later
        return back()->withErrors(['sheets' => 'Google Sheets integration coming soon.']);
    }
}

