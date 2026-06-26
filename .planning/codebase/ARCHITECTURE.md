# Architecture

**Analysis Date:** 2026-06-26

## Pattern Overview

**Overall:** Plugin-based extension system integrated with Moodle's core user profile field subsystem.

**Key Characteristics:**
- Extends base classes provided by Moodle's profile field infrastructure
- Handles the complete field lifecycle: admin configuration, form rendering, validation, and storage
- Uses Moodle's core `user_info_data` table (no custom database tables)
- Client-side form masking via inline JavaScript
- Server-side validation with dual checks: format validation + uniqueness enforcement

## Layers

**Field Definition Layer:**
- Purpose: Handles administrative configuration of the CPF field when created/edited in Site Admin
- Location: `define.class.php`
- Contains: `profile_define_cpf` class extending `profile_define_base`
- Depends on: Moodle's profile field definition framework
- Used by: Moodle Site Admin > User profile fields admin panel

**Field Runtime Layer:**
- Purpose: Manages all user-facing interactions with the CPF field (form rendering, validation, display)
- Location: `field.class.php`
- Contains: `profile_field_cpf` class extending `profile_field_base`
- Depends on: Moodle core form API, database access (`$DB`), string localization
- Used by: User edit profile form, profile display pages

**Privacy Compliance Layer:**
- Purpose: Declares privacy implications to Moodle's privacy framework
- Location: `classes/privacy/provider.php`
- Contains: `provider` class implementing `\core_privacy\local\metadata\null_provider`
- Depends on: Moodle privacy API interfaces
- Used by: Moodle privacy subsystem (GDPR/data export flows)

**Data Storage Layer:**
- Purpose: Persistent storage of CPF values
- Location: Core `user_info_data` table (managed by Moodle, not this plugin)
- Contains: User-submitted CPF digits stored as VARCHAR
- Schema: Established by Moodle core, plugin uses existing structure
- Accessed by: `profile_field_cpf` for uniqueness checks and data retrieval

## Data Flow

**Field Creation Flow:**

1. Admin navigates to Site Admin > User profile fields > New field
2. Moodle's profile field system discovers `profile_define_cpf` class
3. `define.class.php::profile_define_cpf::define_form_specific()` adds the default-value field
4. Admin saves the field configuration to `user_info_field` table

**User Profile Edit Flow:**

1. User accesses profile edit page
2. Moodle discovers `profile_field_cpf` class via plugin registry
3. `field.class.php::edit_field_add()` renders HTML text input with inline JavaScript mask
   - JavaScript applies format mask: `XXX.XXX.XXX-XX` as user types
   - Strips non-digits, applies formatting via regex replacement
4. If editing existing profile, `edit_field_set_data()` formats stored digits (11 chars) to display format
5. User submits form
6. `edit_validate_field()` validates in this order:
   - Checks if field is empty (optional by default)
   - Calls `validate_cpf()` to check format (11 digits, valid check digits, not all identical)
   - Calls `cpf_is_unique()` to verify no other user has this CPF
   - Returns error array if any check fails
7. If validation passes, `edit_save_data_preprocess()` strips formatting and stores only 11 digits
8. Moodle saves to `user_info_data` table

**Profile Display Flow:**

1. User profile page is displayed
2. `display_data()` is called with stored 11 digits
3. Formats as `XXX.XXX.XXX-XX` for display
4. Returns formatted string or raw value if invalid length

**State Management:**
- No persistent state within plugin (stateless)
- All state stored in Moodle's core `user_info_data` and `user_info_field` tables
- CPF values are user profile attributes, managed by core user subsystem

## Key Abstractions

**CPF Validation:**
- Purpose: Ensures mathematically valid Brazilian CPF numbers
- Examples: `field.class.php::validate_cpf()` (lines 161-185)
- Pattern: Implements official CPF check-digit algorithm
  - Pad input to 11 digits with zeros
  - Reject sequences of identical digits (e.g., 00000000000)
  - Calculate first check digit (position 9) using weighted sum
  - Calculate second check digit (position 10) using weighted sum
  - Return true only if both check digits match

