<?php

use App\Services\FeeCalculator\FeeCalculatorService;
use App\Services\FeeCalculator\InvoiceFeeBreakdown;
use App\Services\FeeCalculator\ManifestFeeBreakdown;
use App\Services\FeeCalculator\Money;

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
    expect($first->baseTotal)->toBeInstanceOf(Money::class);
    expect($first->manifestNumber)->toBe('027425604JJK');
    expect($first->lineNumbers)->toBe([1, 2, 3, 4]);
    expect((string) $first->baseTotal)->toBe('960.00');
    expect((string) $first->manifestFee)->toBe('25.00');
    expect((string) $first->subtotal)->toBe('985.00');
    expect((string) $first->surcharge)->toBe('85.70');
    expect((string) $first->manifestTotal)->toBe('1070.70');

    $second = $result->manifests[1];
    expect($second->manifestNumber)->toBe('027425611JJK');
    expect($second->lineNumbers)->toBe([5]);
    expect((string) $second->baseTotal)->toBe('320.00');
    expect((string) $second->manifestFee)->toBe('25.00');
    expect((string) $second->subtotal)->toBe('345.00');
    expect((string) $second->surcharge)->toBe('30.02');
    expect((string) $second->manifestTotal)->toBe('375.02');

    expect($result->invoiceTotal)->toBeInstanceOf(Money::class);
    expect((string) $result->invoiceTotal)->toBe('1445.72');
});

test('allocates the invoice flat fee across manifests after the surcharge', function () {
    $lines = [
        ['line_number' => 1, 'manifest_number' => 'M1', 'amount' => 100.00],
        ['line_number' => 2, 'manifest_number' => 'M2', 'amount' => 100.00],
        ['line_number' => 3, 'manifest_number' => 'M3', 'amount' => 100.00],
    ];
    $config = [
        'manifest_fee' => 0,
        'surcharge_percent' => 10,
        'surcharge_applies_to' => 'base_plus_manifest_fee',
        'flat_fee' => 40.00,
    ];

    $result = (new FeeCalculatorService)->calculate($lines, $config);

    // Surcharge stays 10% of the 100.00 subtotal -> the allocated share is NOT surcharged.
    expect((string) $result->manifests[0]->surcharge)->toBe('10.00');

    // 40.00 / 3 -> the first-seen manifest carries the leftover cent.
    expect((string) $result->manifests[0]->allocatedShare)->toBe('13.34');
    expect((string) $result->manifests[1]->allocatedShare)->toBe('13.33');
    expect((string) $result->manifests[2]->allocatedShare)->toBe('13.33');

    // manifest_total = subtotal + surcharge + allocated_share.
    expect((string) $result->manifests[0]->manifestTotal)->toBe('123.34');
    expect((string) $result->manifests[1]->manifestTotal)->toBe('123.33');
    expect((string) $result->manifests[2]->manifestTotal)->toBe('123.33');

    // The invoice grew by exactly the 40.00 flat fee (330.00 -> 370.00) and exposes it once.
    expect((string) $result->invoiceTotal)->toBe('370.00');
    expect((string) $result->invoiceFee)->toBe('40.00');
});

test('charges no flat fee when the invoice has no manifests', function () {
    // Nothing to allocate across zero manifests, so the flat fee is simply not charged.
    $result = (new FeeCalculatorService)->calculate(
        [],
        ['manifest_fee' => 25.00, 'surcharge_percent' => 8.7, 'surcharge_applies_to' => 'base_plus_manifest_fee', 'flat_fee' => 40.00],
    );

    expect($result->manifests)->toBe([]);
    expect((string) $result->invoiceFee)->toBe('0.00');
    expect((string) $result->invoiceTotal)->toBe('0.00');
});

