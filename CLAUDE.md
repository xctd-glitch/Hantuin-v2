# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

SRP (Smart Redirect Platform) v2.1 — a PHP 8.3+ traffic routing and decision system for affiliate marketing. Routes visitors based on country, device, IP, and VPN detection with conversion tracking via postbacks. Designed for shared hosting (cPanel/Apache).

## Commands

```bash
# Install dependencies
composer install

# Run full quality gate (all checks below in sequence)
composer run qa

# Individual checks
composer run test           # PHPUnit (--fail-on-warning)
composer run stan           # PHPStan static analysis
composer run cs             # PHP_CodeSniffer (PSR-12)
composer run fix:check      # PHP-CS-Fixer dry-run
composer run check:no-arrow # Enforce no arrow functions

# Dev server
php -S localhost:8000 -t public_html
```

## Architecture

**Layered vanilla PHP** — no framework. Zero external production dependencies; only PHP extensions required.

```
public_html/          Web root (entry points: index.php, decision.php, api.php, postback.php)
src/
  Config/             Bootstrap, Database (auto-schema), Environment (.env), Cache (Redis→Memcached→APCu→noop)
  Controllers/        Request handlers — all static methods
  Models/             Data access + business logic (Settings, TrafficLog, Conversion, SrpClient, Validator)
  Middleware/         Session, SecurityHeaders
  Views/              PHP templates + components/
cron/                 Scheduled tasks (backup, cleanup, purge, health-check)
schema.sql            Full MySQL schema with safe migration logic
entry.php             Client-side entry point (separate tracking domain)
```

**Request flow:** Entry point → `Config\Bootstrap` (autoloader + env) → Controller → Model/Database → Response

**Key controllers:**
- `DecisionController` — core routing logic (bot detection, VPN check, country filter, device targeting)
- `PublicApiController` — REST API v1 (stats, logs, analytics, conversions, settings)
- `AuthController` — dual-role auth (admin full access, viewer read-only)

**Database:** Single-row `settings` table for config, `traffic_logs` with write-queue batching, `conversions` for postback tracking. Schema auto-creates missing tables on connection.

**Multi-domain:** Brand domain serves dashboard (`public_html/`), tracking domain uses `entry.php`.

## Code Conventions

- `declare(strict_types=1)` in all files
- PSR-4 autoloading: `SRP\` → `src/`
- PSR-12 code style
- **No arrow functions** (enforced by `scripts/check-no-arrow.php`)
- Static factory/facade pattern for config classes (`Database::getConnection()`, `Environment::get()`, `Cache::get()`)
- Full type hints on all parameters and return types
- Prepared statements for all SQL (never string interpolation)
- PHPDoc `@return array{key:type}` for typed array shapes
- Documentation language is Indonesian (Bahasa Indonesia)
