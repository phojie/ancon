<?php

declare(strict_types=1);

namespace App\Services\FeeCalculator;

/**
 * The fee breakdown for one unique manifest. Money figures are exact {@see Money} value objects
 * (never floats) so the downstream matching engine can use them directly; {@see self::toArray()}
 * serializes them to exact 2-decimal strings. `lineNumbers` records which invoice lines rolled up
 * into this manifest, for traceability.
 */
final readonly class ManifestFeeBreakdown
{
    /**
     * @param  list<int|string>  $lineNumbers
     */
    public function __construct(
        public string $manifestNumber,
        public array $lineNumbers,
        public Money $baseTotal,
        public Money $manifestFee,
        public Money $subtotal,
        public Money $surcharge,
        public Money $manifestTotal,
    ) {}

    /**
     * @return array{
     *     manifest_number: string,
     *     line_numbers: list<int|string>,
     *     base_total: string,
     *     manifest_fee: string,
     *     subtotal: string,
     *     surcharge: string,
     *     manifest_total: string
     * }
     */
    public function toArray(): array
    {
        return [
            'manifest_number' => $this->manifestNumber,
            'line_numbers' => $this->lineNumbers,
            'base_total' => (string) $this->baseTotal,
            'manifest_fee' => (string) $this->manifestFee,
            'subtotal' => (string) $this->subtotal,
            'surcharge' => (string) $this->surcharge,
            'manifest_total' => (string) $this->manifestTotal,
        ];
    }
}
