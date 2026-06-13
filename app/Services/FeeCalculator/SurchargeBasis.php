<?php

declare(strict_types=1);

namespace App\Services\FeeCalculator;

use InvalidArgumentException;

/**
 * Which figure the surcharge percentage is applied to (the vendor config's
 * `surcharge_applies_to`). Modeled as an enum so new bases can be added without
 * touching the calculation flow; unknown values fail loud.
 */
enum SurchargeBasis: string
{
    /** Surcharge applies to the subtotal (base total + manifest fee). The live value. */
    case BasePlusManifestFee = 'base_plus_manifest_fee';

    /** Surcharge applies to the base total only, before the manifest fee. Anticipated variant. */
    case BaseOnly = 'base_only';

    public static function fromConfig(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new InvalidArgumentException(sprintf('Unknown surcharge_applies_to value: "%s".', $value));
    }

    /**
     * Resolve which money figure the surcharge percentage multiplies.
     */
    public function appliesTo(Money $baseTotal, Money $subtotal): Money
    {
        return match ($this) {
            self::BasePlusManifestFee => $subtotal,
            self::BaseOnly => $baseTotal,
        };
    }
}
