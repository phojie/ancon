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

## Status

| Branch | CI                       |
| ------ | ------------------------ |
| `main` | [![ci][ci-src]][ci-href] |

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
