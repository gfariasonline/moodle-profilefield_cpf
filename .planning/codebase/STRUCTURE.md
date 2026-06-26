# Codebase Structure

**Analysis Date:** 2026-06-26

## Directory Layout

```
cpf/
├── version.php                          # Plugin metadata
├── field.class.php                      # User-facing profile field handler
├── define.class.php                     # Administrator field configuration handler
├── classes/
│   └── privacy/
│       └── provider.php                 # Privacy API compliance
├── lang/
│   ├── en/
│   │   └── profilefield_cpf.php        # English language strings
│   └── pt_br/
│       └── profilefield_cpf.php        # Brazilian Portuguese language strings
├── README.md                            # Installation and usage documentation
├── CLAUDE.md                            # Development guide (project instructions)
├── .gitignore                           # Git exclusion rules
└── .git/                                # Git repository
```

## Directory Purposes

**cpf/ (root):**
- Purpose: Plugin package directory (installed at `user/profile/field/cpf` in Moodle)
- Contains: Plugin entry points, configuration, and main logic
- Key files: `version.php`, `field.class.php`, `define.class.php`

**classes/:**
- Purpose: PSR-4 autoloadable classes (namespace `profilefield_cpf\`)
- Contains: Plugin utility classes and API implementations
- Key files: `privacy/provider.php`

**classes/privacy/:**
- Purpose: Privacy API implementation
- Contains: Data export/deletion compliance classes
- Key files: `provider.php` - Implements `null_provider` interface

**lang/:**
- Purpose: Localized user-visible strings
- Contains: Language-specific string files
- Key files: `en/profilefield_cpf.php`, `pt_br/profilefield_cpf.php`

**lang/en/:**
- Purpose: English interface strings
- Contains: Error messages, field labels, plugin name
- Key strings: `pluginname`, `invalidcpf`, `cpfexists`, `privacy:metadata`

**lang/pt_br/:**
- Purpose: Brazilian Portuguese interface strings
- Contains: Same keys as English, translated to Portuguese
- Key strings: `pluginname` = 'Campo de CPF', `invalidcpf` = 'CPF inválido', etc.

## Key File Locations

**Entry Points:**
- `version.php`: Plugin metadata (component, version, requirements, supported branches)
- `field.class.php`: Main runtime class (`profile_field_cpf`)
- `define.class.php`: Admin configuration class (`profile_define_cpf`)

**Configuration:**
- `version.php`: Version (2026050400), requires Moodle 4.5 (2024100700), supports 4.5-5.2
- `.gitignore`: Excludes development tool configurations and local state

**Core Logic:**
- `field.class.php`: Form rendering, validation, display (200 lines)
  - `edit_field_add()`: Renders form element with JavaScript mask
  - `edit_validate_field()`: Validates format and uniqueness
  - `edit_save_data_preprocess()`: Strips formatting before storage
  - `display_data()`: Formats for display
  - `validate_cpf()`: Checks mathematical validity (check digits)
  - `cpf_is_unique()`: Queries database for duplicates
  - `normalize_cpf()`: Extracts digits from string

**Privacy:**
- `classes/privacy/provider.php`: Declares null_provider (plugin doesn't store personal data)

**Internationalization:**
- `lang/en/profilefield_cpf.php`: English strings (error messages, plugin name)
- `lang/pt_br/profilefield_cpf.php`: Portuguese strings (same keys, translated)

**Documentation:**
- `README.md`: Installation, compatibility, features (CFP explanation in English and Portuguese)
- `CLAUDE.md`: Development guide for this specific project (Moodle plugin conventions)

## Naming Conventions

**Files:**
- Class files: `lowercase.class.php` for non-namespaced classes (`field.class.php`, `define.class.php`)
- Namespaced classes: Direct path under `classes/` (e.g., `classes/privacy/provider.php` = `profilefield_cpf\privacy\provider`)
- Language files: `{component}.php` in `lang/{lang_code}/` (e.g., `profilefield_cpf.php`)
- Configuration: `version.php` (Moodle standard)

**Directories:**
- Plugin root: `{type}_{pluginname}` not used for file paths, but conceptual (this is user profile field)
- Namespace dirs: Follow namespace hierarchy under `classes/` (e.g., `classes/privacy/` for `profilefield_cpf\privacy`)
- Language dirs: `lang/{moodle_lang_code}/` (e.g., `lang/en/`, `lang/pt_br/`)

**Classes:**
- Plugin field handler: `profile_field_{type}` (convention: `profile_field_cpf`)
- Plugin field definition: `profile_define_{type}` (convention: `profile_define_cpf`)
- Namespaced classes: Use CamelCase with full namespace prefix (e.g., `profilefield_cpf\privacy\provider`)

**Methods:**
- Form hooks (public): `edit_field_add()`, `edit_field_set_data()`, `edit_validate_field()`, `edit_save_data_preprocess()`, `display_data()`
- Private utilities: `validate_cpf()`, `normalize_cpf()`, `cpf_is_unique()` (lowercase_with_underscores)

**Language Keys:**
- Plugin identifier: `pluginname`
- Error messages: `invalidcpf`, `cpfexists`
- Privacy statement: `privacy:metadata`

## Where to Add New Code

**New Form Features (validation rules, custom display):**
- Primary location: `field.class.php`
- Approach: Add or override methods in `profile_field_cpf` class
- Examples:
  - New validation step: Add check in `edit_validate_field()`
  - Custom display formatting: Override `display_data()`
  - New form attributes: Modify `edit_field_add()`

**New Administrative Field Options:**
- Primary location: `define.class.php`
- Approach: Add form elements in `define_form_specific()` method
- Examples:
  - Toggle uniqueness enforcement: Add checkbox element
  - Custom default value: Already present
  - Field-specific settings: Add to this method

**New Plugin Behavior/Interfaces:**
- Primary location: `classes/` directory with appropriate subdirectory
- Approach: Create new class with proper namespace `profilefield_cpf\{subsystem}`
- Examples:
  - Event handler: `classes/event/handler.php` - namespace `profilefield_cpf\event`
  - Custom observer: `classes/observer.php` - namespace `profilefield_cpf`
  - Utility service: `classes/service.php` - namespace `profilefield_cpf`

**Tests:**
- Primary location: `tests/` directory (not yet created)
- Approach: Follow Moodle PHPUnit structure
- Examples:
  - Unit test: `tests/cpf_validator_test.php` - namespace `profilefield_cpf`
  - Feature test: `tests/behat/cpf_field.feature` - Behat file

**Localization:**
- Primary location: `lang/{moodle_lang_code}/{component}.php`
- Approach: Add new language files in `lang/` directory
- Examples:
  - Spanish: `lang/es/profilefield_cpf.php`
  - French: `lang/fr/profilefield_cpf.php`

**Documentation:**
- README.md: Installation, usage, compatibility
- CLAUDE.md: Development procedures for this project

## Special Directories

**classes/:**
- Purpose: PSR-4 autoloadable namespaced classes
- Generated: No
- Committed: Yes
- Uses: Moodle's class loader (auto-discovered via `classes/` directory structure)

**lang/:**
- Purpose: Localized interface strings
- Generated: No
- Committed: Yes
- Structure: `lang/{moodle_lang_code}/{component}.php` (must use correct Moodle language code)

**.git/:**
- Purpose: Version control repository
- Generated: Yes (by git init)
- Committed: No (excluded by convention)

**.planning/:**
- Purpose: GSD planning documents
- Generated: Yes (by gsd:map-codebase)
- Committed: No (excluded by .gitignore)

**.claude/:**
- Purpose: Claude-specific development memory and context
- Generated: Yes
- Committed: No (excluded by .gitignore)

---

*Structure analysis: 2026-06-26*