test('gives a single manifest the whole flat fee', function () {
    $result = (new FeeCalculatorService)->calculate(
        [['line_number' => 1, 'manifest_number' => 'M1', 'amount' => 100.00]],
        ['manifest_fee' => 0, 'surcharge_percent' => 0, 'surcharge_applies_to' => 'base_plus_manifest_fee', 'flat_fee' => 40.00],
    );

    expect((string) $result->manifests[0]->allocatedShare)->toBe('40.00');
    expect((string) $result->manifests[0]->manifestTotal)->toBe('140.00');
    expect((string) $result->invoiceFee)->toBe('40.00');
});

test('omitting flat_fee leaves an allocated share of zero', function () {
    $result = (new FeeCalculatorService)->calculate(
        [['line_number' => 1, 'manifest_number' => 'M1', 'amount' => 100.00]],
        ['manifest_fee' => 0, 'surcharge_percent' => 0, 'surcharge_applies_to' => 'base_plus_manifest_fee'],
    );

    expect((string) $result->manifests[0]->allocatedShare)->toBe('0.00');
    expect((string) $result->invoiceFee)->toBe('0.00');
});

test('a credit manifest still carries its equal flat-fee share', function () {
    // M2 is a pure credit; equal allocation is blind to amount, so it still bears its share.
    $result = (new FeeCalculatorService)->calculate(
        [
            ['line_number' => 1, 'manifest_number' => 'M1', 'amount' => 100.00],
            ['line_number' => 2, 'manifest_number' => 'M2', 'amount' => -100.00],
            ['line_number' => 3, 'manifest_number' => 'M3', 'amount' => 50.00],
        ],
        ['manifest_fee' => 0, 'surcharge_percent' => 0, 'surcharge_applies_to' => 'base_plus_manifest_fee', 'flat_fee' => 40.00],
    );

    expect(array_map(fn ($m): string => (string) $m->allocatedShare, $result->manifests))
        ->toBe(['13.34', '13.33', '13.33']);
    // M2: base -100.00 + share 13.33 = -86.67.
    expect((string) $result->manifests[1]->manifestTotal)->toBe('-86.67');
    expect((string) $result->invoiceFee)->toBe('40.00');
});

test('allocated shares always reconcile to the invoice fee for an awkward split', function () {
    // 100.00 across 7 manifests never divides cleanly; the shares must still sum to exactly 100.00.
    $lines = [];
    for ($i = 1; $i <= 7; $i++) {
        $lines[] = ['line_number' => $i, 'manifest_number' => "M{$i}", 'amount' => 10.00];
    }

    $result = (new FeeCalculatorService)->calculate(
        $lines,
        ['manifest_fee' => 0, 'surcharge_percent' => 0, 'surcharge_applies_to' => 'base_plus_manifest_fee', 'flat_fee' => 100.00],
    );

    $sumOfShares = array_reduce(
        $result->manifests,
        fn (Money $carry, ManifestFeeBreakdown $manifest): Money => $carry->plus($manifest->allocatedShare),
        Money::zero(),
    );

    expect((string) $sumOfShares)->toBe('100.00');
    expect((string) $result->invoiceFee)->toBe('100.00');
});

test('rounds the surcharge half-up at the cent boundary', function () {
    // subtotal 100.00 * 8.755% = 8.755 -> half-up -> 8.76
    $result = (new FeeCalculatorService)->calculate(
        [['line_number' => 1, 'manifest_number' => 'M1', 'amount' => 100.00]],
        ['manifest_fee' => 0, 'surcharge_percent' => 8.755, 'surcharge_applies_to' => 'base_plus_manifest_fee'],
    );

    expect((string) $result->manifests[0]->surcharge)->toBe('8.76');
    expect((string) $result->manifests[0]->manifestTotal)->toBe('108.76');
});

