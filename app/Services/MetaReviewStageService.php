<?php

namespace App\Services;

class MetaReviewStageService
{
    private const FALLBACK_STAGES = [
        'intake',
        'qualified',
        'in_progress',
        'converted',
        'lost',
        'not_qualified',
    ];

    public function options(): array
    {
        $form = app(DynamicFormService::class)->getPublishedFormByLocation('lead-detail.requirements');
        $field = $form?->fields?->firstWhere('field_key', 'meta_stage');

        $options = is_array($field?->options) ? $field->options : self::FALLBACK_STAGES;

        return collect(array_merge($options, self::FALLBACK_STAGES))
            ->map(fn ($option) => trim((string) (is_array($option) ? ($option['value'] ?? $option['label'] ?? '') : $option)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isValid(?string $stage): bool
    {
        $value = trim((string) $stage);
        if ($value === '') {
            return false;
        }

        return in_array($value, $this->options(), true);
    }

    public function labels(): array
    {
        return array_map(function (string $value) {
            return [
                'value' => $value,
                'label' => str_replace('_', ' ', ucwords($value, '_')),
            ];
        }, $this->options());
    }
}
