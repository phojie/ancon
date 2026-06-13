<?php

declare(strict_types=1);

namespace App\Services\FeeCalculator;

/**
 * The fee breakdown for one unique manifest. Money figures are exact 2-decimal strings
 * (never floats) so the downstream matching engine receives precise values. `lineNumbers`
 * records which invoice lines rolled up into this manifest, for traceability.
 */
final readonly class ManifestFeeBreakdown
{
    /**
     * @param  list<int|string>  $lineNumbers
     */
    public function __construct(
        public string $manifestNumber,
        public array $lineNumbers,
        public string $baseTotal,
        public string $manifestFee,
        public string $subtotal,
        public string $surcharge,
        public string $manifestTotal,
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
            'base_total' => $this->baseTotal,
            'manifest_fee' => $this->manifestFee,
            'subtotal' => $this->subtotal,
            'surcharge' => $this->surcharge,
            'manifest_total' => $this->manifestTotal,
        ];
    }
}
