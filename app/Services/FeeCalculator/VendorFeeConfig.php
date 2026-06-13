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
    ) {}

    /**
     * @param  array{manifest_fee?: mixed, surcharge_percent?: mixed, surcharge_applies_to?: mixed}  $config
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
        );
    }
}
