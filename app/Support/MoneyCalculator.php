<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class MoneyCalculator
{
    private const SCALE = 2;

    public static function multiply(string $amount, int $quantity): string
    {
        return BigDecimal::of($amount)
            ->multipliedBy($quantity)
            ->toScale(self::SCALE, RoundingMode::HalfUp)
            ->__toString();
    }

    public static function add(string ...$amounts): string
    {
        $total = BigDecimal::zero();

        foreach ($amounts as $amount) {
            $total = $total->plus(BigDecimal::of($amount));
        }

        return $total->toScale(self::SCALE, RoundingMode::HalfUp)->__toString();
    }

    public static function assertNonNegative(string $amount): void
    {
        if (BigDecimal::of($amount)->isNegative()) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }
    }
}
