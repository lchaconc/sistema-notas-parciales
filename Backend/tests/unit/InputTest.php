<?php

use App\Exceptions\ApiError;
use App\Support\Input;
use CodeIgniter\Test\CIUnitTestCase;

final class InputTest extends CIUnitTestCase
{
    public function testDecimalParsingAndFormatting(): void
    {
        $this->assertSame(1523, Input::cents(['nota' => '15,23'], 'nota'));
        $this->assertSame(1523, Input::cents(['nota' => '15.23'], 'nota'));
        $this->assertSame('15.23', Input::decimal(1523));
        $this->assertSame('3.33', Input::decimal(intdiv(1000, 3)));
        $this->assertSame('1.70', Input::decimal(intdiv(850 * 200, 1000)));
    }

    public function testMoreThanTwoDecimalPlacesIsRejected(): void
    {
        $this->expectException(ApiError::class);
        Input::cents(['nota' => '1.234'], 'nota');
    }

    public function testInvalidCalendarDateIsRejected(): void
    {
        $this->expectException(ApiError::class);
        Input::date(['fecha' => '2025-02-30'], 'fecha');
    }
}
