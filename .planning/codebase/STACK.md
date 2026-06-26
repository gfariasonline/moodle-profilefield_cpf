# Technology Stack

**Analysis Date:** 2026-06-26

## Languages

**Primary:**
- PHP 8.2+ - All application code, entry points, and field handlers
- JavaScript - Inline input masking for CPF formatting (client-side only, no external framework)

## Runtime

**Environment:**
- PHP 8.2 or 8.3 (minimum required by Moodle 4.5)
- Runs within Moodle instance

**Package Manager:**
- Not applicable - no external dependencies managed via Composer or npm

## Frameworks

**Core:**
- Moodle 4.5+ - LMS platform providing field API, database layer, form handling
  - Minimum version: 2024100700 (Moodle 4.5)
  - Supported versions: 4.05 to 5.02
  - Plugin type: `profilefield_cpf` (user profile field)

**Field Infrastructure:**
- `profile_field_base` - Moodle's abstract base class for custom user profile fields
- `profile_define_base` - Moodle's abstract base class for field definition/settings form

## Key Dependencies

**None declared.**

This plugin has no external dependencies. All functionality uses only:
- Moodle core profile field API
- Moodle core database API (`$DB`)
- Moodle core form API (`MoodleQuickForm`)
- Moodle core privacy API

## Configuration

**Environment:**
- No environment variables required
- No configuration files (.env, config.json, etc.)
- Settings configured via Moodle admin interface: Site admin > Users > Accounts > User profile fields

**Plugin Metadata:**
- `version.php` - Single configuration file defining:
  - `$plugin->version` - Release number (YYYYMMDDXX format)
  - `$plugin->requires` - Minimum Moodle version
  - `$plugin->supported` - Version support matrix
  - `$plugin->component` - Frankenstyle identifier: `profilefield_cpf`
  - `$plugin->maturity` - Stability level: `MATURITY_STABLE`
  - `$plugin->release` - Semantic version string (currently 2.0.0)

## Platform Requirements

**Development:**
- Moodle 4.5+ installation with database (MySQL/MariaDB or PostgreSQL)
- PHP CLI for running vendor tools (moodle-plugin-ci)
- moodle-plugin-ci for code quality checks

**Production:**
- Moodle 4.5, 5.0, 5.1, or 5.2 installation
- PHP 8.2 or 8.3
- Supported databases: MySQL 5.7+, MariaDB 10.2+, PostgreSQL 9.6+

## Code Style & Quality

**Linting:**
- Moodle Coding Standards (PSR-12 variant)
- Checked via moodle-plugin-ci phpcs

**Documentation:**
- PHPDoc required on all public methods and classes
- GPL v3 header required on all PHP files

**Build/Development Tools:**
- moodle-plugin-ci - Code quality, testing, and validation
  - phplint - PHP syntax validation
  - phpcs - Coding standards
  - phpdoc - Documentation standards
  - validate - Plugin structure validation
  - phpunit - Unit tests
  - behat - Acceptance tests (if added)

## No Build Process

This plugin requires no:
- JavaScript bundling or transpilation
- CSS preprocessing
- Asset minification
- Code compilation

Client-side masking is implemented via inline JavaScript in form attributes (`oninput` handler).

---

*Stack analysis: 2026-06-26*