**CPF Normalization:**
- Purpose: Extract digits from formatted or raw CPF strings
- Examples: `field.class.php::normalize_cpf()` (lines 193-198)
- Pattern: Strip all non-digit characters, return empty string for null input
- Used by: validation, uniqueness check, display formatting

**CPF Uniqueness Check:**
- Purpose: Prevent duplicate CPF values across user accounts
- Examples: `field.class.php::cpf_is_unique()` (lines 133-153)
- Pattern: 
  - Query `user_info_data` joined with `user_info_field`
  - Filter by field datatype = 'cpf' and exclude current user
  - Normalize and compare each stored CPF against submitted CPF
  - Return false if duplicate found

**Form Element Extension:**
- Purpose: Customize Moodle form rendering for CPF input
- Examples: `field.class.php::edit_field_add()` (lines 39-50)
- Pattern: 
  - Calls `$mform->addElement()` with text input type
  - Adds inline JavaScript for client-side masking
  - Sets form validation type to `PARAM_TEXT`
  - Limits display width to 14 characters (formatted length)

## Entry Points

**Plugin Discovery:**
- Location: Auto-discovered via `user/profile/field/cpf` directory structure
- Triggers: Moodle bootstrap when profile field plugin system initializes
- Responsibilities: Provides `profile_field_cpf` and `profile_define_cpf` classes

**Profile Field Instantiation:**
- Location: `field.class.php`
- Triggers: Moodle creates instance when rendering user profile forms
- Responsibilities: 
  - Extends `profile_field_base` to implement profile field contract
  - Implements required methods: `edit_field_add()`, `edit_field_set_data()`, `edit_validate_field()`, `edit_save_data_preprocess()`, `display_data()`

**Field Definition Form:**
- Location: `define.class.php`
- Triggers: Admin visits field settings page
- Responsibilities:
  - Extends `profile_define_base` to implement field definition contract
  - Implements `define_form_specific()` to add default-value input to admin form

**Privacy API Hook:**
- Location: `classes/privacy/provider.php`
- Triggers: Moodle privacy subsystem during data exports or GDPR requests
- Responsibilities:
  - Declares that plugin is a null_provider (doesn't store personal data itself)
  - Returns localized reason string via `get_reason()`

## Error Handling

**Strategy:** Validation-first approach with user-facing error messages.

**Patterns:**
- `edit_validate_field()` returns associative array of errors keyed by field name
- Empty array signals successful validation
- Each error message is localized via `get_string()` calls
  - `'invalidcpf'` - format/checksum validation failed
  - `'cpfexists'` - uniqueness check failed
- Errors rendered inline on the form by Moodle's form system
- No try-catch blocks (no exceptions thrown)
- Database queries in `cpf_is_unique()` use prepared statements via `$DB->get_fieldset_sql()` to prevent SQL injection

## Cross-Cutting Concerns

**Logging:** Not implemented. Profile field operations are logged by Moodle core, not this plugin.

**Validation:** Implemented at multiple levels:
- Client-side: JavaScript input mask (UX enhancement, not security)
- Server-side format validation: `validate_cpf()` checks mathematical validity
- Database-level: Uniqueness constraint via manual query (not database constraint)

**Authentication:** Not required. Field is part of user profiles, access controlled by Moodle's profile edit capability system.

**Internationalization:** All user-visible strings in `lang/en/profilefield_cpf.php` and `lang/pt_br/profilefield_cpf.php`:
- `pluginname` - field type display name
- `invalidcpf` - validation error message
- `cpfexists` - uniqueness error message
- `privacy:metadata` - privacy statement

**Data Format:** 
- Storage: 11 digits only (no formatting)
- Display: `XXX.XXX.XXX-XX` (formatted)
- Form input: Accepts formatted or unformatted via inline JavaScript mask
- Database: Stored as VARCHAR in `user_info_data.data` column

---

*Architecture analysis: 2026-06-26*
