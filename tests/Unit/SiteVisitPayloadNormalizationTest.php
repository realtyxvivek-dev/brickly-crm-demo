<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\SiteVisitController;
use Illuminate\Support\Facades\Validator;
use ReflectionClass;
use Tests\TestCase;

class SiteVisitPayloadNormalizationTest extends TestCase
{
    public function test_single_and_nested_id_values_are_normalized_without_touching_invalid_arrays(): void
    {
        $controller = (new ReflectionClass(SiteVisitController::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(SiteVisitController::class))->getMethod('normalizeSiteVisitId');
        $method->setAccessible(true);

        $this->assertSame(46, $method->invoke($controller, [[46]]));
        $this->assertSame(46, $method->invoke($controller, [['id' => 46, 'name' => 'User']]));
        $this->assertSame([[46], [47]], $method->invoke($controller, [[46], [47]]));
    }

    public function test_invalid_nested_id_payload_returns_validation_error_instead_of_throwing(): void
    {
        $validator = Validator::make(
            ['lead_id' => [[46], [47]]],
            ['lead_id' => 'bail|nullable|integer|exists:leads,id']
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('lead_id', $validator->errors()->toArray());
    }
}
