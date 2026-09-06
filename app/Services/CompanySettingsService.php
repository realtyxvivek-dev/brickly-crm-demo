<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\CompanyFile;
use Illuminate\Support\Facades\Cache;
use App\Services\ColorTemplateService;

class CompanySettingsService
{
    /**
     * Get all company profile settings.
     */
    public function getCompanyProfile(): array
    {
        return Cache::remember('company_profile_settings', 3600, function () {
            return CompanySetting::getGroupedByCategory('company_profile');
        });
    }

    /**
     * Get all branding settings.
     */
    public function getBrandingSettings(): array
    {
        return Cache::remember('branding_settings', 3600, function () {
            $settings = CompanySetting::getGroupedByCategory('branding');
            $settings['color_template'] = $this->getColorTemplate();
            $settings['gradient_colors'] = $this->getGradientColors();
            return $settings;
        });
    }

    /**
     * Update single setting.
     */
    public function updateSetting(string $key, $value, ?int $userId = null): CompanySetting
    {
        Cache::forget('company_profile_settings');
        Cache::forget('branding_settings');
        
        return CompanySetting::set($key, $value, $userId);
    }

    /**
     * Bulk update settings.
     */
    public function updateSettings(array $settings, ?int $userId = null): void
    {
        foreach ($settings as $key => $value) {
            CompanySetting::set($key, $value, $userId);
        }

        Cache::forget('company_profile_settings');
        Cache::forget('branding_settings');
    }

    /**
     * Get active company logo URL.
     */
    public function getCompanyLogo(): ?string
    {
        $file = CompanyFile::getActiveFile('logo');
        return $file ? $file->url : null;
    }

    /**
     * Get active favicon URL.
     */
    public function getFavicon(): ?string
    {
        $file = CompanyFile::getActiveFile('favicon');
        return $file ? $file->url : null;
    }

    /**
     * Get primary color with fallback.
     */
    public function getPrimaryColor(): string
    {
        return CompanySetting::get('primary_color', '#205A44');
    }

    /**
     * Get secondary color with fallback.
     */
    public function getSecondaryColor(): string
    {
        return CompanySetting::get('secondary_color', '#063A1C');
    }

    /**
     * Get custom CSS.
     */
    public function getCustomCss(): string
    {
        return CompanySetting::get('custom_css', '');
    }

    /**
     * Get company name.
     */
    public function getCompanyName(): string
    {
        return CompanySetting::get('company_name', 'Company Name');
    }

    /**
     * Get email header template.
     */
    public function getEmailHeaderTemplate(): string
    {
        return CompanySetting::get('email_header_template', '');
    }

    /**
     * Get email footer template.
     */
    public function getEmailFooterTemplate(): string
    {
        return CompanySetting::get('email_footer_template', '');
    }

    /**
     * Get email signature template.
     */
    public function getEmailSignatureTemplate(): string
    {
        return CompanySetting::get('email_signature_template', '');
    }

    /**
     * Get active color template.
     */
    public function getColorTemplate(): string
    {
        $template = CompanySetting::get('color_template', 'royal_green');
        // Ensure we always return a string, never null
        if (empty($template) || !is_string($template)) {
            return 'royal_green';
        }
        return $template;
    }

    /**
     * Get gradient colors (start and end).
     */
    public function getGradientColors(): array
    {
        $template = $this->getColorTemplate();
        $useGradient = CompanySetting::get('use_gradient', true);
        
        if (!$useGradient) {
            return [
                'start' => CompanySetting::get('primary_color', '#205A44'),
                'end' => CompanySetting::get('primary_color', '#205A44'),
            ];
        }

        $gradientStart = CompanySetting::get('gradient_start');
        $gradientEnd = CompanySetting::get('gradient_end');
        
        if ($gradientStart && $gradientEnd) {
            return [
                'start' => $gradientStart,
                'end' => $gradientEnd,
            ];
        }

        // Fallback to template
        $templateData = ColorTemplateService::getTemplate($template);
        if ($templateData) {
            return [
                'start' => $templateData['gradient_start'],
                'end' => $templateData['gradient_end'],
            ];
        }

        return [
            'start' => '#063A1C',
            'end' => '#205A44',
        ];
    }

    /**
     * Apply a color template.
     */
    public function applyColorTemplate(string $template, ?int $userId = null): void
    {
        $templateData = ColorTemplateService::applyTemplate($template);
        
        if (empty($templateData)) {
            return;
        }

        foreach ($templateData as $key => $value) {
            $this->updateSetting($key, $value, $userId);
        }
    }

    /**
     * Clear all settings cache.
     */
    public function clearCache(): void
    {
        Cache::forget('company_profile_settings');
        Cache::forget('branding_settings');
    }
}
