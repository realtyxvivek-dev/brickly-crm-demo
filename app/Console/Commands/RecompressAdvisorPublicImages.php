<?php

namespace App\Console\Commands;

use App\Models\AdvisorPublicGalleryItem;
use App\Models\AdvisorPublicProfile;
use App\Services\AdvisorPublicImageOptimizer;
use Illuminate\Console\Command;

class RecompressAdvisorPublicImages extends Command
{
    protected $signature = 'advisor:recompress-public-images {--dry-run : Preview changes without writing files}';

    protected $description = 'Recompress advisor public profile and gallery images to optimized WebP assets';

    public function __construct(private AdvisorPublicImageOptimizer $optimizer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $counts = [
            'processed' => 0,
            'optimized' => 0,
            'already_optimized' => 0,
            'missing' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $dryRun = (bool) $this->option('dry-run');

        AdvisorPublicProfile::query()->with('user')->chunkById(100, function ($profiles) use (&$counts, $dryRun) {
            foreach ($profiles as $profile) {
                $user = $profile->user;
                if (!$user?->profile_picture) {
                    $counts['skipped']++;
                    continue;
                }

                $counts['processed']++;
                $result = $this->optimizer->recompressStoredPath($user->profile_picture, 'advisor-profile-pictures', $dryRun);
                $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;

                if (($result['status'] ?? null) === 'optimized' && !$dryRun && !empty($result['path'])) {
                    $user->update(['profile_picture' => $result['path']]);
                }
            }
        });

        AdvisorPublicGalleryItem::query()->chunkById(100, function ($items) use (&$counts, $dryRun) {
            foreach ($items as $item) {
                $counts['processed']++;
                $result = $this->optimizer->recompressStoredPath($item->image_path, 'advisor-gallery', $dryRun);
                $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;

                if (($result['status'] ?? null) === 'optimized' && !$dryRun && !empty($result['path'])) {
                    $item->update(['image_path' => $result['path']]);
                }
            }
        });

        $this->info('Advisor public image recompression complete.');
        foreach ($counts as $label => $count) {
            $this->line($label . ': ' . $count);
        }

        return self::SUCCESS;
    }
}
