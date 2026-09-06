<?php

namespace Database\Seeders;

use App\Models\Builder;
use App\Models\Project;
use App\Models\ProjectAsset;
use App\Models\ProjectPublicPage;
use App\Models\ProjectShareLink;
use App\Models\ProjectSizeVariant;
use App\Models\ProjectUnitType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectPublicPageDemoSeeder extends Seeder
{
    public function run(): void
    {
        $heroPath = 'project-public/demo/hero-main.jpg';
        $galleryOnePath = 'project-public/demo/gallery-1.jpg';
        $galleryTwoPath = 'project-public/demo/gallery-2.jpg';
        $galleryThreePath = 'project-public/demo/gallery-3.jpg';
        $floorPlan3APath = 'project-public/demo/floor-plan-3a.svg';
        $floorPlan3BPath = 'project-public/demo/floor-plan-3b.svg';
        $floorPlan3CPath = 'project-public/demo/floor-plan-3c.svg';
        $floorPlan4APath = 'project-public/demo/floor-plan-4a.svg';
        $floorPlan4BPath = 'project-public/demo/floor-plan-4b.svg';
        $detailsPdf3APath = 'project-public/demo/details-3a.pdf';
        $detailsPdf3BPath = 'project-public/demo/details-3b.pdf';
        $detailsPdf3CPath = 'project-public/demo/details-3c.pdf';
        $detailsPdf4APath = 'project-public/demo/details-4a.pdf';
        $detailsPdf4BPath = 'project-public/demo/details-4b.pdf';
        $brochurePath = 'project-public/demo/brochure.pdf';
        $priceSheetPath = 'project-public/demo/price-sheet.pdf';

        $this->storeImageOrFallback($heroPath, 'https://images.unsplash.com/photo-1511818966892-d7d671e672a2?auto=format&fit=crop&w=1600&q=80', 'Mulberry Heights Hero', '#063A1C', '#205A44');
        $this->storeImageOrFallback($galleryOnePath, 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=1600&q=80', 'Gallery One', '#205A44', '#4f8a6d');
        $this->storeImageOrFallback($galleryTwoPath, 'https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=1600&q=80', 'Gallery Two', '#0f5132', '#5f9f82');
        $this->storeImageOrFallback($galleryThreePath, 'https://images.unsplash.com/photo-1460317442991-0ec209397118?auto=format&fit=crop&w=1600&q=80', 'Gallery Three', '#12482e', '#7aa88f');

        Storage::disk('public')->put($floorPlan3APath, $this->svg('3 BHK Plan A', '#205A44', '#5A8C73'));
        Storage::disk('public')->put($floorPlan3BPath, $this->svg('3 BHK Plan B', '#1f5d3f', '#78A88C'));
        Storage::disk('public')->put($floorPlan3CPath, $this->svg('3 BHK Plan C', '#0E4D2C', '#6fa284'));
        Storage::disk('public')->put($floorPlan4APath, $this->svg('4 BHK Plan A', '#0E4D2C', '#78A88C'));
        Storage::disk('public')->put($floorPlan4BPath, $this->svg('4 BHK Plan B', '#12482e', '#87b497'));

        Storage::disk('public')->put($detailsPdf3APath, $this->minimalPdf('Mulberry Heights - 3 BHK - 1450 Sq.ft.'));
        Storage::disk('public')->put($detailsPdf3BPath, $this->minimalPdf('Mulberry Heights - 3 BHK - 1560 Sq.ft.'));
        Storage::disk('public')->put($detailsPdf3CPath, $this->minimalPdf('Mulberry Heights - 3 BHK - 1685 Sq.ft.'));
        Storage::disk('public')->put($detailsPdf4APath, $this->minimalPdf('Mulberry Heights - 4 BHK - 1890 Sq.ft.'));
        Storage::disk('public')->put($detailsPdf4BPath, $this->minimalPdf('Mulberry Heights - 4 BHK - 2140 Sq.ft.'));
        Storage::disk('public')->put($brochurePath, $this->minimalPdf('Mulberry Heights Brochure'));
        Storage::disk('public')->put($priceSheetPath, $this->minimalPdf('Mulberry Heights Price Sheet'));

        $builder = Builder::query()->firstOrCreate(
            ['name' => 'Rishita Developers'],
            ['status' => 'active']
        );

        $project = Project::query()->updateOrCreate(
            ['name' => 'Mulberry Heights Demo'],
            [
                'builder_id' => $builder->id,
                'short_overview' => 'Premium apartment community for public page testing.',
                'project_type' => 'residential',
                'residential_sub_type' => 'flat',
                'project_status' => 'under_construction',
                'availability_type' => 'fresh',
                'city' => 'Lucknow',
                'area' => 'Sushant Golf City',
                'rera_no' => 'UPRERAPRJ-DEMO-001',
                'possession_date' => now()->addMonths(10)->toDateString(),
                'is_active' => true,
            ]
        );

        $page = ProjectPublicPage::query()->updateOrCreate(
            ['project_id' => $project->id],
            [
                'hero_title' => 'Mulberry Heights',
                'hero_subtitle' => 'Minimal premium public page demo',
                'short_intro' => 'Share-ready customer page with plans, pricing, media, and tracked CTAs.',
                'featured_badges' => ['Clubhouse', 'Prime location', 'Ready ecosystem'],
                'hero_cover_path' => $heroPath,
                'location_summary' => 'Near schools, retail, and daily commute routes.',
                'base_rate_per_sqft' => 11500,
                'rounding_rule' => 'nearest_100000',
                'show_call' => true,
                'show_whatsapp' => true,
                'show_book_visit' => true,
                'show_request_callback' => true,
                'show_downloads' => true,
                'show_video' => true,
                'show_tour_360' => true,
                'call_phone' => '9876543210',
                'whatsapp_number' => '919876543210',
                'book_visit_url' => 'https://example.com/book-visit',
                'callback_url' => 'https://example.com/request-callback',
                'status' => 'published',
                'preview_token' => Str::random(32),
                'last_saved_at' => now(),
                'published_at' => now(),
            ]
        );

        $unit3 = ProjectUnitType::query()->updateOrCreate(
            ['project_id' => $project->id, 'name' => '3 BHK'],
            ['display_order' => 0, 'is_primary' => true]
        );
        $unit4 = ProjectUnitType::query()->updateOrCreate(
            ['project_id' => $project->id, 'name' => '4 BHK'],
            ['display_order' => 1, 'is_primary' => false]
        );

        $variants = [
            [
                'unit_id' => $unit3->id,
                'size_label' => '1450 Sq.ft.',
                'builtup_area_sqft' => 1450,
                'carpet_area_sqft' => 1107,
                'base_rate_per_sqft' => 11500,
                'rounding_rule' => 'nearest_100000',
                'calculated_price' => 16675000,
                'final_price' => 16700000,
                'status' => 'available',
                'floor_plan_image_path' => $floorPlan3APath,
                'details_pdf_path' => $detailsPdf3APath,
                'display_order' => 0,
                'is_featured' => true,
            ],
            [
                'unit_id' => $unit3->id,
                'size_label' => '1560 Sq.ft.',
                'builtup_area_sqft' => 1560,
                'carpet_area_sqft' => 1188,
                'base_rate_per_sqft' => 11650,
                'rounding_rule' => 'nearest_100000',
                'calculated_price' => 18174000,
                'final_price' => 18200000,
                'status' => 'available',
                'floor_plan_image_path' => $floorPlan3BPath,
                'details_pdf_path' => $detailsPdf3BPath,
                'display_order' => 1,
                'is_featured' => false,
            ],
            [
                'unit_id' => $unit3->id,
                'size_label' => '1685 Sq.ft.',
                'builtup_area_sqft' => 1685,
                'carpet_area_sqft' => 1282,
                'base_rate_per_sqft' => 11750,
                'rounding_rule' => 'nearest_100000',
                'calculated_price' => 19798750,
                'final_price' => 19800000,
                'status' => 'hold',
                'floor_plan_image_path' => $floorPlan3CPath,
                'details_pdf_path' => $detailsPdf3CPath,
                'display_order' => 2,
                'is_featured' => false,
            ],
            [
                'unit_id' => $unit4->id,
                'size_label' => '1890 Sq.ft.',
                'builtup_area_sqft' => 1890,
                'carpet_area_sqft' => 1460,
                'base_rate_per_sqft' => 11800,
                'rounding_rule' => 'nearest_100000',
                'calculated_price' => 22302000,
                'final_price' => 22300000,
                'status' => 'available',
                'floor_plan_image_path' => $floorPlan4APath,
                'details_pdf_path' => $detailsPdf4APath,
                'display_order' => 0,
                'is_featured' => false,
            ],
            [
                'unit_id' => $unit4->id,
                'size_label' => '2140 Sq.ft.',
                'builtup_area_sqft' => 2140,
                'carpet_area_sqft' => 1665,
                'base_rate_per_sqft' => 12100,
                'rounding_rule' => 'nearest_100000',
                'calculated_price' => 25894000,
                'final_price' => 25900000,
                'status' => 'hold',
                'floor_plan_image_path' => $floorPlan4BPath,
                'details_pdf_path' => $detailsPdf4BPath,
                'display_order' => 1,
                'is_featured' => true,
            ],
        ];

        foreach ($variants as $variant) {
            ProjectSizeVariant::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'project_unit_type_id' => $variant['unit_id'],
                    'size_label' => $variant['size_label'],
                ],
                [
                    'builtup_area_sqft' => $variant['builtup_area_sqft'],
                    'carpet_area_sqft' => $variant['carpet_area_sqft'],
                    'base_rate_per_sqft' => $variant['base_rate_per_sqft'],
                    'rounding_rule' => $variant['rounding_rule'],
                    'calculated_price' => $variant['calculated_price'],
                    'final_price' => $variant['final_price'],
                    'status' => $variant['status'],
                    'visible_on_public_page' => true,
                    'floor_plan_image_path' => $variant['floor_plan_image_path'],
                    'details_pdf_path' => $variant['details_pdf_path'],
                    'display_order' => $variant['display_order'],
                    'is_featured' => $variant['is_featured'],
                ]
            );
        }

        ProjectSizeVariant::query()
            ->where('project_id', $project->id)
            ->whereNotIn('size_label', collect($variants)->pluck('size_label'))
            ->delete();

        ProjectAsset::query()->updateOrCreate(
            ['project_id' => $project->id, 'tracking_key' => 'mulberry-brochure'],
            [
                'asset_type' => 'brochure',
                'title' => 'Project Brochure',
                'mime_type' => 'application/pdf',
                'source_type' => 'uploaded',
                'file_size' => Storage::disk('public')->size($brochurePath),
                'file_path' => $brochurePath,
                'preview_image_path' => $heroPath,
                'display_order' => 0,
                'is_featured' => true,
            ]
        );

        ProjectAsset::query()->updateOrCreate(
            ['project_id' => $project->id, 'tracking_key' => 'mulberry-price-sheet'],
            [
                'asset_type' => 'price_sheet',
                'title' => 'Price Sheet',
                'mime_type' => 'application/pdf',
                'source_type' => 'uploaded',
                'file_size' => Storage::disk('public')->size($priceSheetPath),
                'file_path' => $priceSheetPath,
                'preview_image_path' => $heroPath,
                'display_order' => 1,
                'is_featured' => false,
            ]
        );

        ProjectAsset::query()->updateOrCreate(
            ['project_id' => $project->id, 'tracking_key' => 'mulberry-video'],
            [
                'asset_type' => 'video',
                'title' => 'Project Walkthrough',
                'mime_type' => 'text/html',
                'source_type' => 'external',
                'external_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'preview_image_path' => $heroPath,
                'display_order' => 2,
                'is_featured' => false,
            ]
        );

        ProjectAsset::query()->updateOrCreate(
            ['project_id' => $project->id, 'tracking_key' => 'mulberry-tour-360'],
            [
                'asset_type' => 'tour_360',
                'title' => '360 Tour',
                'mime_type' => 'text/html',
                'source_type' => 'external',
                'external_url' => 'https://example.com/tour/mulberry-heights',
                'preview_image_path' => $heroPath,
                'display_order' => 3,
                'is_featured' => false,
            ]
        );

        foreach ([
            ['key' => 'mulberry-gallery-1', 'title' => 'Arrival View', 'file' => $galleryOnePath, 'order' => 4],
            ['key' => 'mulberry-gallery-2', 'title' => 'Lifestyle View', 'file' => $galleryTwoPath, 'order' => 5],
            ['key' => 'mulberry-gallery-3', 'title' => 'Facade View', 'file' => $galleryThreePath, 'order' => 6],
        ] as $galleryAsset) {
            ProjectAsset::query()->updateOrCreate(
                ['project_id' => $project->id, 'tracking_key' => $galleryAsset['key']],
                [
                    'asset_type' => 'gallery_image',
                    'title' => $galleryAsset['title'],
                    'mime_type' => 'image/jpeg',
                    'source_type' => 'uploaded',
                    'file_size' => Storage::disk('public')->size($galleryAsset['file']),
                    'file_path' => $galleryAsset['file'],
                    'preview_image_path' => $galleryAsset['file'],
                    'display_order' => $galleryAsset['order'],
                    'is_featured' => false,
                ]
            );
        }

        $project->publicLandmarks()->delete();
        $project->publicLandmarks()->createMany([
            ['label' => 'Phoenix Mall', 'type' => 'Retail', 'distance_text' => '12 min drive', 'display_order' => 0],
            ['label' => 'Metro Station', 'type' => 'Transit', 'distance_text' => '9 min drive', 'display_order' => 1],
            ['label' => 'City School', 'type' => 'School', 'distance_text' => '6 min drive', 'display_order' => 2],
        ]);

        ProjectShareLink::query()->updateOrCreate(
            ['project_id' => $project->id, 'advisor_id' => null, 'lead_id' => null],
            [
                'token' => Str::random(40),
                'status' => 'active',
                'view_count' => 0,
            ]
        );
    }

    private function svg(string $title, string $start, string $end): string
    {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="900" viewBox="0 0 1200 900">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$start}" />
      <stop offset="100%" stop-color="{$end}" />
    </linearGradient>
  </defs>
  <rect width="1200" height="900" fill="url(#bg)" rx="48" />
  <circle cx="970" cy="190" r="150" fill="rgba(255,255,255,0.08)" />
  <rect x="90" y="110" width="500" height="250" rx="30" fill="rgba(255,255,255,0.08)" />
  <text x="110" y="220" fill="#ffffff" font-size="72" font-family="Arial, sans-serif" font-weight="700">{$title}</text>
  <text x="110" y="290" fill="rgba(255,255,255,0.76)" font-size="28" font-family="Arial, sans-serif">CRM share page demo asset</text>
</svg>
SVG;
    }

    private function minimalPdf(string $title): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $title);

        return "%PDF-1.4\n"
            . "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n"
            . "2 0 obj<< /Type /Pages /Count 1 /Kids [3 0 R] >>endobj\n"
            . "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>endobj\n"
            . "4 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj\n"
            . "5 0 obj<< /Length 58 >>stream\nBT /F1 22 Tf 60 760 Td ({$text}) Tj ET\nendstream endobj\n"
            . "xref\n0 6\n0000000000 65535 f \n"
            . "0000000010 00000 n \n0000000061 00000 n \n0000000118 00000 n \n0000000244 00000 n \n0000000314 00000 n \n"
            . "trailer<< /Size 6 /Root 1 0 R >>\nstartxref\n423\n%%EOF";
    }

    private function storeImageOrFallback(string $path, string $url, string $title, string $start, string $end): void
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'header' => "User-Agent: Mozilla/5.0\r\n",
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $contents = @file_get_contents($url, false, $context);
            if ($contents !== false && strlen($contents) > 1024) {
                Storage::disk('public')->put($path, $contents);
                return;
            }
        } catch (\Throwable) {
        }

        Storage::disk('public')->put($path, $this->svg($title, $start, $end));
    }
}
