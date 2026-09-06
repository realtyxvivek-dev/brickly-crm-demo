<?php

namespace App\Services;

use App\Models\DynamicForm;

class DynamicFormService
{
    /**
     * Get the latest linked form for a location path, preferring the active published record.
     */
    public function getLatestFormByLocation(string $locationPath): ?DynamicForm
    {
        return DynamicForm::where('location_path', $locationPath)
            ->orderByRaw("CASE WHEN status = 'published' AND is_active = 1 THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->with(['fields' => function($query) {
                $query->orderBy('order');
            }])
            ->first();
    }

    /**
     * Get a published dynamic form by location path
     *
     * @param string $locationPath
     * @return DynamicForm|null
     */
    public function getPublishedFormByLocation(string $locationPath): ?DynamicForm
    {
        return DynamicForm::where('location_path', $locationPath)
            ->where('status', 'published')
            ->where('is_active', true)
            ->orderByDesc('id')
            ->with(['fields' => function($query) {
                $query->orderBy('order');
            }])
            ->first();
    }

    /**
     * Check if a published dynamic form exists for a location path
     *
     * @param string $locationPath
     * @return bool
     */
    public function hasPublishedForm(string $locationPath): bool
    {
        return DynamicForm::where('location_path', $locationPath)
            ->where('status', 'published')
            ->where('is_active', true)
            ->exists();
    }

    public function findFormById(int $id): ?DynamicForm
    {
        return DynamicForm::with(['fields' => function ($query) {
            $query->orderBy('order');
        }])->find($id);
    }
}
