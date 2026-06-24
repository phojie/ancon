<?php

use App\Services\FeeCalculator\Money;

test('allocate gives the leftover cent to the earliest shares', function () {
    // 40.00 / 3 = 13.3333...; flooring to 13.33 each leaves 0.01 over -> first share gets it.
    $shares = Money::of(40)->allocate(3);

    expect(array_map(fn (Money $m): string => (string) $m, $shares))
        ->toBe(['13.34', '13.33', '13.33']);
});

test('allocate splits evenly when the amount divides cleanly', function () {
    $shares = Money::of(30)->allocate(3);

    expect(array_map(fn (Money $m): string => (string) $m, $shares))
        ->toBe(['10.00', '10.00', '10.00']);
});

test('allocate spreads a multi-cent leftover across the earliest shares', function () {
    // 100.00 / 7 = 14.2857...; floor 14.28 leaves 0.04 over -> first four shares get a cent.
    $shares = Money::of(100)->allocate(7);

    expect(array_map(fn (Money $m): string => (string) $m, $shares))
        ->toBe(['14.29', '14.29', '14.29', '14.29', '14.28', '14.28', '14.28']);
});

test('allocate of a single share returns the whole amount', function () {
    expect(array_map(fn (Money $m): string => (string) $m, Money::of(40)->allocate(1)))
        ->toBe(['40.00']);
});

test('allocate of zero shares returns no shares', function () {
    expect(Money::of(40)->allocate(0))->toBe([]);
});

test('allocate shares always sum back to the original exactly', function () {
    $sum = array_reduce(
        Money::of(40)->allocate(3),
        fn (Money $carry, Money $share): Money => $carry->plus($share),
        Money::zero(),
    );

    expect((string) $sum)->toBe('40.00');
});
