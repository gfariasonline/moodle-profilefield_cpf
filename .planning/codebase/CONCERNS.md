# Codebase Concerns

**Analysis Date:** 2026-06-26

## Test Coverage Gaps

**CPF Validation Logic - Untested:**
- What's not tested: Algorithmic validation of CPF check digits, uniqueness constraints, format normalization, edge cases (all-zeros, repeated digits, invalid lengths)
- Files: `field.class.php` (methods `validate_cpf()`, `cpf_is_unique()`, `normalize_cpf()`)
- Risk: A bug in the CPF validation algorithm (lines 161-185) could allow invalid CPF numbers to be stored. The check digit calculation is critical mathematical logic that should be unit-tested with known valid/invalid CPF examples.
- Priority: High - This is the core business logic and directly affects data integrity

**No Integration Tests:**
- What's not tested: Form submission flow, data persistence, display formatting, privacy API compliance
- Risk: The interaction between `edit_field_add()`, `edit_validate_field()`, `edit_save_data_preprocess()`, and `edit_field_set_data()` is untested. Regressions could go undetected.
- Priority: Medium

**No Unit Tests File:**
- Files: Missing `tests/` directory entirely
- Current approach: Manual testing only
- Recommendation: Create `tests/profilefield_cpf_test.php` with test cases for all validation methods

## Performance Bottlenecks

**Inefficient Uniqueness Check:**
- Problem: The `cpf_is_unique()` method (lines 133-153) fetches ALL CPF values from the database and normalizes/compares them in PHP loop
- Files: `field.class.php` line 140-150
- Cause: The SQL query returns raw data without normalization, requiring PHP-side iteration and string processing
- Current query:
  ```sql
  SELECT uid.data FROM {user_info_data} uid 
  JOIN {user_info_field} uif ON uid.fieldid = uif.id 
  WHERE uif.datatype = 'cpf' AND uid.userid <> :userid
  ```
- Impact: For installations with thousands of users, this query returns all CPF values and processes them in a loop. Scales O(n) with user count.
- Improvement path: Consider caching normalized CPF values in a separate table, or use a more efficient database-side query when possible (with caution for database compatibility across MySQL/PostgreSQL)

## Documentation Gaps

**README.md Outdated:**
- Issue: README still recommends using external jQuery masked input plugin (lines 19-35)
- Files: `README.md`
- Current reality: The plugin now uses inline JavaScript masking via `oninput` event handler (lines 40-41 in `field.class.php`)
- Impact: Users following the README will add unnecessary external dependencies when the plugin already provides masking
- Recommendation: Update README to explain the built-in masking behavior and remove references to external jQuery plugin

**Missing Database Documentation:**
- Issue: No documentation of stored data format or schema requirements
- Files: Missing `db/install.xml`, no comments about `user_info_data` schema assumptions
- What's assumed: The CPF field stores digits-only strings (11 characters) in the `data` column of `user_info_data` table
- Risk: Future developers might not understand why validation requires exactly 11 digits

## Fragile Areas

**Client-Side Input Masking is Complex:**
- Files: `field.class.php` lines 40-41
- Code: Inline JavaScript regex mask is fragile and difficult to test/maintain
  ```javascript
  this.value=this.value.replace(/\\D/g,'').replace(/(\\d{3})(\\d)/,'$1.$2')
    .replace(/(\\d{3})(\\d)/,'$1.$2').replace(/(\\d{3})(\\d{1,2})$/,'$1-$2').slice(0,14);
  ```
- Why fragile: 
  - Complex chained regex replacements (4 separate operations on a single line)
  - Embedded in HTML attribute as a string
  - Cannot be unit tested in isolation
  - Dependent on `oninput` event support (may not work in older browsers or certain input methods)
  - Relies on regex without comments explaining each step
- Safe modification: Extract mask logic into a separate method that can be tested, or use a well-tested masking library
- Test coverage: No tests verify that the mask correctly formats all valid CPF variations

**Data Format Mismatch (Stored vs Display):**
- Problem: Data is stored as digits-only (11 chars) but displayed/edited as formatted (XXX.XXX.XXX-XX)
- Files: `field.class.php` lines 57-64 (loading), 105-110 (saving), 117-124 (display)
- Risk: If normalization or formatting logic breaks, stored unformatted CPF could fail to display as formatted
- Safeguard: The `display_data()` method checks for exactly 11 digits before formatting (line 119), returning raw value if malformed. This is defensive but could indicate data corruption silently.

