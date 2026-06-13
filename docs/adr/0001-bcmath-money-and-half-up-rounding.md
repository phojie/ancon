# BCMath money value object with HALF_UP rounding

Fee math is money math, so floats are unacceptable: `345.00 × 0.087 = 30.015` cannot be
represented exactly in binary float and would drift silently. We compute all fee figures with
a `Money` value object backed by PHP's built-in **BCMath** (string-based arbitrary-precision
decimals), rounding each money result to 2 decimal places using **round-half-up**. This keeps
the result exact and deterministic, adds no Composer dependency (honoring the project's
"no dependency changes without approval" rule), and demonstrates the math rather than hiding it
behind a library.

## Considered Options

- **BCMath `Money` VO (chosen)** — exact, zero new deps, fully testable, trivial to explain and extend.
- **Integer minor units (cents)** — exact for addition, but the surcharge multiply produces
  fractional cents and still needs an explicit rounding step, so it buys little over BCMath.
- **`brick/money`** — the industry-standard library and the natural upgrade path if this grows
  (multi-currency, allocation). Rejected for now: it needs dependency approval and reads as
  over-reach for a single-service, time-boxed task.
- **float + `round()`** — what the brief warns against. Rejected.

## Consequences

- The worked example resolves exactly: surcharges `85.695 → 85.70` and `30.015 → 30.02`,
  invoice total `1445.72`.
- Money crosses the in-process contract as a `Money` value object and serializes to a 2-decimal
  **string** (e.g. `"1070.70"`) at the `toArray()` boundary — never a float, so the Matching Engine
  receives exact values either way.
- Rounding is applied at each money boundary (each computed figure), not only at the end.
- Migration path if requirements grow: swap the `Money` VO internals for `brick/money` behind the
  same interface.
