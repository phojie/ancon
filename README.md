# Vendor Invoice Fee Calculation Service

A single Laravel service, `FeeCalculatorService`, that rolls vendor invoice lines up into one
billable total per manifest, applying vendor-specific fees.

```
For each unique manifest on the invoice:
  base_total     = sum of all line amounts for that manifest
  subtotal       = base_total + manifest_fee        (one fee per unique manifest)
  surcharge      = surcharge_basis * surcharge_percent
  manifest_total = subtotal + surcharge
```

## Run

```bash
composer install
php artisan test --compact          # 12 fee tests, worked example asserts every figure
```

## Where things live

| File | Role |
|------|------|
| [`app/Services/FeeCalculator/FeeCalculatorService.php`](app/Services/FeeCalculator/FeeCalculatorService.php) | The service. Groups by manifest, calculates, returns the result DTO. |
| [`app/Services/FeeCalculator/Money.php`](app/Services/FeeCalculator/Money.php) | Immutable money value object backed by PHP 8.4 `BcMath\Number`. Exact, round-half-up. |
| [`app/Services/FeeCalculator/VendorFeeConfig.php`](app/Services/FeeCalculator/VendorFeeConfig.php) | Typed + validated vendor config, built from the raw array. |
| [`app/Services/FeeCalculator/SurchargeBasis.php`](app/Services/FeeCalculator/SurchargeBasis.php) | Enum for `surcharge_applies_to`. |
| [`app/Services/FeeCalculator/ManifestFeeBreakdown.php`](app/Services/FeeCalculator/ManifestFeeBreakdown.php) · [`InvoiceFeeBreakdown.php`](app/Services/FeeCalculator/InvoiceFeeBreakdown.php) | The return contract (immutable DTOs + `toArray()`). |
| [`tests/Unit/FeeCalculatorServiceTest.php`](tests/Unit/FeeCalculatorServiceTest.php) | The worked-example test plus edge-case coverage. |
| [`CONTEXT.md`](CONTEXT.md) · [`docs/adr/0001-...`](docs/adr/0001-bcmath-money-and-half-up-rounding.md) | Domain glossary and the money-precision decision record. |

## Return contract

The output feeds a separate matching engine, so the result is an immutable, ordered,
serializable structure. Money crosses the boundary as exact 2-decimal **strings**, never floats.

```php
$result = (new FeeCalculatorService())->calculate($lines, $vendorConfig);

$result->invoiceTotal;            // "1445.72"
$result->manifests[0]->subtotal;  // "985.00"
$result->toArray();               // fully serializable for the matching engine
```

```php
// toArray() shape
[
  'manifests' => [
    [
      'manifest_number' => '027425604JJK',
      'line_numbers'    => [1, 2, 3, 4],   // traceability back to source lines
      'base_total'      => '960.00',
      'manifest_fee'    => '25.00',
      'subtotal'        => '985.00',
      'surcharge'       => '85.70',
      'manifest_total'  => '1070.70',
    ],
    // ...
  ],
  'invoice_total' => '1445.72',
]
```

## Assumptions and decisions

1. **Money is not float.** `345.00 × 0.087 = 30.015` is not representable in binary float, so all
   math runs through a `Money` value object backed by `BcMath\Number` (PHP 8.4's
   arbitrary-precision decimal). No Composer dependency was added — the project rule is no
   dependency changes without approval, and `brick/money` would be the documented upgrade path if
   this grows. See [ADR 0001](docs/adr/0001-bcmath-money-and-half-up-rounding.md).
2. **Rounding is round-half-up, applied at each money boundary** (every figure rounded to 2 dp),
   not only at the end. This reproduces the brief exactly (`85.695 → 85.70`, `30.015 → 30.02`).
3. **`surcharge_applies_to` is an enum.** The field name implies variants, so it is modeled as
   `SurchargeBasis`. `base_plus_manifest_fee` is the live value; `base_only` is included as the
   obvious anticipated variant. Any unknown value throws rather than silently mis-billing.
4. **Manifests keep first-seen order;** manifest numbers are trimmed but otherwise compared exactly
   (case-sensitive — they are identifiers, not labels). Purely numeric manifest numbers are handled
   despite PHP's integer array-key coercion.
5. **Input is taken as plain arrays exactly as the brief shows.** The vendor config is validated
   inside the service; missing keys, blank manifest numbers, and non-numeric amounts throw
   `InvalidArgumentException` (fail loud — wrong money is worse than no money).
6. **An invoice with no lines is valid** and returns an empty breakdown with `invoice_total`
   `"0.00"`. **Negative line amounts are allowed** and treated as credits.
7. **`line_numbers` is included per manifest** for the downstream matching engine's traceability,
   even though the brief's output only shows the rolled-up figures.

## Time spent

~2 hours, AI-assisted (Claude Code), inside the 2–3 hour box. Roughly: 20 min grilling the spec and
recording the domain model / ADR, 70 min TDD on the service and value objects, 30 min edge cases and
this README.

## What I'd do next with more time

- **Persist the config properly.** Wrap vendor config behind a `VendorConfig` source (model / repo)
  with Laravel validation rules and a `Money` cast, instead of validating a raw array inside the
  service.
- **Add invariant / property-based tests.** e.g. "sum of `manifest_total` always equals
  `invoice_total`" and fuzz rounding across random amounts and percents to harden the money path.
- **Swap `Money` internals for `brick/money`** (behind the same interface) the moment multi-currency,
  allocation, or formatting requirements appear — the value object already isolates that choice.
