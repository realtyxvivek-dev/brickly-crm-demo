<?php

if (!function_exists('company_name')) {
    /**
     * Get company name.
     */
    function company_name(): string
    {
        $name = \App\Models\CompanySetting::get('company_name', 'Company Name');
        return $name ?? 'Company Name';
    }
}

if (!function_exists('brand_name')) {
    /**
     * Get brand name used in page titles and labels.
     */
    function brand_name(): string
    {
        return company_name();
    }
}

if (!function_exists('company_logo')) {
    /**
     * Get company logo URL.
     */
    function company_logo(): ?string
    {
        $file = \App\Models\CompanyFile::getActiveFile('logo');
        return $file ? asset('storage/' . $file->file_path) : null;
    }
}

if (!function_exists('company_favicon')) {
    /**
     * Get favicon URL.
     */
    function company_favicon(): ?string
    {
        $file = \App\Models\CompanyFile::getActiveFile('favicon');
        return $file ? asset('storage/' . $file->file_path) : null;
    }
}

if (!function_exists('primary_color')) {
    /**
     * Get primary color.
     */
    function primary_color(): string
    {
        $color = \App\Models\CompanySetting::get('primary_color', '#205A44');
        return $color ?? '#205A44';
    }
}

if (!function_exists('secondary_color')) {
    /**
     * Get secondary color.
     */
    function secondary_color(): string
    {
        $color = \App\Models\CompanySetting::get('secondary_color', '#063A1C');
        return $color ?? '#063A1C';
    }
}

if (!function_exists('accent_color')) {
    /**
     * Get accent color.
     */
    function accent_color(): string
    {
        $color = \App\Models\CompanySetting::get('accent_color', '#15803d');
        return $color ?? '#15803d';
    }
}

if (!function_exists('text_color')) {
    /**
     * Get text color.
     */
    function text_color(): string
    {
        $color = \App\Models\CompanySetting::get('text_color', '#063A1C');
        return $color ?? '#063A1C';
    }
}

if (!function_exists('link_color')) {
    /**
     * Get link color.
     */
    function link_color(): string
    {
        $color = \App\Models\CompanySetting::get('link_color', '#205A44');
        return $color ?? '#205A44';
    }
}

if (!function_exists('background_color')) {
    /**
     * Get background color.
     */
    function background_color(): string
    {
        $color = \App\Models\CompanySetting::get('background_color', '#F7F6F3');
        return $color ?? '#F7F6F3';
    }
}

if (!function_exists('branded_css')) {
    /**
     * Get custom CSS.
     */
    function branded_css(): string
    {
        $css = \App\Models\CompanySetting::get('custom_css', '');
        return $css ?? '';
    }
}

if (!function_exists('company_setting')) {
    /**
     * Get company setting by key.
     */
    function company_setting(string $key, $default = null)
    {
        return \App\Models\CompanySetting::get($key, $default);
    }
}

if (!function_exists('gradient_start_color')) {
    /**
     * Get gradient start color.
     */
    function gradient_start_color(): string
    {
        $start = \App\Models\CompanySetting::get('gradient_start');
        if ($start && is_string($start)) {
            return $start;
        }
        
        $template = \App\Models\CompanySetting::get('color_template', 'royal_green');
        $template = $template ?? 'royal_green';
        $templateData = \App\Services\ColorTemplateService::getTemplate($template);
        return $templateData ? ($templateData['gradient_start'] ?? '#063A1C') : '#063A1C';
    }
}

if (!function_exists('gradient_end_color')) {
    /**
     * Get gradient end color.
     */
    function gradient_end_color(): string
    {
        $end = \App\Models\CompanySetting::get('gradient_end');
        if ($end && is_string($end)) {
            return $end;
        }
        
        $template = \App\Models\CompanySetting::get('color_template', 'royal_green');
        $template = $template ?? 'royal_green';
        $templateData = \App\Services\ColorTemplateService::getTemplate($template);
        return $templateData ? ($templateData['gradient_end'] ?? '#205A44') : '#205A44';
    }
}

if (!function_exists('button_gradient_css')) {
    /**
     * Get button gradient CSS.
     */
    function button_gradient_css(): string
    {
        $useGradient = use_gradient();
        if (!$useGradient) {
            $primary = primary_color();
            return "background-color: {$primary};";
        }
        
        $start = gradient_start_color();
        $end = gradient_end_color();
        return "background: linear-gradient(135deg, {$start}, {$end});";
    }
}

if (!function_exists('use_gradient')) {
    /**
     * Check if gradient should be used.
     */
    function use_gradient(): bool
    {
        return (bool) \App\Models\CompanySetting::get('use_gradient', true);
    }
}

if (!function_exists('color_template')) {
    /**
     * Get active color template name.
     */
    function color_template(): string
    {
        $template = \App\Models\CompanySetting::get('color_template', 'royal_green');
        return $template ?? 'royal_green';
    }
}
