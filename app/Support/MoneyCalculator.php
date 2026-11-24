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

    public static function subtract(string $minuend, string $subtrahend): string
    {
        return BigDecimal::of($minuend)
            ->minus(BigDecimal::of($subtrahend))
            ->toScale(self::SCALE, RoundingMode::HalfUp)
            ->__toString();
    }

    public static function assertNonNegative(string $amount): void
    {
        if (BigDecimal::of($amount)->isNegative()) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }
    }

    public static function percentage(string $amount, string $percent): string
    {
        $bdAmount = BigDecimal::of($amount);
        if ($bdAmount->isNegativeOrZero()) {
            return '0.00';
        }

        $bdPercent = BigDecimal::of($percent);
        if ($bdPercent->isNegativeOrZero()) {
            return '0.00';
        }

        $hundred = BigDecimal::of('100');
        if ($bdPercent->isGreaterThan($hundred)) {
            $bdPercent = $hundred;
        }

        $discount = $bdAmount
            ->multipliedBy($bdPercent)
            ->dividedBy($hundred, self::SCALE, RoundingMode::HalfUp);

        return $discount->isGreaterThan($bdAmount)
            ? $bdAmount->toScale(self::SCALE, RoundingMode::HalfUp)->__toString()
            : $discount->__toString();
    }

    public static function min(string $a, string $b): string
    {
        $bdA = BigDecimal::of($a);
        $bdB = BigDecimal::of($b);

        return ($bdA->isLessThanOrEqualTo($bdB) ? $bdA : $bdB)
            ->toScale(self::SCALE, RoundingMode::HalfUp)
            ->__toString();
    }
}
