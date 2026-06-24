<?php

declare(strict_types=1);

namespace App\Services\FeeCalculator;

/**
 * The full result of a fee calculation: one {@see ManifestFeeBreakdown} per unique manifest
 * (in first-seen order) plus the invoice total. This is the contract consumed by the
 * downstream matching engine — stable, ordered, and serializable via {@see self::toArray()}.
 */
final readonly class InvoiceFeeBreakdown
{
    /**
     * @param  list<ManifestFeeBreakdown>  $manifests
     */
    public function __construct(
        public array $manifests,
        public Money $invoiceFee,
        public Money $invoiceTotal,
    ) {}

    /**
     * @return array{manifests: list<array<string, mixed>>, invoice_fee: string, invoice_total: string}
     */
    public function toArray(): array
    {
        return [
            'manifests' => array_map(
                static fn (ManifestFeeBreakdown $manifest): array => $manifest->toArray(),
                $this->manifests,
            ),
            'invoice_fee' => (string) $this->invoiceFee,
            'invoice_total' => (string) $this->invoiceTotal,
        ];
    }
}