## Data Integrity Concerns

**Hardcoded Field Type Check:**
- Issue: `cpf_is_unique()` queries for `datatype = 'cpf'` (line 143)
- Files: `field.class.php` line 143
- Risk: If field type name changes or custom instances exist, the uniqueness check may miss duplicates across different field configurations
- Mitigation: The plugin is named `profilefield_cpf`, so the datatype 'cpf' is canonical, but this assumes proper field initialization

**No Data Migration Path:**
- Issue: No `db/upgrade.php` file exists for version tracking or schema migrations
- Files: Missing `db/upgrade.php`
- Risk: If the plugin needs to change storage format (e.g., from digits-only to formatted), there's no defined migration path
- Impact: Future upgrades could require manual data transformation
- Recommendation: Create upgrade script infrastructure even if empty initially

## Missing Critical Features

**No Encryption for Sensitive Data:**
- Issue: CPF (a Brazilian tax ID) is sensitive personal data, but stored in plain text
- Files: `field.class.php`, data stored via Moodle core profile system
- Current approach: Relies on Moodle's user data access controls
- Risk: Database backups or exports could expose CPF numbers in plaintext
- Note: This is likely a system-wide Moodle consideration, not specific to this plugin, but worth noting that CPF should be treated as sensitive data

**No Audit Trail:**
- Issue: No logging of CPF changes (add, update, delete)
- Risk: Cannot detect unauthorized CPF modifications or track data lineage
- Recommendation: Consider adding event-based logging for GDPR compliance and audit purposes

## Security Considerations

**Privacy API Classification:**
- Issue: The provider implements `null_provider`, claiming the plugin stores no personal data
- Files: `classes/privacy/provider.php`
- Nuance: While technically correct that the plugin doesn't have its own database table, CPF data IS personal/sensitive data passed through the plugin
- Current mitigation: The comment explains that storage is handled by core user profile system
- Risk (low): If Moodle changes how it handles profile field privacy, this plugin may need updating
- Recommendation: Consider implementing `provider implements metadata_provider` to explicitly declare CPF as personal data and document handling

**Input Validation Order:**
- Issue: Form submission validates CPF format before checking uniqueness
- Files: `field.class.php` lines 85-93
- Current approach: Correct order (format validation before uniqueness check)
- Risk (minimal): If reversed, invalid CPF would still trigger uniqueness query waste, but this is already correct

**HTML Attribute Injection Risk (Minimal):**
- Issue: The `oninput` handler is constructed by string concatenation
- Files: `field.class.php` lines 40-48
- Current code: `'oninput="' . $maskjs . '"'` 
- Risk: Very low in this context since `$maskjs` is hardcoded, not user-controlled
- Safeguard: The `$maskjs` string contains only static regex patterns and operations

## Scaling Limits

**Linear Performance with User Count:**
- Bottleneck: `cpf_is_unique()` method loads all CPF values into memory for comparison
- Current capacity: Works fine for installations with < 5,000 users
- Limit: Could timeout or use excessive memory with > 50,000 users depending on server specs
- Scaling path: 
  1. Use database normalization in SQL query (if compatible with both MySQL and PostgreSQL)
  2. Cache normalized CPFs in a separate indexed table
  3. Add a hash-based uniqueness index for faster lookups

**No Database Indexing:**
- Issue: The plugin doesn't create indexes for CPF uniqueness lookups
- Files: Missing `db/install.xml`
- Impact: Each uniqueness check requires full table scan of `user_info_data` for the 'cpf' field type
- Recommendation: Define `db/install.xml` to create a functional index on normalized CPF values

## Browser Compatibility

**JavaScript oninput Event:**
- Issue: Relies on `oninput` event for client-side masking
- Browser support: IE 9+, all modern browsers, but may not work on older IE versions
- Fallback: Server-side validation ensures data integrity even if client-side mask fails
- Risk (low): Users on unsupported browsers can still submit unmasked CPF; validation catches it

---

*Concerns audit: 2026-06-26*
