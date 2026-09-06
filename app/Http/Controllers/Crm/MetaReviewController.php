<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\MetaReviewSaveService;
use App\Services\MetaReviewStageService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetaReviewController extends Controller
{
    public function index(Request $request, MetaReviewStageService $stageService)
    {
        $leads = Lead::query()
            ->with([
                'latestFbLead.form:id,form_name',
                'activeAssignments' => function ($query) {
                    $query->with('assignedTo:id,name');
                },
                'metaStageUpdatedBy:id,name',
            ])
            ->where('source', Lead::normalizeSource('meta'))
            ->whereHas('latestFbLead', function ($query) {
                $query->whereNotNull('leadgen_id')->where('leadgen_id', '!=', '');
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('crm.meta-review', [
            'leads' => $leads,
            'stageOptions' => $stageService->labels(),
        ]);
    }

    public function update(Request $request, Lead $lead, MetaReviewSaveService $saveService)
    {
        $request->validate([
            'meta_stage' => 'required|string|max:100',
            'meta_review_note' => 'nullable|string|max:5000',
        ]);

        DB::beginTransaction();

        try {
            $saveService->saveFromLeadRequirements(
                $lead,
                $request->user(),
                $request->input('meta_stage'),
                $request->input('meta_review_note')
            );

            DB::commit();

            return back()->with('success', 'Meta Review updated successfully.');
        } catch (AuthorizationException $e) {
            DB::rollBack();

            return back()->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to update Meta Review: ' . $e->getMessage());
        }
    }
}
