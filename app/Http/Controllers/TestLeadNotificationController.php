<?php

namespace App\Http\Controllers;

use App\Events\LeadAssigned;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Support\TestLeadNameGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestLeadNotificationController extends Controller
{
    /**
     * Show the 1-click test page for lead-assigned notification.
     */
    public function index()
    {
        return view('test.lead-notification');
    }

    /**
     * Simulate a lead assigned to the current user (for testing popup + email).
     */
    public function simulate(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $lead = null;
        try {
            DB::beginTransaction();
            $lead = Lead::create([
                'name' => TestLeadNameGenerator::make(),
                'phone' => '9999999999',
                'email' => 'test-lead-'.time().'@example.com',
                'status' => 'new',
                'source' => 'other',
                'created_by' => $user->id,
            ]);

            LeadAssignment::create([
                'lead_id' => $lead->id,
                'assigned_to' => $user->id,
                'assigned_by' => $user->id,
                'assignment_type' => 'primary',
                'assignment_method' => 'manual',
                'is_active' => true,
                'assigned_at' => now(),
            ]);

            DB::commit();

            $viewUrl = ($user->isTelecaller() || $user->isSalesExecutive())
                ? route('telecaller.tasks').'?status=pending'
                : route('leads.index');
            $popupData = [
                'name' => $lead->name,
                'phone' => $lead->phone ?? '',
                'viewUrl' => $viewUrl,
            ];

            // Broadcast event (optional; if Pusher fails, we still show popup on next page via flash)
            try {
                event(new LeadAssigned($lead, $user->id, $user->id));
            } catch (\Throwable $e) {
                report($e);
            }

            // Always redirect with success and popup data so the popup shows on this page (works without Pusher)
            return redirect()->route('test.lead-notification')
                ->with('success', 'Done! Lead created and assigned. Check your email (and spam folder). The popup should appear below.')
                ->with('lead_just_assigned', $popupData);
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->route('test.lead-notification')
                ->with('error', 'Simulation failed: ' . $e->getMessage());
        }
    }
}
