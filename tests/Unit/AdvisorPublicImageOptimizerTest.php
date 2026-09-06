<?php

namespace Tests\Unit;

use App\Services\AdvisorPublicImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdvisorPublicImageOptimizerTest extends TestCase
{
    public function test_optimizer_stores_webp_and_resizes_within_max_dimension(): void
    {
        if (!extension_loaded('imagick') && !function_exists('imagewebp')) {
            $this->markTestSkipped('No local image extension available for optimizer test.');
        }

        Storage::fake('public');
        $optimizer = new AdvisorPublicImageOptimizer();
        $upload = $this->makeLargePngUpload();

        $path = $optimizer->storeOptimizedUpload($upload, 'advisor-gallery');

        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);

        $details = getimagesize(Storage::disk('public')->path($path));
        $this->assertNotFalse($details);
        $this->assertLessThanOrEqual(1600, $details[0]);
        $this->assertLessThanOrEqual(1600, $details[1]);
    }

    private function makeLargePngUpload(): UploadedFile
    {
        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            $image = new \Imagick();
            $image->newImage(2200, 1800, new \ImagickPixel('#22c55e'));
            $image->setImageFormat('png');
            $blob = $image->getImageBlob();

            return UploadedFile::fake()->createWithContent('large.png', $blob);
        }

        $canvas = imagecreatetruecolor(2200, 1800);
        $background = imagecolorallocate($canvas, 34, 197, 94);
        imagefilledrectangle($canvas, 0, 0, 2200, 1800, $background);

        ob_start();
        imagepng($canvas);
        $blob = ob_get_clean();
        imagedestroy($canvas);

        return UploadedFile::fake()->createWithContent('large.png', $blob === false ? '' : $blob);
    }
}
