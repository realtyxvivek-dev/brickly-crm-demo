<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'provider',
        'meta_waba_account_id',
        'template_id',
        'name',
        'content',
        'components',
        'raw_payload',
        'category',
        'language',
        'status',
        'rejection_reason',
        'is_active',
    ];

    public function metaWabaAccount()
    {
        return $this->belongsTo(MetaWabaAccount::class, 'meta_waba_account_id');
    }

    protected $casts = [
        'is_active' => 'boolean',
        'components' => 'array',
        'raw_payload' => 'array',
    ];

    public static function normalizeTemplatePayload(array $template): array
    {
        if (isset($template['templates']) && is_array($template['templates'])) {
            $template = $template['templates'];
        }

        $components = $template['components'] ?? [];
        if (is_string($components)) {
            $decoded = json_decode($components, true);
            $components = is_array($decoded) ? $decoded : [];
        }

        $template['components'] = $components;

        return $template;
    }

    public static function extractContent(array $template): string
    {
        $template = self::normalizeTemplatePayload($template);

        $content = $template['content']
            ?? $template['body']
            ?? $template['message']
            ?? data_get($template, 'components.0.text')
            ?? null;

        if (is_string($content) && trim($content) !== '') {
            return trim($content);
        }
        $components = $template['components'] ?? [];
        if (!is_array($components)) {
            return '';
        }

        foreach ($components as $component) {
            $type = strtolower((string) ($component['type'] ?? ''));
            if ($type !== 'body') {
                continue;
            }

            $text = $component['text'] ?? data_get($component, 'example.body_text.0.0');
            if (is_string($text) && trim($text) !== '') {
                return trim($text);
            }
        }

        return '';
    }

    /**
     * Sync templates from API
     */
    public static function syncFromAPI(array $templates): void
    {
        foreach ($templates as $template) {
            $template = self::normalizeTemplatePayload($template);

            self::updateOrCreate(
                ['template_id' => $template['id'] ?? $template['template_id']],
                [
                    'name' => $template['name'] ?? '',
                    'content' => self::extractContent($template),
                    'provider' => 'meta_waba',
                    'components' => $template['components'] ?? [],
                    'raw_payload' => $template,
                    'category' => $template['category'] ?? null,
                    'language' => $template['language'] ?? data_get($template, 'language.code') ?? 'en',
                    'status' => strtoupper((string) ($template['status'] ?? 'APPROVED')),
                    'rejection_reason' => data_get($template, 'rejected_reason'),
                    'is_active' => (($template['status'] ?? 'APPROVED') === 'APPROVED'),
                ]
            );
        }
    }

    /**
     * Get available active templates
     */
    public static function getAvailableTemplates()
    {
        return self::where('is_active', true)->orderBy('name')->get();
    }
}
