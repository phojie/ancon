<?php

declare(strict_types=1);

namespace App\Services\FeeCalculator;

use InvalidArgumentException;

/**
 * Rolls vendor invoice lines up into one billable total per manifest, applying
 * vendor-specific fees.
 *
 * For each unique manifest on the invoice:
 *   1. base_total     = sum of all line amounts for that manifest
 *   2. subtotal       = base_total + manifest_fee   (one fee per unique manifest)
 *   3. surcharge      = surcharge_basis * surcharge_percent
 *   4. manifest_total = subtotal + surcharge
 *
 * All money math is exact (see {@see Money} and ADR 0001). The result is a structured,
 * serializable contract for the downstream matching engine.
 */
final class FeeCalculatorService
{
    /**
     * @param  iterable<array{line_number?: int|string, manifest_number: string, description?: string, amount: int|float|string}>  $lines
     * @param  array{manifest_fee?: mixed, surcharge_percent?: mixed, surcharge_applies_to?: mixed}  $vendorConfig
     */
    public function calculate(iterable $lines, array $vendorConfig): InvoiceFeeBreakdown
    {
        $config = VendorFeeConfig::fromArray($vendorConfig);

        $manifests = [];
        $invoiceTotal = Money::zero();

        foreach ($this->groupByManifest($lines) as $manifestNumber => $manifestLines) {
            $breakdown = $this->calculateManifest($manifestNumber, $manifestLines, $config);

            $manifests[] = $breakdown;
            $invoiceTotal = $invoiceTotal->plus(Money::of($breakdown->manifestTotal));
        }

        return new InvoiceFeeBreakdown($manifests, (string) $invoiceTotal);
    }

    /**
     * Group lines by manifest number, preserving first-seen order.
     *
     * @param  iterable<array<string, mixed>>  $lines
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupByManifest(iterable $lines): array
    {
        $grouped = [];

        foreach ($lines as $index => $line) {
            $manifestNumber = $this->manifestNumberOf($line, $index);

            $grouped[$manifestNumber][] = $line;
        }

        return $grouped;
    }

    /**
     * @param  list<array<string, mixed>>  $manifestLines
     */
    private function calculateManifest(string $manifestNumber, array $manifestLines, VendorFeeConfig $config): ManifestFeeBreakdown
    {
        $baseTotal = Money::zero();
        $lineNumbers = [];

        foreach ($manifestLines as $index => $line) {
            if (! array_key_exists('amount', $line)) {
                throw new InvalidArgumentException(sprintf('Invoice line %s on manifest %s is missing an amount.', $index, $manifestNumber));
            }

            $baseTotal = $baseTotal->plus(Money::of($line['amount']));

            if (array_key_exists('line_number', $line)) {
                $lineNumbers[] = $line['line_number'];
            }
        }

        $subtotal = $baseTotal->plus($config->manifestFee);
        $surcharge = $config->surchargeBasis
            ->appliesTo($baseTotal, $subtotal)
            ->percentage($config->surchargePercent);
        $manifestTotal = $subtotal->plus($surcharge);

        return new ManifestFeeBreakdown(
            manifestNumber: $manifestNumber,
            lineNumbers: $lineNumbers,
            baseTotal: (string) $baseTotal,
            manifestFee: (string) $config->manifestFee,
            subtotal: (string) $subtotal,
            surcharge: (string) $surcharge,
            manifestTotal: (string) $manifestTotal,
        );
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function manifestNumberOf(array $line, int|string $index): string
    {
        if (! array_key_exists('manifest_number', $line)) {
            throw new InvalidArgumentException(sprintf('Invoice line %s is missing a manifest_number.', $index));
        }

        $manifestNumber = trim((string) $line['manifest_number']);

        if ($manifestNumber === '') {
            throw new InvalidArgumentException(sprintf('Invoice line %s has a blank manifest_number.', $index));
        }

        return $manifestNumber;
    }
}
