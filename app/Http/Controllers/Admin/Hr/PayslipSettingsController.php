<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\PayrollPayslipSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PayslipSettingsController extends Controller
{
    public function index(Request $request)
    {
        $settings = PayrollPayslipSetting::query()->firstOrCreate([], [
            'company_name' => config('app.name', 'Base CRM'),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $settings]);
        }

        return view('admin.hr.payslip-settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'payslip_prefix' => 'required|string|max:30',
            'company_name' => 'nullable|string|max:255',
            'header_text' => 'nullable|string|max:2000',
            'footer_text' => 'nullable|string|max:2000',
            'default_notes' => 'nullable|string|max:2000',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title' => 'nullable|string|max:255',
            'signatory_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'remove_signatory_image' => 'nullable|boolean',
        ]);

        $settings = PayrollPayslipSetting::query()->firstOrCreate([]);
        $payload = collect($validated)->except(['signatory_image', 'remove_signatory_image'])->all();

        if ($request->boolean('remove_signatory_image') && $settings->signatory_image_path) {
            Storage::disk('public')->delete($settings->signatory_image_path);
            $payload['signatory_image_path'] = null;
        }

        if ($request->hasFile('signatory_image')) {
            if ($settings->signatory_image_path) {
                Storage::disk('public')->delete($settings->signatory_image_path);
            }

            $payload['signatory_image_path'] = $request->file('signatory_image')->store('payslips/signatures', 'public');
        }

        $settings->update($payload);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $settings->fresh()]);
        }

        return back()->with('success', 'Payslip settings updated.');
    }
}
