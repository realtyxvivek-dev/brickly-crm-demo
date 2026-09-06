<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectSizeVariant;

class SimpleProjectPdfService
{
    private const PAGE_WIDTH = 595;
    private const PAGE_HEIGHT = 842;

    public function generateVariantPdf(Project $project, ProjectSizeVariant $variant): string
    {
        $page = $project->publicPage;
        $heroImage = $this->imageSpec($page?->hero_cover_path ? storage_path('app/public/' . $page->hero_cover_path) : null);
        $logoImage = $this->imageSpec($page?->builder_logo_path ? storage_path('app/public/' . $page->builder_logo_path) : null);
        $floorPlanImage = $this->imageSpec($variant->floor_plan_image_path ? storage_path('app/public/' . $variant->floor_plan_image_path) : null);

        $imageMap = [];
        $imageObjects = [];
        $nextObjectId = 9;
        foreach ([
            'hero' => $heroImage,
            'logo' => $logoImage,
            'floor' => $floorPlanImage,
        ] as $key => $spec) {
            if (!$spec) {
                continue;
            }

            $imageMap[$key] = 'Im' . (count($imageMap) + 1);
            $imageObjects[$nextObjectId] = $spec;
            $spec['alias'] = $imageMap[$key];
            $images[$key] = $spec;
            $nextObjectId++;
        }

        $images = [];
        $currentObjectId = 9;
        foreach ([
            'hero' => $heroImage,
            'logo' => $logoImage,
            'floor' => $floorPlanImage,
        ] as $key => $spec) {
            if (!$spec) {
                continue;
            }

            $images[$key] = $spec + [
                'alias' => 'Im' . (count($images) + 1),
                'object_id' => $currentObjectId,
            ];
            $currentObjectId++;
        }

        $highlights = $this->collectHighlights($project, $page?->featured_badges ?? []);
        $amenities = array_slice($highlights, 0, 8);
        $overview = $page?->short_intro ?: $project->short_overview ?: 'Project overview will be refined in the public page wizard.';
        $location = $page?->location_summary ?: $this->implodeParts([$project->area, $project->city]) ?: 'Location on request';
        $title = $page?->hero_title ?: $project->name ?: 'Project Details';
        $subtitle = $page?->hero_subtitle ?: ($project->builder?->name ?: 'Premium residential project');
        $unitTitle = trim(($variant->unitType?->name ?: 'Unit Variant') . ' | ' . $variant->size_label);
        $contactLine = $this->implodeParts([
            $page?->call_phone ? 'Call ' . $page->call_phone : null,
            $page?->whatsapp_number ? 'WhatsApp ' . $page->whatsapp_number : null,
        ]) ?: 'Advisor contact available on request';

        $pageOne = [];
        $pageTwo = [];

        $pageOne[] = $this->rect(0, 0, self::PAGE_WIDTH, self::PAGE_HEIGHT, [248, 246, 241], true);
        $pageTwo[] = $this->rect(0, 0, self::PAGE_WIDTH, self::PAGE_HEIGHT, [248, 246, 241], true);

        if (isset($images['hero'])) {
            [$w, $h, $x, $y] = $this->coverImage($images['hero']['width'], $images['hero']['height'], 0, 560, self::PAGE_WIDTH, 282);
            $pageOne[] = sprintf("q\n%.2F 0 0 %.2F %.2F %.2F cm\n/%s Do\nQ\n", $w, $h, $x, $y, $images['hero']['alias']);
            $pageOne[] = $this->rect(0, 560, self::PAGE_WIDTH, 282, [6, 32, 18], true);
        } else {
            $pageOne[] = $this->rect(0, 560, self::PAGE_WIDTH, 282, [11, 59, 36], true);
        }

        if (isset($images['logo'])) {
            [$w, $h, $x, $y] = $this->fitImage($images['logo']['width'], $images['logo']['height'], 42, 748, 74, 52);
            $pageOne[] = $this->rect(36, 742, 84, 62, [255, 255, 255], true);
            $pageOne[] = sprintf("q\n%.2F 0 0 %.2F %.2F %.2F cm\n/%s Do\nQ\n", $w, $h, $x, $y, $images['logo']['alias']);
        }

        $pageOne[] = $this->rect(36, 590, self::PAGE_WIDTH - 72, 120, [255, 255, 255], true);
        $pageOne[] = $this->text($title, 52, 668, 25, [14, 39, 26], 'B');
        $pageOne[] = $this->text($subtitle, 52, 640, 12, [68, 101, 81]);
        $pageOne[] = $this->textBlock($location, 52, 620, 11, [68, 101, 81], 42, 14, 3);
        $pageOne[] = $this->text('AUTO-GENERATED UNIT DETAILS', 378, 668, 10, [68, 101, 81], 'B');
        $pageOne[] = $this->text('Prepared for quick customer sharing', 378, 648, 11, [68, 101, 81]);

        $pageOne[] = $this->rect(36, 420, 250, 146, [238, 246, 240], true);
        $pageOne[] = $this->rect(306, 420, 253, 146, [255, 255, 255], true);
        $pageOne[] = $this->text('PRICE SNAPSHOT', 52, 538, 11, [68, 101, 81], 'B');
        $pageOne[] = $this->text($variant->formatted_final_price ?: 'Price on Request', 52, 500, 26, [6, 58, 28], 'B');
        $pageOne[] = $this->text(
            $variant->base_rate_per_sqft ? 'Company rate: Rs ' . number_format((float) $variant->base_rate_per_sqft, 0) . ' / sq.ft.' : 'Company rate: On request',
            52,
            478,
            11,
            [68, 101, 81]
        );
        $pageOne[] = $this->text($variant->is_price_on_request ? 'Quoted as Price on Request' : 'Calculated on built-up area basis', 52, 458, 11, [68, 101, 81]);

        $pageOne[] = $this->text('UNIT PROFILE', 322, 538, 11, [68, 101, 81], 'B');
        $pageOne[] = $this->text($unitTitle, 322, 504, 19, [6, 58, 28], 'B');
        $pageOne[] = $this->text('Built-up: ' . ($variant->builtup_area_sqft ? number_format((float) $variant->builtup_area_sqft, 0) . ' sq.ft.' : 'N/A'), 322, 478, 11, [22, 48, 34]);
        $pageOne[] = $this->text('Carpet: ' . ($variant->carpet_area_sqft ? number_format((float) $variant->carpet_area_sqft, 0) . ' sq.ft.' : 'N/A'), 322, 458, 11, [22, 48, 34]);
        $pageOne[] = $this->text('Status: ' . ucfirst((string) $variant->status), 322, 438, 11, [22, 48, 34]);

        $pageOne[] = $this->rect(36, 90, self::PAGE_WIDTH - 72, 300, [255, 255, 255], true);
        $pageOne[] = $this->text('PROJECT STORY', 52, 364, 12, [6, 58, 28], 'B');
        foreach ($this->wrapText($overview, 38) as $i => $line) {
            if ($i >= 5) {
                break;
            }
            $pageOne[] = $this->text($line, 52, 338 - ($i * 18), 11, [22, 48, 34]);
        }

        $pageOne[] = $this->text('KEY HIGHLIGHTS', 52, 252, 12, [6, 58, 28], 'B');
        foreach (array_slice($highlights, 0, 6) as $i => $highlight) {
            $pageOne[] = $this->text('• ' . $highlight, 52, 224 - ($i * 18), 11, [22, 48, 34]);
        }

        $pageOne[] = $this->text('CONTACT READY', 332, 364, 12, [6, 58, 28], 'B');
        if ($page?->call_phone) {
            $pageOne[] = $this->textBlock('Call: ' . $page->call_phone, 332, 338, 12, [22, 48, 34], 24, 14, 2, 'B');
        }
        if ($page?->whatsapp_number) {
            $pageOne[] = $this->textBlock('WhatsApp: ' . $page->whatsapp_number, 332, 314, 12, [22, 48, 34], 24, 14, 2, 'B');
        }
        $pageOne[] = $this->textBlock('Possession: ' . ($project->possession_date ? $project->possession_date->format('M Y') : 'N/A'), 332, 284, 11, [22, 48, 34], 24, 13, 2);
        $pageOne[] = $this->textBlock('RERA: ' . ($project->rera_no ?: 'N/A'), 332, 254, 10, [22, 48, 34], 26, 12, 3);

        $pageOne[] = $this->text('TOP AMENITIES', 332, 252, 12, [6, 58, 28], 'B');
        foreach (array_slice($amenities, 0, 5) as $i => $amenity) {
            $pageOne[] = $this->text('• ' . $amenity, 332, 224 - ($i * 18), 11, [22, 48, 34]);
        }

        $pageOne[] = $this->text('Generated on ' . now()->format('d M Y, h:i A'), 52, 58, 10, [68, 101, 81]);
        $pageOne[] = $this->text('System-generated brochure preview. Uploaded PDF will override this version automatically.', 52, 42, 10, [68, 101, 81]);

        $pageTwo[] = $this->rect(0, 736, self::PAGE_WIDTH, 106, [6, 58, 28], true);
        $pageTwo[] = $this->text('FLOOR PLAN & UNIT DETAILS', 42, 790, 22, [255, 255, 255], 'B');
        $pageTwo[] = $this->text($unitTitle, 42, 764, 12, [226, 240, 231]);
        $pageTwo[] = $this->textBlock($location, 42, 744, 11, [226, 240, 231], 46, 14, 3);

        $pageTwo[] = $this->rect(36, 228, self::PAGE_WIDTH - 72, 476, [255, 255, 255], true);
        $pageTwo[] = $this->text('PLAN VIEW', 52, 676, 11, [68, 101, 81], 'B');

        if (isset($images['floor'])) {
            [$w, $h, $x, $y] = $this->fitImage($images['floor']['width'], $images['floor']['height'], 54, 294, 487, 340);
            $pageTwo[] = $this->rect(52, 296, self::PAGE_WIDTH - 104, 342, [243, 246, 244], true);
            $pageTwo[] = sprintf("q\n%.2F 0 0 %.2F %.2F %.2F cm\n/%s Do\nQ\n", $w, $h, $x, $y, $images['floor']['alias']);
        } else {
            $pageTwo[] = $this->rect(52, 296, self::PAGE_WIDTH - 104, 342, [243, 246, 244], true);
            $pageTwo[] = $this->text('Floor plan image is not uploaded yet.', 72, 476, 18, [68, 101, 81], 'B');
            $pageTwo[] = $this->text('This PDF still carries unit, pricing, location, and contact details for quick sharing.', 72, 446, 12, [68, 101, 81]);
            $pageTwo[] = $this->text('Upload the floor plan later and regenerate for a richer branded brochure.', 72, 424, 12, [68, 101, 81]);
        }

        $pageTwo[] = $this->rect(36, 54, self::PAGE_WIDTH - 72, 144, [238, 246, 240], true);
        $pageTwo[] = $this->text('REFERENCE DETAILS', 52, 168, 12, [6, 58, 28], 'B');
        $detailBlocks = [
            ['Built-up Area', $variant->builtup_area_sqft ? number_format((float) $variant->builtup_area_sqft, 0) . ' sq.ft.' : 'N/A'],
            ['Carpet Area', $variant->carpet_area_sqft ? number_format((float) $variant->carpet_area_sqft, 0) . ' sq.ft.' : 'N/A'],
            ['Price', $variant->formatted_final_price ?: 'Price on Request'],
            ['Rate', $variant->base_rate_per_sqft ? 'Rs ' . number_format((float) $variant->base_rate_per_sqft, 0) . ' / sq.ft.' : 'On request'],
        ];
        foreach ($detailBlocks as $index => [$label, $value]) {
            $x = $index < 2 ? 52 : 308;
            $y = $index % 2 === 0 ? 132 : 92;
            $pageTwo[] = $this->text($label, $x, $y, 10, [68, 101, 81], 'B');
            $pageTwo[] = $this->text($value, $x, $y - 18, 14, [22, 48, 34], 'B');
        }

        $pageOneContent = implode('', $pageOne);
        $pageTwoContent = implode('', $pageTwo);

        $resources = "<< /Font << /F1 7 0 R /F2 8 0 R >>";
        if ($images !== []) {
            $xObjects = [];
            foreach ($images as $image) {
                $xObjects[] = '/' . $image['alias'] . ' ' . $image['object_id'] . ' 0 R';
            }
            $resources .= ' /XObject << ' . implode(' ', $xObjects) . ' >>';
        }
        $resources .= ' >>';

        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>",
            3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . "] /Resources {$resources} /Contents 5 0 R >>",
            4 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . "] /Resources {$resources} /Contents 6 0 R >>",
            5 => "<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream",
            6 => "<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream",
            7 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
            8 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>",
        ];