test('applies the surcharge to the base total only when basis is base_only', function () {
    // base 100.00, fee 25.00; surcharge on base only = 100.00 * 10% = 10.00
    $result = (new FeeCalculatorService)->calculate(
        [['line_number' => 1, 'manifest_number' => 'M1', 'amount' => 100.00]],
        ['manifest_fee' => 25.00, 'surcharge_percent' => 10, 'surcharge_applies_to' => 'base_only'],
    );

    $manifest = $result->manifests[0];
    expect((string) $manifest->baseTotal)->toBe('100.00');
    expect((string) $manifest->subtotal)->toBe('125.00');
    expect((string) $manifest->surcharge)->toBe('10.00');
    expect((string) $manifest->manifestTotal)->toBe('135.00');
});

test('groups interleaved lines preserving first-seen manifest order', function () {
    $result = (new FeeCalculatorService)->calculate(
        [
            ['line_number' => 1, 'manifest_number' => 'A', 'amount' => 10.00],
            ['line_number' => 2, 'manifest_number' => 'B', 'amount' => 20.00],
            ['line_number' => 3, 'manifest_number' => 'A', 'amount' => 5.00],
            ['line_number' => 4, 'manifest_number' => 'C', 'amount' => 1.00],
        ],
        ['manifest_fee' => 0, 'surcharge_percent' => 0, 'surcharge_applies_to' => 'base_plus_manifest_fee'],
    );

    expect(array_map(fn ($m) => $m->manifestNumber, $result->manifests))->toBe(['A', 'B', 'C']);
    expect($result->manifests[0]->lineNumbers)->toBe([1, 3]);
    expect((string) $result->manifests[0]->baseTotal)->toBe('15.00');
});

test('handles purely numeric manifest numbers without integer key coercion', function () {
    // PHP coerces integer-like array keys to int; the breakdown must still expose a string.
    $result = (new FeeCalculatorService)->calculate(
        [['line_number' => 1, 'manifest_number' => '12345', 'amount' => 100.00]],
        ['manifest_fee' => 0, 'surcharge_percent' => 0, 'surcharge_applies_to' => 'base_plus_manifest_fee'],
    );

    expect($result->manifests[0]->manifestNumber)->toBe('12345');
    expect((string) $result->manifests[0]->baseTotal)->toBe('100.00');
});

test('treats negative line amounts as credits', function () {
    $result = (new FeeCalculatorService)->calculate(
        [
            ['line_number' => 1, 'manifest_number' => 'M1', 'amount' => 200.00],
            ['line_number' => 2, 'manifest_number' => 'M1', 'amount' => -50.00],
        ],
        ['manifest_fee' => 0, 'surcharge_percent' => 0, 'surcharge_applies_to' => 'base_plus_manifest_fee'],
    );

    expect((string) $result->manifests[0]->baseTotal)->toBe('150.00');
    expect((string) $result->invoiceTotal)->toBe('150.00');
});

test('returns an empty breakdown with a zero total for no lines', function () {
    $result = (new FeeCalculatorService)->calculate(
        [],
        ['manifest_fee' => 25.00, 'surcharge_percent' => 8.7, 'surcharge_applies_to' => 'base_plus_manifest_fee'],
    );

    expect($result->manifests)->toBe([]);
    expect((string) $result->invoiceTotal)->toBe('0.00');
});

test('serializes to a stable array contract for the matching engine', function () {
    $result = (new FeeCalculatorService)->calculate(
        [['line_number' => 1, 'manifest_number' => 'M1', 'amount' => 100.00]],
        ['manifest_fee' => 25.00, 'surcharge_percent' => 10, 'surcharge_applies_to' => 'base_plus_manifest_fee'],
    );

    expect($result->toArray())->toBe([
        'manifests' => [[
            'manifest_number' => 'M1',
            'line_numbers' => [1],
            'base_total' => '100.00',
            'manifest_fee' => '25.00',
            'subtotal' => '125.00',
            'surcharge' => '12.50',
            'allocated_share' => '0.00',
            'manifest_total' => '137.50',
        ]],
        'invoice_fee' => '0.00',
        'invoice_total' => '137.50',
    ]);
});

