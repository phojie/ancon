# Invoice Fee: equal allocation with largest-remainder, applied after surcharge

The **Invoice Fee** is a flat fee charged once per **Invoice** (vendor config `flat_fee`, e.g. `$40`),
but the result is reported per **Manifest** — so the fee must be split across an invoice's manifests.
Two money decisions follow: how to split it so the parts reconcile *exactly*, and whether the
surcharge applies to it.

We **allocate** the Invoice Fee equally across manifests and settle the leftover cent
deterministically — largest-remainder, ties broken by first-seen manifest order — and we add each
**Allocated Share** _after_ the surcharge. The Invoice Fee is a pass-through; it is not part of the
surcharge basis.

Worked example — `40.00 / 3`: floor to `13.33` each (sum `39.99`), 1¢ leftover goes to the
first-seen manifest → `13.34, 13.33, 13.33` = `40.00` exactly. That cent is the whole point: naive
per-manifest rounding loses it and the invoice fails to reconcile.

## Considered Options

### Splitting the fee

- **Equal allocation + largest-remainder leftover (chosen)** — every share is within 1¢ of the rest,
  the parts sum *exactly* to the fee, and the outcome is deterministic. Mirrors `brick/money`'s
  `allocate()` (ADR 0001's flagged upgrade path) and reuses the codebase's existing first-seen-order
  convention for the tiebreak.
- **Naive `round(40 / 3)` per manifest** — what the original question exposed: `13.33 × 3 = 39.99`,
  the cent vanishes, the invoice doesn't reconcile. Rejected.
- **Proportional to base total** — weights the fee by manifest size. Rejected: the brief specifies
  `40 / 3` (equal), and proportional distorts on credit or zero-base manifests.
- **Dump the leftover on the first (or last) manifest** — simpler, but arbitrary and lumpier than
  largest-remainder. Rejected in favour of the standard remainder rule.

### Surcharge interaction

- **Add after surcharge, not surcharged (chosen)** — the Invoice Fee reads as an invoice-level
  admin / pass-through charge, so the `40` lands exactly in the manifest totals and reconciliation
  stays clean.
- **Fold into the subtotal (surcharged, like `manifest_fee`)** — symmetric with the Manifest Fee,
  but makes the Invoice-Fee effect `40 + surcharge × 40` and muddies the "where's the cent"
  reconciliation. Rejected; revisit if the domain reclassifies the fee as a vendor service.

## Consequences

- `Money` gains `allocate(int $count): list<Money>` (largest-remainder); leftover cents go to the
  earliest entries, matching first-seen manifest order.
- The service can no longer compute each manifest in isolation: it counts the manifests, allocates
  `flat_fee` across them, then assigns share *i* to manifest *i*.
- `flat_fee` is **optional** in Vendor Config; absent or `0` → every Allocated Share is `0.00`, so
  existing callers are unaffected.
- Empty invoice (0 manifests) → nothing to allocate → the Invoice Fee is not charged and
  `invoice_total` stays `0.00`.
- The per-manifest result gains `allocated_share` (after `surcharge`); the invoice result gains
  `invoice_fee`. Invariants: `Σ allocated_share == invoice_fee`, and the **Invoice Total** contains
  the Invoice Fee exactly once.
- Migration path unchanged: swap the `Money` internals for `brick/money` behind the same interface —
  its `allocate()` follows this same rule.
