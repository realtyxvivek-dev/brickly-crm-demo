<?php

namespace Tests\Unit;

use App\Services\InternationalPhoneService;
use PHPUnit\Framework\TestCase;

class InternationalPhoneServiceTest extends TestCase
{
    public function test_it_normalizes_indian_and_international_numbers_without_colliding(): void
    {
        $service = new InternationalPhoneService();

        $this->assertSame('919876543210', $service->parse('9876543210')['normalized']);
        $this->assertSame('919876543210', $service->parse('+91 98765-43210')['normalized']);
        $this->assertSame('+971501234567', $service->parse('+971 50 123 4567')['e164']);
        $this->assertSame('GB', $service->parse('7700900123', 'GB')['country_iso']);
        $this->assertNotSame($service->parse('+919876543210')['normalized'], $service->parse('+9719876543210')['normalized']);
        $this->assertNull($service->parse('1234567890'));
    }
}
