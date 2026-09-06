<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\CompanyFile;
use App\Models\ActivityLog;
use App\Services\CompanySettingsService;
use App\Services\ColorTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CompanySettingsController extends Controller
{
    protected $settingsService;

    public function __construct(CompanySettingsService $settingsService)
    {
        $this->middleware('auth');
        $this->settingsService = $settingsService;
    }

    /**
     * Display settings page.
     */
    public function index()
    {
        $user = auth()->user();
        
        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        $companyProfile = $this->settingsService->getCompanyProfile();
        $brandingSettings = $this->settingsService->getBrandingSettings();
        $companyFiles = CompanyFile::orderBy('file_type')->orderBy('created_at', 'desc')->get();

        return view('admin.company-settings.index', compact('companyProfile', 'brandingSettings', 'companyFiles'));
    }

    /**
     * Update company profile settings.
     */
    public function updateCompanyProfile(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'phone' => 'required|string|regex:/^[0-9+\-\s()]+$/',
            'landline' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
            'website' => 'nullable|url|max:255',
            'gst_number' => 'nullable|string|regex:/^[0-9A-Z]{15}$/',
            'pan_number' => 'nullable|string|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
            'registration_number' => 'nullable|string|max:100',
        ]);

        $oldValues = [];
        $newValues = [];

        foreach ($validated as $key => $value) {
            $oldSetting = CompanySetting::where('setting_key', $key)->first();
            $oldValues[$key] = $oldSetting ? $oldSetting->value : null;
            $newValues[$key] = $value;
            
            CompanySetting::set($key, $value, $user->id);
        }

        // Log activity
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'company_profile_updated',
            'model_type' => 'CompanySetting',
            'model_id' => null,
            'description' => 'Company profile settings updated',
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->settingsService->clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Company profile updated successfully',
        ]);
    }

    /**
     * Update branding settings.
     */
    public function updateBranding(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            // Convert checkbox value to boolean before validation
            // Checkbox sends '1' when checked, nothing or '0' when unchecked
            $useGradient = $request->has('use_gradient') && 
                        $request->input('use_gradient') !== '0' && 
                        $request->input('use_gradient') !== false &&
                        $request->input('use_gradient') !== null;
            $request->merge(['use_gradient' => (bool) $useGradient]);

            $validated = $request->validate([
            'color_template' => 'nullable|string|in:royal_green,royal_blue,golden,royal_red,ocean_blue,sunset_orange,purple_royal,emerald_green,crimson_red,midnight_blue,custom',
            'primary_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'background_color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'text_color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'link_color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'gradient_start' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'gradient_end' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'use_gradient' => 'required|boolean',
            'email_header_template' => 'nullable|string',
            'email_footer_template' => 'nullable|string',
            'email_signature_template' => 'nullable|string',
            'custom_css' => 'nullable|string',
        ]);

        $oldValues = [];
        $newValues = [];

        foreach ($validated as $key => $value) {
            $oldSetting = CompanySetting::where('setting_key', $key)->first();
            $oldValues[$key] = $oldSetting ? $oldSetting->value : null;
            $newValues[$key] = $value;
            
            CompanySetting::set($key, $value, $user->id);
        }

        // Log activity
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'branding_updated',
            'model_type' => 'CompanySetting',
            'model_id' => null,
            'description' => 'Branding settings updated',
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

            $this->settingsService->clearCache();
            
            // Clear view cache to ensure new CSS variables are loaded
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            \Illuminate\Support\Facades\Artisan::call('cache:clear');

            return response()->json([
                'success' => true,
                'message' => 'Branding settings updated successfully. Page will reload to apply changes.',
                'reload' => true, // Signal frontend to reload
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Branding update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage() ?: 'An error occurred while updating branding settings',
            ], 500);
        }
    }

    /**
     * Upload company file.
     */
    public function uploadFile(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'file' => 'required|file',
            'file_type' => 'required|in:logo,favicon,email_header,email_footer',
        ]);

        $file = $request->file('file');
        $fileType = $validated['file_type'];

        // Additional validation based on file type
        $rules = [];
        switch ($fileType) {
            case 'logo':
                $rules = ['image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048', 'dimensions:max_width=2000,max_height=2000'];
                break;
            case 'favicon':
                $rules = ['image', 'mimes:ico,png', 'max:512', 'dimensions:width=32,height=32'];
                break;
            case 'email_header':
            case 'email_footer':
                $rules = ['image', 'mimes:jpeg,png,jpg,gif,svg', 'max:1024'];
                break;
        }

        $validator = Validator::make(['file' => $file], ['file' => $rules]);
        if ($validator->fails()) {
            return response()->json([
                'error' => 'File validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $companyFile = CompanyFile::uploadFile($file, $fileType, $user->id);

            // If logo is uploaded, also create/update favicon with same file
            if ($fileType === 'logo') {
                // Deactivate old favicon
                CompanyFile::where('file_type', 'favicon')
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
                
                // Create favicon record pointing to same file
                CompanyFile::create([
                    'file_type' => 'favicon',
                    'file_path' => $companyFile->file_path, // Same file
                    'file_name' => $companyFile->file_name,
                    'file_size' => $companyFile->file_size,
                    'mime_type' => $companyFile->mime_type,
                    'dimensions' => $companyFile->dimensions,
                    'is_active' => true,
                    'uploaded_by' => $user->id,
                ]);
            }

            // Log activity
            ActivityLog::create([
                'user_id' => $user->id,
                'action' => 'file_uploaded',
                'model_type' => 'CompanyFile',
                'model_id' => $companyFile->id,
                'description' => "Uploaded {$fileType} file: {$companyFile->file_name}" . ($fileType === 'logo' ? ' (also applied as favicon)' : ''),
                'old_values' => null,
                'new_values' => [
                    'file_type' => $fileType,
                    'file_name' => $companyFile->file_name,
                    'file_path' => $companyFile->file_path,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => $fileType === 'logo' ? 'Logo uploaded successfully and applied as favicon' : 'File uploaded successfully',
                'file' => [
                    'id' => $companyFile->id,
                    'url' => $companyFile->url,
                    'file_name' => $companyFile->file_name,
                    'file_type' => $companyFile->file_type,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('File upload error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to upload file',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete company file.
     */
    public function deleteFile($id)
    {
        $user = auth()->user();
        
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $file = CompanyFile::findOrFail($id);

        try {
            // Delete from storage
            if (Storage::disk('public')->exists($file->file_path)) {
                Storage::disk('public')->delete($file->file_path);
            }

            // Log activity
            ActivityLog::create([
                'user_id' => $user->id,
                'action' => 'file_deleted',
                'model_type' => 'CompanyFile',
                'model_id' => $file->id,
                'description' => "Deleted {$file->file_type} file: {$file->file_name}",
                'old_values' => [
                    'file_type' => $file->file_type,
                    'file_name' => $file->file_name,
                    'file_path' => $file->file_path,
                ],
                'new_values' => null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $file->delete();

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('File delete error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to delete file',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get settings API endpoint.
     */
    public function getSettings()
    {
        $user = auth()->user();
        
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'company_profile' => $this->settingsService->getCompanyProfile(),
            'branding' => $this->settingsService->getBrandingSettings(),
        ]);
    }

    /**
     * Apply color template.
     */
    public function applyTemplate(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'template' => 'required|string|in:royal_green,royal_blue,golden,royal_red,ocean_blue,sunset_orange,purple_royal,emerald_green,crimson_red,midnight_blue',
        ]);

        $template = $validated['template'];
        
        // Get old values for logging
        $oldValues = [
            'color_template' => CompanySetting::get('color_template', 'royal_green'),
            'primary_color' => CompanySetting::get('primary_color', '#205A44'),
            'secondary_color' => CompanySetting::get('secondary_color', '#063A1C'),
            'accent_color' => CompanySetting::get('accent_color', '#15803d'),
            'gradient_start' => CompanySetting::get('gradient_start', '#063A1C'),
            'gradient_end' => CompanySetting::get('gradient_end', '#205A44'),
        ];

        // Apply template
        $this->settingsService->applyColorTemplate($template, $user->id);

        // Get new values
        $newValues = [
            'color_template' => CompanySetting::get('color_template', 'royal_green'),
            'primary_color' => CompanySetting::get('primary_color', '#205A44'),
            'secondary_color' => CompanySetting::get('secondary_color', '#063A1C'),
            'accent_color' => CompanySetting::get('accent_color', '#15803d'),
            'gradient_start' => CompanySetting::get('gradient_start', '#063A1C'),
            'gradient_end' => CompanySetting::get('gradient_end', '#205A44'),
        ];

        // Log activity
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'color_template_applied',
            'model_type' => 'CompanySetting',
            'model_id' => null,
            'description' => "Applied color template: {$template}",
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->settingsService->clearCache();
        
        // Clear view cache to ensure new CSS variables are loaded
        \Illuminate\Support\Facades\Artisan::call('view:clear');
        \Illuminate\Support\Facades\Artisan::call('cache:clear');

        return response()->json([
            'success' => true,
            'message' => 'Color template applied successfully. Page will reload to apply changes.',
            'template' => $template,
            'colors' => $newValues,
            'reload' => true, // Signal frontend to reload
        ]);
    }

    /**
     * Preview branding changes.
     */
    public function previewBranding()
    {
        $user = auth()->user();
        
        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        $branding = $this->settingsService->getBrandingSettings();
        
        return view('admin.company-settings.preview', compact('branding'));
    }
}
