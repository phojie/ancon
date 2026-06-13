# Vendor Invoice Fee Calculation Service

A small Laravel service that rolls vendor waste-disposal **invoice lines** up into one billable total
per **manifest**, applying vendor-specific fees (a flat manifest fee + a configurable surcharge). The
output is a structured, exact-money result intended to feed a downstream matching engine.

> The implementation is walked through live — this README is just the overview, workflow, and how to run it.

## 📋 Project board

Work is tracked on the GitHub project board:

**https://github.com/users/phojie/projects/4?query=sort%3Aupdated-desc+state%3Aopen**

## 🧭 How this repo is worked

Every change — feature, CI, chore, docs — goes through the same lightweight flow:

1. **Issue first.** A `ready-for-agent` work-item issue describes the slice (🎯 What · ✅ Acceptance · 🚫 Out of scope · 🔗 Blocked by).
2. **Branch → PR.** One branch per issue; the PR body uses the repo PR template and `Closes #<n>`.
3. **CI must be green** before merge, then squash-merge.

Issue and PR templates live in [`.github/`](.github) so every ticket and PR stays consistent.

### Conventional Commits

Commits and PR titles follow [Conventional Commits](https://www.conventionalcommits.org/) — and this
history does too. Types used here:

| Type | Example |
|------|---------|
| `feat` | `feat: add vendor invoice fee calculation service` |
| `ci` | `ci: add GitHub Actions pipeline for Pest and Pint` |
| `chore` | `chore: add Rector automated refactoring via essentials` |
| `test` | `test: cover rounding, basis variant, grouping, validation` |
| `docs` | `docs: capture fee-calc domain model and money ADR` |

### CI gates

GitHub Actions runs on every push to `main` and every PR — three gates, all must pass:

- **Pint** — `vendor/bin/pint --test` (code style)
- **Rector** — `vendor/bin/rector process --dry-run` (automated refactor check)
- **Pest** — `php artisan test` (the suite)

## 🧱 Tech stack

- **PHP 8.4** · **Laravel 13**
- **Pest 4** — testing
- **Pint** — formatting · **Rector** — refactoring (via `nunomaduro/essentials`)
- **`nunomaduro/essentials`** — strict models, immutable dates, safe console, and other opinionated defaults
- Money math via PHP 8.4 `BcMath\Number` (exact decimals, no floats)

## 🚀 Getting started

```bash
composer install
php artisan test --compact     # run the suite
composer rector:dry            # refactor check (dry-run)
vendor/bin/pint --test         # style check
```

## 🗂️ Where things live

| Path | What |
|------|------|
| [`app/Services/FeeCalculator/`](app/Services/FeeCalculator) | The service and its value objects / DTOs |
| [`tests/Unit/FeeCalculatorServiceTest.php`](tests/Unit/FeeCalculatorServiceTest.php) | Worked example + edge-case tests |
| [`CONTEXT.md`](CONTEXT.md) | Domain glossary (manifest, surcharge basis, etc.) |
| [`docs/adr/`](docs/adr) | Architecture decision records (e.g. money precision) |

## 📝 Assumptions, decisions, next steps

Key decisions are recorded where they belong rather than restated here:

- **Domain language** → [`CONTEXT.md`](CONTEXT.md)
- **Money is exact, not float** (BCMath, round-half-up) → [ADR 0001](docs/adr/0001-bcmath-money-and-half-up-rounding.md)
- The original task assumptions are captured in the [fee-calc PRD](https://github.com/phojie/ancon/issues/1).

**Time spent:** ~2 hours on the core take-home (service + tests + docs), AI-assisted; the tooling
around it (CI, essentials, Rector, templates) was added afterward as follow-up slices.

**What's next:** make CI a required status check on `main`, add a PHP version matrix (8.3 + 8.4), and
adopt `spatie/laravel-data` at the HTTP/API boundary _if_ the service is ever exposed (the domain DTOs
stay plain).
