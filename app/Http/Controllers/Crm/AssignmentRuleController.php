<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\AssignmentRule;
use App\Services\LeadAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssignmentRuleController extends Controller
{
    protected $assignmentService;

    public function __construct(LeadAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    public function index()
    {
        $rules = AssignmentRule::with(['ruleUsers.user', 'specificUser', 'creator', 'googleSheetsConfigs'])
            ->latest()
            ->get();

        $users = \App\Models\User::where('is_active', true)
            ->whereHas('role', function($q) {
                $q->whereIn('slug', ['sales_manager', 'sales_executive']);
            })
            ->get();

        return view('crm.automation.rules', compact('rules', 'users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:specific_user,percentage',
            'specific_user_id' => 'required_if:type,specific_user|exists:users,id',
            'users' => 'required_if:type,percentage|array',
            'users.*.user_id' => 'required|exists:users,id',
            'users.*.percentage' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $rule = AssignmentRule::create([
                'name' => $request->name,
                'type' => $request->type,
                'created_by' => $request->user()->id,
                'specific_user_id' => $request->type === 'specific_user' ? $request->specific_user_id : null,
                'description' => $request->description,
                'is_active' => true,
            ]);

            if ($request->type === 'percentage') {
                $userPercentages = $request->users;
                
                if (!$this->assignmentService->validatePercentageRule($userPercentages)) {
                    DB::rollBack();
                    return back()->withErrors(['users' => 'Percentages must sum to exactly 100%.']);
                }

                foreach ($userPercentages as $userData) {
                    $rule->ruleUsers()->create([
                        'user_id' => $userData['user_id'],
                        'percentage' => $userData['percentage'],
                    ]);
                }
            }

            DB::commit();

            return redirect()
                ->route('crm.automation.rules')
                ->with('success', 'Assignment rule created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create rule: ' . $e->getMessage()]);
        }
    }

    public function destroy(AssignmentRule $rule)
    {
        if ($rule->importBatches()->count() > 0) {
            return back()->withErrors(['error' => 'Cannot delete rule that has been used in imports.']);
        }

        $rule->delete();

        return redirect()
            ->route('crm.automation.rules')
            ->with('success', 'Assignment rule deleted successfully.');
    }
}