test('rounds each line amount at the cent boundary before summing', function () {
    // Each 100.005 rounds half-up to 100.01 at the boundary, so 100.01 + 100.01 = 200.02.
    // A sum-then-round strategy would instead give 200.01 — this pins ADR 0001's choice.
    $result = (new FeeCalculatorService)->calculate(
        [
            ['line_number' => 1, 'manifest_number' => 'M1', 'amount' => '100.005'],
            ['line_number' => 2, 'manifest_number' => 'M1', 'amount' => '100.005'],
        ],
        ['manifest_fee' => 0, 'surcharge_percent' => 0, 'surcharge_applies_to' => 'base_plus_manifest_fee'],
    );

    expect((string) $result->manifests[0]->baseTotal)->toBe('200.02');
    expect((string) $result->manifests[0]->manifestTotal)->toBe('200.02');
});

test('fails loud on invalid input', function (array $lines, array $config, string $message) {
    expect(fn () => (new FeeCalculatorService)->calculate($lines, $config))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'missing config key' => [
        [['line_number' => 1, 'manifest_number' => 'M1', 'amount' => 10.00]],
        ['manifest_fee' => 25.00, 'surcharge_percent' => 8.7],
        'Missing vendor config key: "surcharge_applies_to".',
    ],
    'unknown surcharge basis' => [
        [['line_number' => 1, 'manifest_number' => 'M1', 'amount' => 10.00]],
        ['manifest_fee' => 25.00, 'surcharge_percent' => 8.7, 'surcharge_applies_to' => 'whole_invoice'],
        'Unknown surcharge_applies_to value: "whole_invoice".',
    ],
    'blank manifest number' => [
        [['line_number' => 1, 'manifest_number' => '   ', 'amount' => 10.00]],
        ['manifest_fee' => 25.00, 'surcharge_percent' => 8.7, 'surcharge_applies_to' => 'base_plus_manifest_fee'],
        'has a blank manifest_number',
    ],
    'non-numeric amount' => [
        [['line_number' => 1, 'manifest_number' => 'M1', 'amount' => 'free']],
        ['manifest_fee' => 25.00, 'surcharge_percent' => 8.7, 'surcharge_applies_to' => 'base_plus_manifest_fee'],
        'Money expects a numeric value',
    ],
    'scientific-notation amount' => [
        [['line_number' => 1, 'manifest_number' => 'M1', 'amount' => '1e3']],
        ['manifest_fee' => 25.00, 'surcharge_percent' => 8.7, 'surcharge_applies_to' => 'base_plus_manifest_fee'],
        'Money expects a decimal value',
    ],
    'null amount' => [
        [['line_number' => 7, 'manifest_number' => 'M1', 'amount' => null]],
        ['manifest_fee' => 25.00, 'surcharge_percent' => 8.7, 'surcharge_applies_to' => 'base_plus_manifest_fee'],
        'line 7 on manifest M1 is missing an amount',
    ],
    'scientific-notation surcharge_percent' => [
        [['line_number' => 1, 'manifest_number' => 'M1', 'amount' => 10.00]],
        ['manifest_fee' => 25.00, 'surcharge_percent' => '1e3', 'surcharge_applies_to' => 'base_plus_manifest_fee'],
        'Money expects a decimal value',
    ],
    'non-numeric flat_fee' => [
        [['line_number' => 1, 'manifest_number' => 'M1', 'amount' => 10.00]],
        ['manifest_fee' => 25.00, 'surcharge_percent' => 8.7, 'surcharge_applies_to' => 'base_plus_manifest_fee', 'flat_fee' => 'free'],
        'Vendor config "flat_fee" must be numeric.',
    ],
    'negative flat_fee' => [
        [['line_number' => 1, 'manifest_number' => 'M1', 'amount' => 10.00]],
        ['manifest_fee' => 25.00, 'surcharge_percent' => 8.7, 'surcharge_applies_to' => 'base_plus_manifest_fee', 'flat_fee' => -40.00],
        'Vendor config "flat_fee" must not be negative.',
    ],
]);
