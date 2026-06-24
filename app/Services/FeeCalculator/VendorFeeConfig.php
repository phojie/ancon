<?php

declare(strict_types=1);

namespace App\Services\FeeCalculator;

use InvalidArgumentException;

/**
 * Typed, validated vendor fee parameters. Built from the raw config array the brief
 * specifies so malformed input fails loud at the boundary rather than producing wrong money.
 */
final readonly class VendorFeeConfig
{
    public function __construct(
        public Money $manifestFee,
        public string|int|float $surchargePercent,
        public SurchargeBasis $surchargeBasis,
        public Money $flatFee,
    ) {}

    /**
     * @param  array{manifest_fee?: mixed, surcharge_percent?: mixed, surcharge_applies_to?: mixed, flat_fee?: mixed}  $config
     */
    public static function fromArray(array $config): self
    {
        foreach (['manifest_fee', 'surcharge_percent', 'surcharge_applies_to'] as $key) {
            if (! array_key_exists($key, $config)) {
                throw new InvalidArgumentException(sprintf('Missing vendor config key: "%s".', $key));
            }
        }

        if (! is_numeric($config['manifest_fee'])) {
            throw new InvalidArgumentException('Vendor config "manifest_fee" must be numeric.');
        }

        if (! is_numeric($config['surcharge_percent'])) {
            throw new InvalidArgumentException('Vendor config "surcharge_percent" must be numeric.');
        }

        return new self(
            manifestFee: Money::of($config['manifest_fee']),
            surchargePercent: $config['surcharge_percent'],
            surchargeBasis: SurchargeBasis::fromConfig((string) $config['surcharge_applies_to']),
            flatFee: self::flatFeeFrom($config),
        );
    }

    /**
     * The invoice-level flat fee is optional; absent means none. When present it must be a
     * non-negative number, failing loud like the required keys rather than silently mis-billing.
     * Negative is rejected here so {@see Money::allocate()} only ever receives a value its
     * largest-remainder split reconciles exactly (a negative would drop the leftover cent).
     *
     * @param  array{flat_fee?: mixed}  $config
     */
    private static function flatFeeFrom(array $config): Money
    {
        if (! array_key_exists('flat_fee', $config)) {
            return Money::zero();
        }

        if (! is_numeric($config['flat_fee'])) {
            throw new InvalidArgumentException('Vendor config "flat_fee" must be numeric.');
        }

        if ($config['flat_fee'] < 0) {
            throw new InvalidArgumentException('Vendor config "flat_fee" must not be negative.');
        }

        return Money::of($config['flat_fee']);
    }
}
