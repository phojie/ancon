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
        return new self(self::round(self::toNumber($value)));
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
            ->mul(self::toNumber($percent))
            ->div('100', self::SCALE + 6);

        return new self(self::round($raw));
    }

    /**
     * Split this value into $count shares that sum back to it *exactly*.
     *
     * Equal split; any leftover cent is handed to the earliest shares (largest-remainder with a
     * first-seen tiebreak), so `40.00` over 3 is `13.34, 13.33, 13.33`. A count below 1 yields no
     * shares. Requires a non-negative value (a negative would drop the leftover cent); fee inputs
     * are gated by {@see VendorFeeConfig}. See ADR 0002.
     *
     * @return list<self>
     */
    public function allocate(int $count): array
    {
        if ($count < 1) {
            return [];
        }

        // Every Money is pre-rounded to SCALE=2, so ×100 is always integral — the cast is exact.
        $totalCents = (int) (string) $this->amount->mul('100');
        $baseCents = intdiv($totalCents, $count);
        $leftoverCents = $totalCents - ($baseCents * $count);

        $shares = [];

        for ($index = 0; $index < $count; $index++) {
            $shareCents = $baseCents + ($index < $leftoverCents ? 1 : 0);
            $shares[] = new self(self::round(self::toNumber((string) $shareCents)->div('100', self::SCALE)));
        }

        return $shares;
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
     * Build a BcMath\Number from a numeric scalar, failing loud. This is the single
     * guarded entry point for raw input, so well-formed-but-unsupported forms (e.g. the
     * scientific-notation string '1e3') surface as InvalidArgumentException rather than a
     * raw ValueError — whether the value is an amount or a percentage.
     */
    private static function toNumber(string|int|float $value): Number
    {
        $normalized = self::toNumericString($value);

        try {
            return new Number($normalized);
        } catch (\ValueError $valueError) {
            throw new InvalidArgumentException(
                sprintf('Money expects a decimal value, got "%s".', $normalized),
                $valueError->getCode(),
                previous: $valueError,
            );
        }
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
