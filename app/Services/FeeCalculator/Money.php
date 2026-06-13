<?php

declare(strict_types=1);

namespace App\Services\FeeCalculator;

use BcMath\Number;
use InvalidArgumentException;
use RoundingMode;

/**
 * Immutable money value object backed by PHP 8.4's arbitrary-precision BcMath\Number.
 *
 * Every value is normalized to {@see self::SCALE} decimal places using round-half-up
 * ({@see RoundingMode::HalfAwayFromZero}), so all arithmetic is exact and floats never
 * enter the calculation. See ADR 0001.
 */
final readonly class Money implements \Stringable
{
    /** Decimal places every money figure is rounded to. */
    public const int SCALE = 2;

    private function __construct(private Number $amount) {}

    /**
     * Build money from a numeric scalar, rounding to {@see self::SCALE} half-up.
     */
    public static function of(string|int|float $value): self
    {
        return new self(self::round(new Number(self::toNumericString($value))));
    }

    public static function zero(): self
    {
        return self::of(0);
    }

    /**
     * Return a new Money equal to this value plus another.
     */
    public function plus(Money $other): self
    {
        return new self(self::round($this->amount->add($other->amount)));
    }

    /**
     * Return the given percentage of this value, rounded half-up.
     *
     * A percent of 8.7 means 8.7%, i.e. value * 8.7 / 100.
     */
    public function percentage(string|int|float $percent): self
    {
        $raw = $this->amount
            ->mul(self::toNumericString($percent))
            ->div('100', self::SCALE + 6);

        return new self(self::round($raw));
    }

    public function __toString(): string
    {
        return (string) $this->amount;
    }

    private static function round(Number $value): Number
    {
        return $value->round(self::SCALE, RoundingMode::HalfAwayFromZero);
    }

    /**
     * Convert a numeric scalar to a string BcMath\Number accepts, without float drift
     * beyond money precision. Non-numeric input fails loud.
     */
    private static function toNumericString(string|int|float $value): string
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException(
                sprintf('Money expects a numeric value, got "%s".', is_string($value) ? $value : gettype($value))
            );
        }

        if (is_float($value)) {
            return sprintf('%.6F', $value);
        }

        return (string) $value;
    }
}
