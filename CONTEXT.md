# Vendor Invoice Fee Calculation

This context rolls vendor waste-disposal **Invoice Lines** up into one billable total per
**Manifest**, applying vendor-specific fees. Its output feeds a downstream **Matching Engine**.

## Language

**Invoice**:
A document of one or more **Invoice Lines** received from a single **Vendor**.
_Avoid_: Bill, statement.

**Invoice Line**:
A single charge on an **Invoice**, referencing exactly one **Manifest**.
_Avoid_: Row, item, entry.

**Manifest**:
A shipment identifier (e.g. `027425604JJK`). Multiple **Invoice Lines** can share one.
Billing rolls all lines for a manifest into a single **Manifest Total**.
_Avoid_: Shipment, consignment, ticket.

**Vendor Config**:
The fee parameters applied to every **Manifest** on a **Vendor**'s **Invoice**:
`manifest_fee`, `surcharge_percent`, `surcharge_applies_to`.
_Avoid_: Settings, rules, pricing.

**Base Total**:
Sum of all **Invoice Line** amounts for one **Manifest**, before any fee.

**Manifest Fee**:
A flat fee charged once per unique **Manifest** (not per line).

**Subtotal**:
**Base Total** plus **Manifest Fee**.

**Surcharge**:
A percentage charge whose basis is set by **Surcharge Basis**.

**Surcharge Basis** (`surcharge_applies_to`):
Which figure the **Surcharge** percentage multiplies. Known value:
`base_plus_manifest_fee` (the **Subtotal**).

**Manifest Total**:
**Subtotal** plus **Surcharge** — the final billable figure for one **Manifest**.

**Invoice Total**:
Sum of every **Manifest Total** on the **Invoice**.

**Matching Engine**:
A separate downstream consumer of the per-manifest breakdown. Not built here; it shapes
the return contract (stable, serializable, exact money values).

## Relationships

- A **Vendor** issues an **Invoice**
- An **Invoice** has many **Invoice Lines**
- An **Invoice Line** references exactly one **Manifest**
- A **Manifest** groups one or more **Invoice Lines**
- A **Vendor Config** applies to every **Manifest** on one **Invoice**
- The fee service produces one breakdown per **Manifest** plus one **Invoice Total**, consumed by the **Matching Engine**

## Example dialogue

> **Dev:** "The `manifest_fee` — is it per line or per manifest?"
> **Domain expert:** "Per unique **Manifest**. Four lines on one manifest still get one fee."
>
> **Dev:** "And the **Surcharge** — does it apply before or after the **Manifest Fee**?"
> **Domain expert:** "Depends on **Surcharge Basis**. For `base_plus_manifest_fee` it's on the **Subtotal**, so after the fee."

## Flagged ambiguities

- `surcharge_applies_to` ships with only one value (`base_plus_manifest_fee`), but the field name implies variants. Modeled as a **Surcharge Basis** enum so other bases can be added without touching the calculation flow; unknown values fail loud.
- Money precision: see [ADR 0001](docs/adr/0001-bcmath-money-and-half-up-rounding.md).
