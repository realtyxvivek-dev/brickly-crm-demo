<?php

namespace App\Services;

use App\Models\AttendancePhoto;
use App\Models\AttendancePolicy;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttendancePhotoService
{
    public function storeCompressed(?UploadedFile $photo, User $user, ?AttendancePolicy $policy = null, array $meta = []): ?AttendancePhoto
    {
        if (!$photo) {
            return null;
        }

        $quality = (int) ($policy?->compress_quality ?? 75);
        $maxWidth = (int) ($policy?->compress_max_width ?? 1280);
        $maxHeight = (int) ($policy?->compress_max_height ?? 1280);
        $disk = Storage::disk('public');
        $path = 'attendance/photos/' . now()->format('Y/m') . '/' . Str::uuid() . '.jpg';
        $width = null;
        $height = null;
        $compressedBytes = null;

        $raw = @file_get_contents($photo->getRealPath());
        $image = $raw ? @imagecreatefromstring($raw) : false;

        if ($image !== false) {
            $sourceWidth = imagesx($image);
            $sourceHeight = imagesy($image);
            [$targetWidth, $targetHeight] = $this->fitWithin($sourceWidth, $sourceHeight, $maxWidth, $maxHeight);

            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

            ob_start();
            imagejpeg($canvas, null, $quality);
            $compressed = ob_get_clean();

            imagedestroy($canvas);
            imagedestroy($image);

            if ($compressed !== false) {
                $disk->put($path, $compressed);
                $compressedBytes = strlen($compressed);
                $width = $targetWidth;
                $height = $targetHeight;
            }
        }

        if ($compressedBytes === null) {
            $path = $disk->putFile('attendance/photos/' . now()->format('Y/m'), $photo);
            $details = @getimagesize($photo->getRealPath()) ?: [null, null];
            $width = $details[0] ?? null;
            $height = $details[1] ?? null;
        }

        return AttendancePhoto::create([
            'user_id' => $user->id,
            'file_path' => $path,
            'mime_type' => $photo->getMimeType(),
            'file_size' => $photo->getSize(),
            'compressed_size' => $compressedBytes ?? $photo->getSize(),
            'width' => $width,
            'height' => $height,
            'compression_quality' => $quality,
            'file_hash' => $this->hashFile($disk->path($path)),
            'meta_json' => [
                'original_name' => $photo->getClientOriginalName(),
            ] + $meta,
            'captured_at' => now(),
        ]);
    }

    private function hashFile(string $path): ?string
    {
        return is_file($path) ? hash_file('sha256', $path) : null;
    }

    private function fitWithin(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        if ($width <= $maxWidth && $height <= $maxHeight) {
            return [$width, $height];
        }

        $ratio = min($maxWidth / max($width, 1), $maxHeight / max($height, 1));

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }
}
