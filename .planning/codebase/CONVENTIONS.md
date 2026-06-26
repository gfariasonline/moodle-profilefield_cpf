# Coding Conventions

**Analysis Date:** 2026-06-26

## Naming Patterns

**Files:**
- Class files: `lowercase.class.php` (e.g., `define.class.php`, `field.class.php`)
- Namespaced classes: `ClassName.php` under `classes/` directories (e.g., `classes/privacy/provider.php`)
- Language files: `{frankenstyle}.php` in `lang/{lang_code}/` (e.g., `lang/en/profilefield_cpf.php`)

**Functions:**
- Methods and functions: `lowercase_with_underscores` (e.g., `edit_validate_field()`, `normalize_cpf()`, `cpf_is_unique()`)
- Private methods: prefixed with `private` keyword, use underscores (e.g., `private function validate_cpf()`)
- Public methods: no prefix, inherited from base classes

**Variables:**
- Instance/local variables: `lowercase_with_underscores` (e.g., `$maskjs`, `$digits`, `$cpf`, `$userid`)
- Array keys: lowercase with underscores or inline PHP constants (e.g., `['userid' => $userid]`, `PARAM_TEXT`)
- Moodle globals: `$DB`, `$CFG` (uppercase as per Moodle standard)

**Types:**
- Classes: `CamelCase` (e.g., `profile_define_cpf`, `profile_field_cpf`)
- Namespaced classes: `CamelCase` in namespace (e.g., `class provider` in `namespace profilefield_cpf\privacy`)
- Constants: `UPPER_CASE` with `PARAM_` prefix for field types (e.g., `PARAM_TEXT`, `MOODLE_INTERNAL`)

## Code Style

**Formatting:**
- Indentation: 4 spaces (no tabs)
- Line length: No hard limit, but keep readable (typically 100-120 chars)
- Opening braces: Same line for control structures (Moodle style)
- Spacing: Single space around operators, no space after function name before parentheses

**Linting:**
- Tool: moodle-plugin-ci (with phpcs using Moodle ruleset)
- Key rules enforced:
  - GPL license header on all PHP files
  - PHPDoc on all public methods and classes
  - No trailing whitespace
  - Language file string ordering (phpcs moodle.Files.LangFilesOrdering)
- Run locally: `moodle-plugin-ci phpcs --max-warnings 0`

## Import Organization

**Order:**
1. License header block (lines 2-15)
2. File PHPDoc block
3. `defined('MOODLE_INTERNAL') || die();` (required for all plugin files)
4. `namespace` declaration (if using PSR-4, only in `classes/` folder)
5. Class declaration

**Path Aliases:**
- No path aliases used; Moodle uses global `$CFG` for root config
- Relative includes: `require(__DIR__ . '/../../config.php');`
- Local files: `require_once(__DIR__ . '/locallib.php');`

## Error Handling

**Patterns:**
- Validation: Early return pattern with empty array for no errors
  ```php
  if (!isset($usernew->{$this->inputname})) {
      return [];
  }
  ```
- Database checks: Use `$DB->get_fieldset_sql()` to iterate results
- String conversion: Explicit `(string)` cast before regex operations

**Exception Handling:**
- Not used in current codebase; validation errors returned as associative arrays
- For database operations: exceptions bubble up from Moodle's API

## Logging

**Framework:** Not used in current codebase
- PHP `error_log()` available if needed
- Moodle debugging: Use `mtrace()` for CLI output during upgrades/tasks
- User feedback: Use `get_string()` for localized error messages

## Comments

**When to Comment:**
- PHPDoc above all classes, public methods, and properties
- Inline comments for complex logic (e.g., CPF validation algorithm)
- Explain "why", not "what" (avoid obvious comments like `// increment counter`)

**JSDoc/TSDoc:**
- Not applicable (PHP codebase, no JavaScript)
- PHPDoc standard: `@param`, `@return`, `@package`, `@copyright`, `@license` are mandatory

**PHPDoc Format:**
```php
/**
 * Validates a CPF number by checking its two verification digits.
 *
 * @param string $cpf Digits-only CPF (11 chars).
 * @return bool True if mathematically valid.
 */
private function validate_cpf($cpf) {
    // ...
}
```

## Function Design

**Size:**
- Keep methods focused on single responsibility
- Example: `validate_cpf()` validates only CPF format; `cpf_is_unique()` checks uniqueness separately

**Parameters:**
- Type-hint when possible (string, int, bool, stdClass)
- Return type declarations (PHP 7.0+): `: string`, `: bool`, `: array`
- Default to early returns for validation logic

**Return Values:**
- For validation: return empty array (truthy) on success, array with errors (truthy) on failure
- For checks: return `bool` (true/false)
- For data operations: return formatted string or null

Example from `edit_validate_field()`:
```php
public function edit_validate_field($usernew) {
    $errors = [];
    
    if (!isset($usernew->{$this->inputname})) {
        return $errors;  // Early return, no errors
    }
    
    $cpf = $this->normalize_cpf($usernew->{$this->inputname});
    
    if ($cpf === '') {
        return $errors;  // Early return
    }
    
    if (!$this->validate_cpf($cpf)) {
        $errors[$this->inputname] = get_string('invalidcpf', 'profilefield_cpf');
        return $errors;  // Return with error
    }
    
    // More checks...
    
    return $errors;
}
```

## Module Design

**Exports:**
- Classes inherit from Moodle base classes: `profile_define_base`, `profile_field_base`, `\core_privacy\local\metadata\null_provider`
- Methods override parent abstract methods to provide implementation

**Barrel Files:**
- Not used; plugin uses single-file classes
- Privacy provider is in `classes/privacy/provider.php` (required structure for Privacy API)

## String Handling

**Localization:**
- All user-facing strings use `get_string('key', 'profilefield_cpf')`
- Language files: `lang/{lang_code}/profilefield_cpf.php`
- Keys: lowercase with underscores (e.g., `'invalidcpf'`, `'cpfexists'`, `'privacy:metadata'`)

**Formatting:**
- String concatenation: `'text' . $var . 'more'`
- Multi-line strings: use `.` operator on separate lines:
  ```php
  $string['privacy:metadata'] = 'The CPF profile field plugin does not store any personal data itself; '
                              . 'CPF values are stored by the core user profile subsystem.';
  ```

## Moodle-Specific Patterns

**Capabilities:**
- Format: `{frankenstyle}:{action}` (e.g., `profilefield_cpf:manage`)
- Check: `require_capability()` or `has_capability()`

**Database Access:**
- Always use `global $DB;` to get Moodle's database abstraction layer
- Use prepared queries with named placeholders: `$DB->get_fieldset_sql($sql, ['userid' => $userid])`

**User/Profile Objects:**
- Instance variable `$this->inputname` contains the field's form element name
- Instance variable `$this->field` contains the field definition (stdClass)
- Instance variable `$this->data` contains the user's current value for this field

---

*Convention analysis: 2026-06-26*
