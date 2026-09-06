<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdvisorPublicImageOptimizer
{
    private const TARGET_BYTES = 256000;
    private const MAX_DIMENSION = 1600;
    private const QUALITY_STEPS = [86, 82, 78, 74, 70, 66, 62, 58, 54];

    public function storeOptimizedUpload(UploadedFile $file, string $directory): string
    {
        $optimized = $this->optimizeBinary(
            @file_get_contents($file->getRealPath()) ?: null,
            $file->getRealPath()
        );

        if (!$optimized) {
            return $file->store($directory, 'public');
        }

        $path = trim($directory, '/') . '/' . Str::uuid() . '.webp';
        Storage::disk('public')->put($path, $optimized['content']);

        return $path;
    }

    public function recompressStoredPath(?string $path, string $directory, bool $dryRun = false): array
    {
        $path = $this->normalizePath($path);

        if (!$path) {
            return ['status' => 'skipped', 'reason' => 'empty_path', 'path' => null];
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($path)) {
            return ['status' => 'missing', 'reason' => 'file_not_found', 'path' => $path];
        }

        $absolutePath = $disk->path($path);
        $raw = @file_get_contents($absolutePath) ?: null;
        if ($raw === null) {
            return ['status' => 'failed', 'reason' => 'read_failed', 'path' => $path];
        }

        $details = @getimagesize($absolutePath) ?: [null, null];
        $isAlreadyOptimized = Str::endsWith(Str::lower($path), '.webp')
            && filesize($absolutePath) <= self::TARGET_BYTES
            && ($details[0] ?? 0) <= self::MAX_DIMENSION
            && ($details[1] ?? 0) <= self::MAX_DIMENSION;

        if ($isAlreadyOptimized) {
            return ['status' => 'already_optimized', 'path' => $path];
        }

        $optimized = $this->optimizeBinary($raw, $absolutePath);
        if (!$optimized) {
            return ['status' => 'failed', 'reason' => 'optimizer_unavailable', 'path' => $path];
        }

        $newPath = $this->buildOptimizedPath($path, $directory);
        if ($dryRun) {
            return ['status' => 'optimized', 'path' => $newPath, 'old_path' => $path, 'dry_run' => true];
        }

        $disk->put($newPath, $optimized['content']);
        if ($newPath !== $path && $disk->exists($path)) {
            $disk->delete($path);
        }

        return ['status' => 'optimized', 'path' => $newPath, 'old_path' => $path];
    }

    public function isOptimizationAvailable(): bool
    {
        return $this->canUseImagick() || $this->canUseGd();
    }

    private function optimizeBinary(?string $raw, ?string $sourcePath = null): ?array
    {
        if (!$raw) {
            return null;
        }

        if ($this->canUseImagick()) {
            return $this->optimizeWithImagick($raw);
        }

        if ($this->canUseGd()) {
            return $this->optimizeWithGd($raw, $sourcePath);
        }

        return null;
    }

    private function optimizeWithImagick(string $raw): ?array
    {
        try {
            $image = new \Imagick();
            $image->readImageBlob($raw);
            $image->autoOrient();
            $image->stripImage();

            $sourceWidth = $image->getImageWidth();
            $sourceHeight = $image->getImageHeight();
            [$targetWidth, $targetHeight] = $this->fitWithin($sourceWidth, $sourceHeight);

            if ($targetWidth !== $sourceWidth || $targetHeight !== $sourceHeight) {
                $image->thumbnailImage($targetWidth, $targetHeight, true, true);
            }

            $image->setImageFormat('webp');
            $image->setOption('webp:method', '6');

            $bestBlob = null;
            $bestQuality = null;

            foreach (self::QUALITY_STEPS as $quality) {
                $image->setImageCompressionQuality($quality);
                $blob = $image->getImagesBlob();
                $bestBlob = $blob;
                $bestQuality = $quality;

                if (strlen($blob) <= self::TARGET_BYTES) {
                    break;
                }
            }

            return $bestBlob === null ? null : [
                'content' => $bestBlob,
                'quality' => $bestQuality,
                'width' => $image->getImageWidth(),
                'height' => $image->getImageHeight(),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function optimizeWithGd(string $raw, ?string $sourcePath = null): ?array
    {
        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            return null;
        }

        $image = $this->applyExifOrientation($image, $sourcePath);

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        [$targetWidth, $targetHeight] = $this->fitWithin($sourceWidth, $sourceHeight);

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        $bestBlob = null;
        $bestQuality = null;

        foreach (self::QUALITY_STEPS as $quality) {
            ob_start();
            imagewebp($canvas, null, $quality);
            $blob = ob_get_clean();

            if ($blob === false) {
                continue;
            }

            $bestBlob = $blob;
            $bestQuality = $quality;

            if (strlen($blob) <= self::TARGET_BYTES) {
                break;
            }
        }

        imagedestroy($canvas);
        imagedestroy($image);

        return $bestBlob === null ? null : [
            'content' => $bestBlob,
            'quality' => $bestQuality,
            'width' => $targetWidth,
            'height' => $targetHeight,
        ];
    }

    private function applyExifOrientation($image, ?string $sourcePath)
    {
        if (!$sourcePath || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($sourcePath);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private function fitWithin(int $width, int $height): array
    {
        if ($width <= self::MAX_DIMENSION && $height <= self::MAX_DIMENSION) {
            return [$width, $height];
        }

        $ratio = min(
            self::MAX_DIMENSION / max($width, 1),
            self::MAX_DIMENSION / max($height, 1)
        );

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }

    private function normalizePath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return ltrim(str_replace('\\', '/', $path), '/');
    }

    private function buildOptimizedPath(string $path, string $directory): string
    {
        if (Str::endsWith(Str::lower($path), '.webp')) {
            return $path;
        }

        $name = pathinfo($path, PATHINFO_FILENAME);
        $baseDirectory = trim(str_replace('\\', '/', pathinfo($path, PATHINFO_DIRNAME)), './');
        $targetDirectory = $baseDirectory && $baseDirectory !== '.'
            ? $baseDirectory
            : trim($directory, '/');

        return trim($targetDirectory, '/') . '/' . $name . '.webp';
    }

    private function canUseImagick(): bool
    {
        return extension_loaded('imagick') && class_exists(\Imagick::class);
    }

    private function canUseGd(): bool
    {
        return function_exists('imagecreatefromstring')
            && function_exists('imagecreatetruecolor')
            && function_exists('imagewebp');
    }
}
