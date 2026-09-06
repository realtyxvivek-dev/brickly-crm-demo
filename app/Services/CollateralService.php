<?php

namespace App\Services;

use App\Models\ProjectCollateral;
use App\Models\Project;

class CollateralService
{
    /**
     * Add collateral to project.
     */
    public function addCollateral(Project $project, array $data): ProjectCollateral
    {
        $collateral = $project->collaterals()->create($data);

        // Auto-detect link type
        $collateral->detectLinkType();
        $collateral->save();

        // If price sheet and marked as latest, unmark others
        if ($data['category'] === 'price_sheet' && isset($data['is_latest']) && $data['is_latest']) {
            $this->markLatestPriceSheet($project, $collateral);
        }

        return $collateral;
    }

    /**
     * Update collateral.
     */
    public function updateCollateral(ProjectCollateral $collateral, array $data): ProjectCollateral
    {
        $collateral->update($data);

        // Re-detect link type if link changed
        if (isset($data['link'])) {
            $collateral->detectLinkType();
            $collateral->save();
        }

        // If price sheet and marked as latest, unmark others
        if ($data['category'] === 'price_sheet' && isset($data['is_latest']) && $data['is_latest']) {
            $this->markLatestPriceSheet($collateral->project, $collateral);
        }

        return $collateral->fresh();
    }

    /**
     * Detect link type from URL.
     */
    public function detectLinkType(string $url): ?string
    {
        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
            return 'youtube';
        } elseif (str_contains($url, 'drive.google.com')) {
            return 'google_drive';
        }

        return null;
    }

    /**
     * Validate link (YouTube or Drive).
     */
    public function validateLink(string $url): bool
    {
        $linkType = $this->detectLinkType($url);

        if (!$linkType) {
            return false;
        }

        // Additional validation
        if ($linkType === 'youtube') {
            return $this->validateYouTubeLink($url);
        } elseif ($linkType === 'google_drive') {
            return $this->validateDriveLink($url);
        }

        return false;
    }

    /**
     * Validate YouTube link.
     */
    public function validateYouTubeLink(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false &&
               (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be'));
    }

    /**
     * Validate Google Drive link.
     */
    public function validateDriveLink(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false &&
               str_contains($url, 'drive.google.com');
    }

    /**
     * Mark price sheet as latest (unmark others).
     */
    public function markLatestPriceSheet(Project $project, ProjectCollateral $collateral): void
    {
        // Unmark all other price sheets
        $project->collaterals()
            ->where('category', 'price_sheet')
            ->where('id', '!=', $collateral->id)
            ->update(['is_latest' => false]);

        // Mark this one as latest
        $collateral->update(['is_latest' => true]);
    }

    /**
     * Get collaterals by category.
     */
    public function getByCategory(Project $project, string $category)
    {
        return $project->collaterals()->where('category', $category)->get();
    }

    /**
     * Generate button data for collaterals.
     */
    public function generateButtonData(Project $project): array
    {
        $collaterals = $project->collaterals()->get();
        $buttons = [];

        foreach ($collaterals as $collateral) {
            $category = $collateral->category;

            if (!isset($buttons[$category])) {
                $buttons[$category] = [
                    'icon' => $collateral->getCategoryIcon(),
                    'category' => $category,
                    'items' => [],
                    'count' => 0,
                    'has_latest' => false,
                ];
            }

            $buttons[$category]['items'][] = [
                'id' => $collateral->id,
                'title' => $collateral->title,
                'link' => $collateral->link,
                'link_type' => $collateral->link_type,
                'is_latest' => $collateral->is_latest,
            ];

            $buttons[$category]['count']++;
            if ($collateral->is_latest) {
                $buttons[$category]['has_latest'] = true;
            }
        }

        return $buttons;
    }
}
