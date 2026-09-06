<?php

namespace Tests\Unit;

use App\Services\FieldMappingService;
use Tests\TestCase;

class FieldMappingServiceWebsiteIntegrationTest extends TestCase
{
    public function test_website_default_template_maps_nested_payload_and_ignored_fields(): void
    {
        $service = app(FieldMappingService::class);
        $template = $service->getWebsiteIntegrationTemplates()['website_default_json'];

        $preview = $service->previewWebsiteMappings(
            $service->getWebsiteDefaultSamplePayload(),
            $template['mappings']
        );

        $this->assertSame('Ashutosh Dwivedi', $preview['mapped_payload']['name']);
        $this->assertSame('gal.ashutosh@gmail.com', $preview['mapped_payload']['email']);
        $this->assertSame('9560386672', $preview['mapped_payload']['phone']);
        $this->assertStringContainsString('Download', $preview['mapped_payload']['notes']);
        $this->assertStringContainsString('source.$oid', $preview['mapped_payload']['notes']);
        $this->assertArrayHasKey('__v', $preview['ignored_fields']);
        $this->assertSame([], $preview['missing_required']);
    }

    public function test_website_mapping_validation_requires_name_and_phone_targets(): void
    {
        $service = app(FieldMappingService::class);

        $validation = $service->validateWebsiteMappings([
            [
                'incoming_field' => 'email',
                'crm_field' => 'email',
                'is_required' => false,
                'default_value' => null,
                'is_ignored' => false,
            ],
        ], [
            'email' => 'test@example.com',
        ]);

        $this->assertArrayHasKey('required.name', $validation['errors']);
        $this->assertArrayHasKey('required.phone', $validation['errors']);
    }
}
