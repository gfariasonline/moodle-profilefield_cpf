# Architecture Research

**Domain:** Moodle AMD JavaScript integration — profile field plugin
**Researched:** 2026-06-26
**Confidence:** HIGH (verified against Moodle 4.5 source code and official docs)

## Standard Architecture

### System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     PHP — Server Side                           │
│  field.class.php::edit_field_add()                              │
│     → $mform->addElement('text', 'profile_field_cpf', ...)      │
│       → Moodle renders: <input id="id_profile_field_cpf">       │
│     → $PAGE->requires->js_call_amd(                             │
│         'profilefield_cpf/cpf_mask', 'init',                    │
│         ['id_profile_field_cpf']                                │
│       )                                                         │
│       → Queues AMD call for page footer                         │
└──────────────────────────┬──────────────────────────────────────┘
                           │ HTTP response (page HTML + queued JS)
┌──────────────────────────▼──────────────────────────────────────┐
│                   Browser — Client Side                         │
│                                                                 │
│  RequireJS (Moodle's AMD loader)                                │
│     → loads amd/build/cpf_mask.min.js                          │
│       → loads amd/build/cleave.min.js  (runtime dependency)    │
│     → calls cpf_mask.init('id_profile_field_cpf')              │
│       → document.getElementById('id_profile_field_cpf')        │
│       → new Cleave(element, {                                   │
│           numericOnly: true,                                    │
│           blocks: [3, 3, 3, 2],                                 │
│           delimiters: ['.', '.', '-']                           │
│         })                                                      │
└─────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Notes |
|-----------|----------------|-------|
| `field.class.php::edit_field_add()` | Renders form element; queues AMD call | PHP-side entry point |
| `amd/src/cpf_mask.js` | ESM module — receives fieldId, initializes Cleave on DOM element | Author this file |
| `amd/src/cleave.js` | Cleave.js 1.6.0 UMD source — local copy of dist file | Copy from npm dist, do not modify |
| `amd/build/cpf_mask.min.js` | Grunt output — served to browser by RequireJS | Generated, commit required |
| `amd/build/cleave.min.js` | Grunt output — served to browser by RequireJS | Generated, commit required |
| `thirdpartylibs.xml` | Declares cleave.js as third-party — tells grunt ignorefiles to skip ESLint on it | Author this file |

## Recommended Project Structure

```
cpf/                                    # plugin root (user/profile/field/cpf/)
├── amd/
│   ├── src/
│   │   ├── cpf_mask.js                 # ESM AMD module — AUTHOR
│   │   └── cleave.js                   # Cleave.js 1.6.0 dist — COPY VERBATIM
│   └── build/
│       ├── cpf_mask.min.js             # grunt output — COMMIT
│       ├── cpf_mask.min.js.map         # grunt output — COMMIT
│       ├── cleave.min.js               # grunt output — COMMIT
│       └── cleave.min.js.map           # grunt output — COMMIT
├── thirdpartylibs.xml                  # third-party declarations — AUTHOR
├── field.class.php                     # modify edit_field_add() — MODIFY
└── ...                                 # existing files unchanged
```

### Structure Rationale

- **amd/src/:** Moodle's prescribed location for AMD source files. Grunt auto-discovers all `.js` files here when running from the Moodle root. Each file maps 1-to-1 to an output file in `amd/build/`.
- **amd/build/:** Grunt-generated output. Moodle (and RequireJS) serves ONLY from `amd/build/`, never from `amd/src/`. Files here must be committed.
- **thirdpartylibs.xml:** Required to exclude cleave.js from ESLint during `grunt ignorefiles`. Without it, the grunt CI check fails on third-party code style errors.
- **No plugin-level package.json or Gruntfile.js needed:** Moodle's root grunt auto-discovers the plugin via `lib/components.json` → `user/profile/field` path → glob `*/version.php`. The build uses the Moodle root's node_modules.

## Architectural Patterns

### Pattern 1: js_call_amd with DOM element ID

**What:** PHP queues an AMD module call that receives the DOM element id as a string argument.
**When to use:** Anytime server-side code needs to initialize a JS behavior on a specific rendered element.
**Trade-offs:** Simple and idiomatic; element must exist in DOM when init() fires (it will, since AMD calls are injected at page footer).

**Verified from:** `lib/form/autocomplete.php` (core Moodle) and `lib/form/filetypes.php` (core Moodle).

**PHP (in edit_field_add):**
```php
public function edit_field_add($mform) {
    global $PAGE;

    $mform->addElement(
        'text',
        $this->inputname,
        format_string($this->field->name),
        'maxlength="14" size="14"'
    );
    $mform->setType($this->inputname, PARAM_TEXT);

    // Moodle auto-generates DOM id as 'id_' + element_name.
    // For element named 'profile_field_cpf': id = 'id_profile_field_cpf'.
    $PAGE->requires->js_call_amd(
        'profilefield_cpf/cpf_mask',
        'init',
        ['id_' . $this->inputname]
    );
}
```

**JavaScript (amd/src/cpf_mask.js):**
```javascript
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software... [GPL header]

/**
 * CPF input mask module — initializes cleave.js on the CPF form field.
 *
 * @module     profilefield_cpf/cpf_mask
 * @copyright  2026 [Author]
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Cleave from 'profilefield_cpf/cleave';

/**
 * Initialize the CPF mask on the given form element.
 *
 * @param {string} fieldId The DOM element id of the CPF input.
 */
export const init = (fieldId) => {
    const element = document.getElementById(fieldId);
    if (!element) {
        return;
    }
    new Cleave(element, {
        numericOnly: true,
        blocks: [3, 3, 3, 2],
        delimiters: ['.', '.', '-'],
    });
};
```

### Pattern 2: Bundling a UMD third-party library locally

**What:** Place the third-party library's dist file verbatim in `amd/src/`. Declare it in `thirdpartylibs.xml`. Grunt processes it the same as any other AMD file.
**When to use:** When a CDN is not acceptable (offline instances) and the library is stable.
**Trade-offs:** Increases committed file size. Build step re-minifies already-minified code, which is acceptable.

**Verified from:** `lib/amd/src/chartjs-lazy.js` (Chart.js 4.4.2 bundled verbatim in Moodle core). The built `lib/amd/build/chartjs-lazy.min.js` has `define("core/chartjs-lazy", factory)` injected by Babel.

**amd/src/cleave.js:**
```
Copy verbatim from: node_modules/cleave.js/dist/cleave.js (or the npm package dist/cleave.js)
Do NOT use the minified version (cleave.min.js) — grunt will minify it.
```

**thirdpartylibs.xml (plugin root):**
```xml
<?xml version="1.0"?>
<libraries>
    <library>
        <location>amd/src/cleave.js</location>
        <name>Cleave.js</name>
        <version>1.6.0</version>
        <license>MIT</license>
        <licenseversion>1.0</licenseversion>
        <repository>https://github.com/nosir/cleave.js</repository>
    </library>
</libraries>
```

### Pattern 3: Moodle Grunt AMD build process

**What:** Moodle's grunt `amd` task runs three sub-tasks: `ignorefiles` → `eslint:amd` → `rollup`.
**When to use:** Always. This is the only build tool that produces byte-identical output to what moodle-plugin-ci compares against.

**Build flow:**
```
grunt amd (run from Moodle root OR from plugin's amd/ directory)
  ├── ignorefiles
  │     reads all thirdpartylibs.xml in the installation
  │     generates .eslintignore (excludes node_modules/, build/, third-party paths)
  │
  ├── eslint:amd
  │     lints amd/src/*.js
  │     cleave.js is skipped (listed in thirdpartylibs.xml → .eslintignore)
  │
  └── rollup (with Babel plugins)
        processes each amd/src/*.js independently (treeshake: false)
        Babel transform-es2015-modules-amd-lazy: converts ESM import/export to AMD
        Babel babel-plugin-add-module-to-define: injects module name into define()
          cpf_mask.js  → define ('profilefield_cpf/cpf_mask', [...], function(...){})
          cleave.js    → define ('profilefield_cpf/cleave', [...], factory)
        terser: minifies output
        Output:
          amd/src/cpf_mask.js  → amd/build/cpf_mask.min.js + cpf_mask.min.js.map
          amd/src/cleave.js    → amd/build/cleave.min.js  + cleave.min.js.map
```

**Run from Moodle root (recommended):**
```bash
cd /path/to/moodle
npx grunt amd
# or target only this plugin's AMD directory:
npx grunt amd --root=user/profile/field/cpf/amd
```

**Run from plugin's amd/ directory (alternative):**
```bash
cd /path/to/moodle/user/profile/field/cpf/amd
npx grunt amd
```

## Data Flow

### AMD Module Load Flow

```
Browser receives page HTML
    → RequireJS finds queued AMD call: profilefield_cpf/cpf_mask::init
    → RequireJS loads: [wwwroot]/user/profile/field/cpf/amd/build/cpf_mask.min.js
        → cpf_mask.min.js declares dependencies: ['profilefield_cpf/cleave']
        → RequireJS loads: [wwwroot]/user/profile/field/cpf/amd/build/cleave.min.js
            → cleave.min.js registers as AMD module 'profilefield_cpf/cleave'
        → RequireJS calls cpf_mask init(fieldId)
            → document.getElementById('id_profile_field_cpf') → <input> element
            → new Cleave(element, {blocks:[3,3,3,2], delimiters:['.','.','-']})
                → input now formats as XXX.XXX.XXX-XX on keydown
```

### moodle-plugin-ci grunt Check Flow

```
moodle-plugin-ci grunt
    → detects plugin has no Gruntfile.js → runs from Moodle root
    → backs up current amd/build/ files
    → runs grunt amd (ignorefiles + eslint:amd + rollup)
    → compares new amd/build/ against backup
    → PASS: files match committed versions
    → FAIL: "File is stale and needs to be rebuilt" if mismatch
```

## Scaling Considerations

Not applicable for this integration — it's a form widget with negligible performance impact. The only scale concern is Moodle's AMD loader adding a second HTTP request for `cleave.min.js` the first time. RequireJS caches modules, so this is a one-time cost per page.

## Anti-Patterns

### Anti-Pattern 1: Using esbuild instead of grunt

**What people do:** Use esbuild (faster alternative) to build AMD modules.
**Why it's wrong:** esbuild output does not byte-match grunt output. moodle-plugin-ci rebuilds with grunt and compares. Any difference triggers "File is stale" failure.
**Do this instead:** Always use Moodle root's grunt. The build is slow but produces the only acceptable output.

**Source:** The esbuild guide itself states: "esbuild output won't byte-match [grunt output], potentially flagging mismatched files" during moodle.org automated checks.

### Anti-Pattern 2: Not committing amd/build/ files

**What people do:** Treat `amd/build/` as a generated artifact and add it to `.gitignore`.
**Why it's wrong:** Moodle (and RequireJS) only serves files from `amd/build/`. In production, there is no build step. moodle-plugin-ci will also fail the grunt check.
**Do this instead:** Commit all four build artifacts: `cpf_mask.min.js`, `cpf_mask.min.js.map`, `cleave.min.js`, `cleave.min.js.map`.

### Anti-Pattern 3: Omitting thirdpartylibs.xml for cleave.js

**What people do:** Skip `thirdpartylibs.xml` and just drop cleave.js in `amd/src/`.
**Why it's wrong:** The `grunt ignorefiles` step adds third-party paths to `.eslintignore`. Without the declaration, ESLint will scan cleave.js and fail on style violations in third-party code.
**Do this instead:** Always declare third-party libraries in `thirdpartylibs.xml` before running grunt.

### Anti-Pattern 4: Setting a custom id on the form element

**What people do:** Add `id="..."` explicitly in the `addElement()` attributes string (as the existing code does with `id="profile_field_cpf"`).
**Why it's wrong:** Moodle already auto-generates `id="id_profile_field_cpf"`. Explicitly setting an id overrides this and creates a non-standard id that bypasses Moodle's namespacing. It also risks duplicates if the field appears more than once.
**Do this instead:** Remove the explicit id attribute. Let Moodle generate `id_<elementname>`. Compute the id in PHP as `'id_' . $this->inputname` and pass it to `js_call_amd()`.

**Evidence:** Core Moodle form elements (`autocomplete.php`, `filetypes.php`) all use `$this->getAttribute('id')` after Moodle generates it, never override it manually.

### Anti-Pattern 5: Passing the Cleave instance back to PHP or storing it in the DOM

**What people do:** Try to reference or update the Cleave instance after initialization.
**Why it's wrong:** Cleave.js manages its own internal state. No lifecycle management is needed for a simple mask.
**Do this instead:** Create the instance in `init()` and let it self-manage. No cleanup needed for profile forms (single-page lifecycle).

## Integration Points

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| PHP → JS | `js_call_amd('profilefield_cpf/cpf_mask', 'init', ['id_profile_field_cpf'])` | Only string argument (the DOM id) — keeps payload well under 1KB warning threshold |
| cpf_mask → cleave | ESM import `from 'profilefield_cpf/cleave'` → AMD runtime dependency | RequireJS loads cleave lazily when cpf_mask is first required |
| cleave → DOM | `document.getElementById(fieldId)` → HTMLInputElement | Direct DOM reference — no jQuery, no events API |

### Moodle Subsystem Integration

| Subsystem | How It Interacts | Notes |
|-----------|-----------------|-------|
| `$PAGE->requires` | Queues AMD calls; outputs them in page footer | Available via `global $PAGE` in any PHP method called during page render |
| RequireJS (Moodle's AMD loader) | Resolves module names to file paths; loads `amd/build/*.min.js` | Module name `profilefield_cpf/cleave` maps to `user/profile/field/cpf/amd/build/cleave.min.js` |
| Grunt build | Processes `amd/src/` → `amd/build/`; injects module names via Babel plugin | Must run from Moodle root to get correct module name resolution via `components.json` |

## Sources

- Moodle JavaScript Modules (5.2): https://moodledev.io/docs/5.2/guides/javascript/modules — ESM format, js_call_amd usage, DOM targeting, grunt build (HIGH confidence)
- Moodle source: `lib/form/autocomplete.php` — production example of js_call_amd from a form element passing `'#' . $id` (HIGH confidence, verified in Moodle 4.5 source)
- Moodle source: `lib/form/filetypes.php` — production example passing raw element id to js_call_amd (HIGH confidence, verified in Moodle 4.5 source)
- Moodle source: `lib/amd/src/chartjs-lazy.js` + `lib/amd/build/chartjs-lazy.min.js` — production example of third-party UMD library bundled in amd/src/ (HIGH confidence, verified in Moodle 4.5 source)
- Moodle source: `.grunt/tasks/javascript.js` — grunt amd task definition: ignorefiles → eslint:amd → rollup (HIGH confidence, verified in Moodle 4.5 source)
- Moodle source: `.grunt/babel-plugin-add-module-to-define.js` — how module names are injected into define() calls (HIGH confidence, verified in Moodle 4.5 source)
- Moodle source: `.grunt/components.js` — auto-discovery of plugin AMD dirs via components.json (HIGH confidence, verified in Moodle 4.5 source)
- Moodle source: `lib/components.json` — profilefield plugin type maps to `user/profile/field` path (HIGH confidence, verified)
- moodle-plugin-ci grunt command: runs from Moodle root (no plugin Gruntfile.js), rebuilds amd/build/, compares against committed files (MEDIUM confidence, verified via GruntCommand.php + GitHub issues)
- Solin esbuild guide: https://solin.co/guide-esbuild-amd-modules-moodle-plugin/ — confirms grunt vs esbuild output mismatch risk (MEDIUM confidence, single external source)
- Cleave.js 1.6.0 module format: UMD with `define.amd` check, compatible with RequireJS (HIGH confidence, verified via GitHub source)

---
*Architecture research for: Moodle AMD JavaScript integration in a profilefield plugin*
*Researched: 2026-06-26*
