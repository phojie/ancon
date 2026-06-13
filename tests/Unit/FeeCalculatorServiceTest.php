<?php

use App\Services\FeeCalculator\FeeCalculatorService;
use App\Services\FeeCalculator\InvoiceFeeBreakdown;
use App\Services\FeeCalculator\ManifestFeeBreakdown;

/**
 * The worked example from the brief. Asserts every intermediate figure, not just
 * the final total, so a regression in any step (grouping, fee, surcharge, rounding)
 * is caught precisely.
 */
test('calculates the worked example with exact per-manifest figures', function () {
    $lines = [
        ['line_number' => 1, 'manifest_number' => '027425604JJK', 'description' => 'Disposal', 'amount' => 500.00],
        ['line_number' => 2, 'manifest_number' => '027425604JJK', 'description' => 'Treatment', 'amount' => 75.00],
        ['line_number' => 3, 'manifest_number' => '027425604JJK', 'description' => 'Transport', 'amount' => 175.00],
        ['line_number' => 4, 'manifest_number' => '027425604JJK', 'description' => 'Lab Work', 'amount' => 210.00],
        ['line_number' => 5, 'manifest_number' => '027425611JJK', 'description' => 'Disposal', 'amount' => 320.00],
    ];

    $vendorConfig = [
        'manifest_fee' => 25.00,
        'surcharge_percent' => 8.7,
        'surcharge_applies_to' => 'base_plus_manifest_fee',
    ];

    $result = (new FeeCalculatorService)->calculate($lines, $vendorConfig);

    expect($result)->toBeInstanceOf(InvoiceFeeBreakdown::class);
    expect($result->manifests)->toHaveCount(2);

    $first = $result->manifests[0];
    expect($first)->toBeInstanceOf(ManifestFeeBreakdown::class);
    expect($first->manifestNumber)->toBe('027425604JJK');
    expect($first->lineNumbers)->toBe([1, 2, 3, 4]);
    expect($first->baseTotal)->toBe('960.00');
    expect($first->manifestFee)->toBe('25.00');
    expect($first->subtotal)->toBe('985.00');
    expect($first->surcharge)->toBe('85.70');
    expect($first->manifestTotal)->toBe('1070.70');

    $second = $result->manifests[1];
    expect($second->manifestNumber)->toBe('027425611JJK');
    expect($second->lineNumbers)->toBe([5]);
    expect($second->baseTotal)->toBe('320.00');
    expect($second->manifestFee)->toBe('25.00');
    expect($second->subtotal)->toBe('345.00');
    expect($second->surcharge)->toBe('30.02');
    expect($second->manifestTotal)->toBe('375.02');

    expect($result->invoiceTotal)->toBe('1445.72');
});
