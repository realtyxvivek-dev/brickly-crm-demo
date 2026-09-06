<?php

namespace Tests\Unit;

use App\Services\DynamicFormService;
use App\Services\KycFormSchemaService;
use Mockery;
use PHPUnit\Framework\TestCase;

class KycFormSchemaServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_unit_type_options_from_form_builder_are_preserved(): void
    {
        $service = new KycFormSchemaService(Mockery::mock(DynamicFormService::class));
        $fields = $service->prepareFieldsForPersistence([[
            'field_key' => 'booking_unit_type',
            'field_type' => 'select',
            'label' => 'Unit Type',
            'options' => ['2 BHK', '3 BHK + S', '4 BHK'],
        ]]);

        $unitType = collect($fields)->firstWhere('field_key', 'booking_unit_type');

        self::assertSame(['2 BHK', '3 BHK + S', '4 BHK'], $unitType['options']);
    }

    public function test_date_of_birth_accepts_years_from_1900_through_today(): void
    {
        $dynamicForms = Mockery::mock(DynamicFormService::class);
        $dynamicForms->shouldReceive('getLatestFormByLocation')->once()->andReturnNull();
        $service = new KycFormSchemaService($dynamicForms);
        $rules = $service->getValidationRules(true);

        self::assertStringContainsString('after_or_equal:1900-01-01', $rules['primary_applicant_date_of_birth']);
        self::assertStringContainsString('before_or_equal:today', $rules['primary_applicant_date_of_birth']);
    }
}