        foreach ($images as $image) {
            $objects[$image['object_id']] = "<< /Type /XObject /Subtype /Image /Width {$image['width']} /Height {$image['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($image['data']) . " >>\nstream\n{$image['data']}\nendstream";
        }

        return $this->buildPdf($objects);
    }

    private function collectHighlights(Project $project, array $featuredBadges): array
    {
        $items = [];
        foreach ($featuredBadges as $badge) {
            if (is_string($badge) && trim($badge) !== '') {
                $items[] = trim($badge);
            }
        }

        $projectHighlights = $project->project_highlights;
        if (is_string($projectHighlights) && trim($projectHighlights) !== '') {
            foreach (preg_split('/[\r\n|,]+/', $projectHighlights) ?: [] as $item) {
                $item = trim($item);
                if ($item !== '') {
                    $items[] = $item;
                }
            }
        }

        $fallbacks = array_filter([
            $project->project_status ? 'Status: ' . ucwords(str_replace('_', ' ', $project->project_status)) : null,
            $project->city ? 'City: ' . $project->city : null,
            $project->area ? 'Locality: ' . $project->area : null,
        ]);

        return array_values(array_unique(array_merge($items, $fallbacks)));
    }

    private function buildPdf(array $objects): string
    {
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (max(array_keys($objects)) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= max(array_keys($objects)); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size " . (max(array_keys($objects)) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function text(string $text, float $x, float $y, float $size, array $rgb, string $font = 'R'): string
    {
        [$r, $g, $b] = array_map(fn ($value) => $value / 255, $rgb);
        $fontName = $font === 'B' ? 'F2' : 'F1';

        return sprintf(
            "BT\n/%s %.2F Tf\n%.3F %.3F %.3F rg\n1 0 0 1 %.2F %.2F Tm\n(%s) Tj\nET\n",
            $fontName,
            $size,
            $r,
            $g,
            $b,
            $x,
            $y,
            $this->escapeText($text)
        );
    }

    private function textBlock(string $text, float $x, float $y, float $size, array $rgb, int $wrapAt, float $lineHeight, int $maxLines = 3, string $font = 'R'): string
    {
        $output = '';
        $lines = array_slice($this->wrapText($text, $wrapAt), 0, $maxLines);
        foreach ($lines as $index => $line) {
            $output .= $this->text($line, $x, $y - ($index * $lineHeight), $size, $rgb, $font);
        }

        return $output;
    }

    private function rect(float $x, float $y, float $width, float $height, array $rgb, bool $fill = true): string
    {
        [$r, $g, $b] = array_map(fn ($value) => $value / 255, $rgb);

        return sprintf("%.3F %.3F %.3F rg\n%.2F %.2F %.2F %.2F re\n%s\n", $r, $g, $b, $x, $y, $width, $height, $fill ? 'f' : 'S');
    }

    private function fitImage(int $width, int $height, float $boxX, float $boxY, float $boxWidth, float $boxHeight): array
    {
        $scale = min($boxWidth / max($width, 1), $boxHeight / max($height, 1));
        $drawWidth = $width * $scale;
        $drawHeight = $height * $scale;
        $drawX = $boxX + (($boxWidth - $drawWidth) / 2);
        $drawY = $boxY + (($boxHeight - $drawHeight) / 2);

        return [$drawWidth, $drawHeight, $drawX, $drawY];
    }

    private function coverImage(int $width, int $height, float $boxX, float $boxY, float $boxWidth, float $boxHeight): array
    {
        $scale = max($boxWidth / max($width, 1), $boxHeight / max($height, 1));
        $drawWidth = $width * $scale;
        $drawHeight = $height * $scale;
        $drawX = $boxX - (($drawWidth - $boxWidth) / 2);
        $drawY = $boxY - (($drawHeight - $boxHeight) / 2);

        return [$drawWidth, $drawHeight, $drawX, $drawY];
    }

    private function escapeText(string $text): string
    {
        $text = preg_replace('/[^\x20-\x7E]/', ' ', $text) ?? '';

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function imageSpec(?string $path): ?array
    {
        if (!$path || !is_file($path) || !function_exists('imagecreatefromjpeg')) {
            return null;
        }

        $info = @getimagesize($path);
        if (!$info || !isset($info[2])) {
            return null;
        }

        $resource = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if (!$resource) {
            return null;
        }

        $width = imagesx($resource);
        $height = imagesy($resource);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $resource, 0, 0, 0, 0, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 88);
        $binary = (string) ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($resource);

        if ($binary === '') {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
            'data' => $binary,
        ];
    }

    private function wrapText(string $text, int $length): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($clean === '') {
            return [];
        }

        return explode("\n", wordwrap($clean, $length, "\n", true));
    }

    private function implodeParts(array $parts): string
    {
        return implode(', ', array_values(array_filter($parts, fn ($part) => filled($part))));
    }
}
