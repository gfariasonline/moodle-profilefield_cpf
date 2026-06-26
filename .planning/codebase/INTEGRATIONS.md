# External Integrations

**Analysis Date:** 2026-06-26

## APIs & External Services

**None.**

This plugin makes no calls to external APIs or third-party services. All validation, formatting, and storage operations use only Moodle's built-in APIs.

## Data Storage

**Databases:**
- Moodle core database only (MySQL/MariaDB or PostgreSQL)
  - Connection: Via Moodle's global `$DB` object
  - Tables accessed:
    - `mdl_user_info_data` - Stores CPF values for users (core Moodle table managed by profile field subsystem)
    - `mdl_user_info_field` - Stores field metadata (core Moodle table, read-only for this plugin)
  - Client: Moodle's database abstraction layer (`$DB`)
  - No separate database connections or configuration required

**File Storage:**
- Not applicable - plugin stores no files

**Caching:**
- Not applicable - plugin uses no caching layer

## Authentication & Identity

**Auth Provider:**
- Moodle's built-in user authentication
  - CPF field is integrated into Moodle's user profile subsystem
  - User access control handled by Moodle core
  - No separate auth integration required

## Monitoring & Observability

**Error Tracking:**
- Not integrated - uses Moodle's error handling and logging

**Logs:**
- Moodle's standard logging system
  - Form submission events logged to `mdl_logstore_standard_log`
  - Validation errors returned via Moodle form error display
  - No external logging services used

## CI/CD & Deployment

**Hosting:**
- Not applicable - plugin runs within existing Moodle instance

**CI Pipeline:**
- Not configured - no GitHub Actions or CI service defined
- moodle-plugin-ci available for local testing

## Environment Configuration

**Required env vars:**
- None - plugin requires no environment variables

**Secrets location:**
- Not applicable - plugin stores no secrets
- Sensitive data (CPF numbers) handled identically to other user profile fields

## Webhooks & Callbacks

**Incoming:**
- None - plugin provides no webhook endpoints

**Outgoing:**
- None - plugin makes no outbound webhook calls

## Privacy & Data Handling

**Data Stored:**
- CPF values (11-digit Brazilian tax ID)
  - Stored in `mdl_user_info_data` table under profile field subsystem
  - Stored as formatted string: `XXX.XXX.XXX-XX` (with dots and dash)
  - Normalized to digits-only internally for validation and uniqueness checks

**Privacy API Implementation:**
- `classes/privacy/provider.php` - Implements `null_provider` interface
  - Declares that plugin stores no personal data directly
  - CPF data managed by core user profile subsystem (outside plugin scope)
  - GDPR/Privacy API compliance: data deletion handled by Moodle core

**User Data Access:**
- `field.class.php::cpf_is_unique()` - Queries `mdl_user_info_data` for uniqueness validation
  - Performs SQL: `SELECT uid.data FROM {user_info_data} uid JOIN {user_info_field} uif...`
  - Excludes current user from duplicates check
  - No user-facing data export/import methods

---

*Integration audit: 2026-06-26*
