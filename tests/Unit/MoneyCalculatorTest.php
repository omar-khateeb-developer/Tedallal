<?php

namespace Tests\Unit;

use App\Support\MoneyCalculator;
use Tests\TestCase;

class MoneyCalculatorTest extends TestCase
{
    public function test_multiplies_price_by_quantity(): void
    {
        $this->assertSame('25.50', MoneyCalculator::multiply('8.50', 3));
    }

    public function test_adds_amounts_without_float_drift(): void
    {
        $this->assertSame('0.30', MoneyCalculator::add('0.10', '0.20'));
    }
}
