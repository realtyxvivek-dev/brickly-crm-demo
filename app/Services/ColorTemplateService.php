<?php

namespace App\Services;

class ColorTemplateService
{
    /**
     * Get all available color templates.
     */
    public static function getAllTemplates(): array
    {
        return [
            'royal_green' => [
                'name' => 'Royal Green',
                'display_name' => 'Royal Green',
                'gradient_start' => '#063A1C',
                'gradient_middle' => '#205A44',
                'gradient_end' => '#15803d',
                'primary_color' => '#205A44',
                'secondary_color' => '#063A1C',
                'accent_color' => '#15803d',
                'description' => 'Professional green gradient perfect for real estate and business',
            ],
            'royal_blue' => [
                'name' => 'Royal Blue',
                'display_name' => 'Royal Blue',
                'gradient_start' => '#1e3a8a',
                'gradient_middle' => '#3b82f6',
                'gradient_end' => '#60a5fa',
                'primary_color' => '#3b82f6',
                'secondary_color' => '#1e3a8a',
                'accent_color' => '#60a5fa',
                'description' => 'Trustworthy blue gradient for corporate environments',
            ],
            'golden' => [
                'name' => 'Golden',
                'display_name' => 'Golden',
                'gradient_start' => '#92400e',
                'gradient_middle' => '#f59e0b',
                'gradient_end' => '#fbbf24',
                'primary_color' => '#f59e0b',
                'secondary_color' => '#92400e',
                'accent_color' => '#fbbf24',
                'description' => 'Luxurious golden gradient for premium brands',
            ],
            'royal_red' => [
                'name' => 'Royal Red',
                'display_name' => 'Royal Red',
                'gradient_start' => '#7f1d1d',
                'gradient_middle' => '#dc2626',
                'gradient_end' => '#ef4444',
                'primary_color' => '#dc2626',
                'secondary_color' => '#7f1d1d',
                'accent_color' => '#ef4444',
                'description' => 'Bold red gradient for energetic brands',
            ],
            'ocean_blue' => [
                'name' => 'Ocean Blue',
                'display_name' => 'Ocean Blue',
                'gradient_start' => '#0c4a6e',
                'gradient_middle' => '#0284c7',
                'gradient_end' => '#38bdf8',
                'primary_color' => '#0284c7',
                'secondary_color' => '#0c4a6e',
                'accent_color' => '#38bdf8',
                'description' => 'Calm ocean blue gradient for serene brands',
            ],
            'sunset_orange' => [
                'name' => 'Sunset Orange',
                'display_name' => 'Sunset Orange',
                'gradient_start' => '#9a3412',
                'gradient_middle' => '#ea580c',
                'gradient_end' => '#fb923c',
                'primary_color' => '#ea580c',
                'secondary_color' => '#9a3412',
                'accent_color' => '#fb923c',
                'description' => 'Warm sunset orange gradient for creative brands',
            ],
            'purple_royal' => [
                'name' => 'Purple Royal',
                'display_name' => 'Purple Royal',
                'gradient_start' => '#581c87',
                'gradient_middle' => '#9333ea',
                'gradient_end' => '#a855f7',
                'primary_color' => '#9333ea',
                'secondary_color' => '#581c87',
                'accent_color' => '#a855f7',
                'description' => 'Regal purple gradient for premium services',
            ],
            'emerald_green' => [
                'name' => 'Emerald Green',
                'display_name' => 'Emerald Green',
                'gradient_start' => '#064e3b',
                'gradient_middle' => '#10b981',
                'gradient_end' => '#34d399',
                'primary_color' => '#10b981',
                'secondary_color' => '#064e3b',
                'accent_color' => '#34d399',
                'description' => 'Fresh emerald green gradient for growth brands',
            ],
            'crimson_red' => [
                'name' => 'Crimson Red',
                'display_name' => 'Crimson Red',
                'gradient_start' => '#991b1b',
                'gradient_middle' => '#e11d48',
                'gradient_end' => '#f43f5e',
                'primary_color' => '#e11d48',
                'secondary_color' => '#991b1b',
                'accent_color' => '#f43f5e',
                'description' => 'Vibrant crimson red gradient for dynamic brands',
            ],
            'midnight_blue' => [
                'name' => 'Midnight Blue',
                'display_name' => 'Midnight Blue',
                'gradient_start' => '#1e1b4b',
                'gradient_middle' => '#4338ca',
                'gradient_end' => '#6366f1',
                'primary_color' => '#4338ca',
                'secondary_color' => '#1e1b4b',
                'accent_color' => '#6366f1',
                'description' => 'Deep midnight blue gradient for professional brands',
            ],
        ];
    }

    /**
     * Get a specific template by name.
     */
    public static function getTemplate(string $templateName): ?array
    {
        $templates = self::getAllTemplates();
        return $templates[$templateName] ?? null;
    }

    /**
     * Get gradient CSS for a template.
     */
    public static function getGradient(string $templateName, string $direction = 'to-r'): ?string
    {
        $template = self::getTemplate($templateName);
        if (!$template) {
            return null;
        }

        $directionMap = [
            'to-r' => 'linear-gradient(to right, %s, %s, %s)',
            'to-b' => 'linear-gradient(to bottom, %s, %s, %s)',
            'to-br' => 'linear-gradient(to bottom right, %s, %s, %s)',
            '135deg' => 'linear-gradient(135deg, %s, %s, %s)',
        ];

        $format = $directionMap[$direction] ?? $directionMap['to-r'];
        return sprintf(
            $format,
            $template['gradient_start'],
            $template['gradient_middle'],
            $template['gradient_end']
        );
    }

    /**
     * Apply a template and return color values.
     */
    public static function applyTemplate(string $templateName): array
    {
        $template = self::getTemplate($templateName);
        if (!$template) {
            return [];
        }

        return [
            'color_template' => $templateName,
            'primary_color' => $template['primary_color'],
            'secondary_color' => $template['secondary_color'],
            'accent_color' => $template['accent_color'],
            'gradient_start' => $template['gradient_start'],
            'gradient_end' => $template['gradient_end'],
            'use_gradient' => true,
        ];
    }

    /**
     * Get gradient CSS string for Tailwind classes.
     */
    public static function getTailwindGradient(string $templateName): string
    {
        $template = self::getTemplate($templateName);
        if (!$template) {
            return '';
        }

        return sprintf(
            'bg-gradient-to-r from-[%s] via-[%s] to-[%s]',
            $template['gradient_start'],
            $template['gradient_middle'],
            $template['gradient_end']
        );
    }

    /**
     * Get hover gradient CSS string for Tailwind classes.
     */
    public static function getTailwindHoverGradient(string $templateName): string
    {
        $template = self::getTemplate($templateName);
        if (!$template) {
            return '';
        }

        return sprintf(
            'hover:from-[%s] hover:via-[%s] hover:to-[%s]',
            $template['gradient_middle'],
            $template['gradient_end'],
            $template['gradient_end']
        );
    }
}
