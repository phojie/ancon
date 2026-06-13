# ✨ Ancon — Vendor Invoice Fee Service

[![php][php-src]][php-href]
[![laravel][laravel-src]][laravel-href]
[![ci][ci-src]][ci-href]
[![license][license-src]][license-href]

A focused Laravel service that calculates vendor invoice fees, rolled up per **manifest**.

## Features

A single `FeeCalculatorService`, but:

- **[Manifest roll-up](CONTEXT.md)** - groups invoice lines by manifest into one total each.
- **[Exact money math](docs/adr/0001-bcmath-money-and-half-up-rounding.md)** - round-half-up via `BcMath\Number`, no float drift.
- **Configurable surcharge** - per-vendor `SurchargeBasis` enum; unknown values fail loud.
- **Immutable result contract** - typed DTOs holding exact `Money`, serialized to strings, ready for a matching engine.
- **[Fully tested](tests/Unit/FeeCalculatorServiceTest.php)** - worked example plus rounding, grouping, credit, and validation edges.

## Assumptions

- **Money is exact, never float** — all arithmetic runs through a `BcMath\Number`-backed `Money` value object, round-half-up at each money boundary ([ADR 0001](docs/adr/0001-bcmath-money-and-half-up-rounding.md)).
- **`surcharge_applies_to` is an enum** — `base_plus_manifest_fee` is live, `base_only` is the anticipated variant; unknown values throw.
- **Manifests keep first-seen order**; manifest numbers are trimmed but compared exactly (they're identifiers).
- **Fail loud** — missing/blank/invalid config or line fields throw `InvalidArgumentException` rather than mis-bill.
- **Empty input** → empty result with a `0.00` total; **negative line amounts** are treated as credits.
- **Result contract** holds `Money` in-process and serializes to exact 2-decimal strings via `toArray()` for the matching engine.

## Time Spent

~20-30 minutes on the core deliverable (the service, value objects, and tests), AI-assisted. Roughly another hour went to the surrounding tooling and chores — CI, Rector, `essentials`, issue/PR templates, and docs.

## What I'd Do Next

- Extract the fee calculation into a composable rule pipeline (Open/Closed) — turn the fixed `base → fee → surcharge` sequence into ordered `FeeRule` steps so new fee types (tax, tiered fees, a second surcharge) extend the calculator without modifying it; pair it with a `FeeCalculator` interface for DI and swapability (Dependency Inversion).
- Expose the service over HTTP with `spatie/laravel-data` — request → input DTO (validated at the edge) → result → JSON — keeping the domain DTOs plain.
- Give `Money` a currency (or swap its internals for `brick/money`) if invoices ever span currencies — the value object already isolates that change.

## References

| Resource        | Link                                                                                                                   |
| --------------- | ---------------------------------------------------------------------------------------------------------------------- |
| Project Board   | [github.com/users/phojie/projects/4](https://github.com/users/phojie/projects/4?query=sort%3Aupdated-desc+state%3Aopen) |
| Domain Glossary | [CONTEXT.md](CONTEXT.md)                                                                                                |
| Decisions (ADR) | [docs/adr](docs/adr)                                                                                                    |

## Getting Started

```bash
composer install
php artisan test --compact
```

<!-- Badge Variables -->

[php-src]: https://img.shields.io/badge/PHP-8.4-777BB4?style=flat&colorA=18181B&colorB=777BB4
[php-href]: https://www.php.net
[laravel-src]: https://img.shields.io/badge/Laravel-13-FF2D20?style=flat&colorA=18181B&colorB=FF2D20
[laravel-href]: https://laravel.com
[ci-src]: https://img.shields.io/github/actions/workflow/status/phojie/ancon/ci.yml?branch=main&style=flat&colorA=18181B&colorB=CA8A04&label=CI
[ci-href]: https://github.com/phojie/ancon/actions/workflows/ci.yml
[license-src]: https://img.shields.io/badge/license-MIT-CA8A04?style=flat&colorA=18181B&colorB=CA8A04
[license-href]: ./LICENSE
