# Pitfalls Research

**Domain:** Moodle AMD module integration with third-party JavaScript library (cleave.js)
**Researched:** 2026-06-26
**Confidence:** HIGH — core findings verified by reading Moodle 4.5 source files directly (Gruntfile.js, .eslintrc, .grunt/tasks/javascript.js, .grunt/tasks/ignorefiles.js, .grunt/components.js)

---

## Critical Pitfalls

### Pitfall 1: Bare NPM Import Fails — Moodle's Rollup Has No Node-Resolve Plugin

**What goes wrong:**
Writing `import Cleave from 'cleave.js'` in `amd/src/cpf_mask.js` causes the rollup step to error with "Could not resolve 'cleave.js'". The build never produces `amd/build/cpf_mask.min.js`, so the plugin is broken and CI fails.

**Why it happens:**
Moodle's Gruntfile.js `rollup` configuration includes only: `rateLimit()`, `babel(...)`, `terser(...)`. There is no `@rollup/plugin-node-resolve` and no `@rollup/plugin-commonjs`. Rollup 2.x cannot resolve bare module specifiers (npm package names) without node-resolve. This is confirmed by Moodle's `package.json`, which lists neither package.

**How to avoid:**
Copy the cleave.js ESM distribution (`dist/cleave-esm.js`) directly into `amd/src/cleave-esm.js`. Then use a relative import in `cpf_mask.js`:

```js
import Cleave from './cleave-esm';
```

Rollup resolves relative file paths natively without any plugins. The cleave code will be bundled inline into `cpf_mask.min.js`.

**Warning signs:**
- `grunt amd` errors with "Could not resolve 'cleave.js' from user/profile/field/cpf/amd/src/cpf_mask.js"
- `amd/build/cpf_mask.min.js` is missing after running grunt

**Phase to address:**
AMD module scaffold phase — the import strategy must be correct from the first commit of `amd/src/cpf_mask.js`.

---

### Pitfall 2: Third-Party File in amd/src/ Without thirdpartylibs.xml Causes ESLint Failures

**What goes wrong:**
Copying `cleave-esm.js` to `amd/src/` without creating `thirdpartylibs.xml` causes `eslint:amd` to lint the cleave source. Cleave.js (2020 vintage) does not carry Moodle JSDoc blocks, uses `console.log`, has lines over 132 chars, and otherwise violates `eslint:recommended` plus Moodle's custom rules. The grunt step fails immediately.

**Why it happens:**
`grunt amd` runs three tasks in sequence: `ignorefiles` → `eslint:amd` → `rollup`. The `ignorefiles` task parses every `thirdpartylibs.xml` found in installed plugin directories (by scanning for `version.php` files) and writes `.eslintignore`. If the plugin's `thirdpartylibs.xml` does not exist or does not list the cleave file, it is not excluded from linting.

The `<location>` element in `thirdpartylibs.xml` is a path **relative to the plugin root**, so to exclude `amd/src/cleave-esm.js` the entry must be `<location>amd/src/cleave-esm.js</location>`.

**How to avoid:**
Create `thirdpartylibs.xml` in the plugin root before the first `grunt amd` run:

```xml
<?xml version="1.0"?>
<libraries>
    <library>
        <location>amd/src/cleave-esm.js</location>
        <name>cleave.js</name>
        <version>1.6.0</version>
        <license>MIT</license>
        <repository>https://github.com/nosir/cleave.js</repository>
        <copyrights>
            <copyright>2020 Max Huang</copyright>
        </copyrights>
    </library>
</libraries>
```

After adding or changing this file, run `grunt ignorefiles` from the Moodle root **before** running `grunt eslint:amd` or the full `grunt amd`. The updated `.eslintignore` will exclude the cleave file from linting.

**Warning signs:**
- ESLint errors referencing `amd/src/cleave-esm.js` (not `cpf_mask.js`)
- Errors like "Missing JSDoc comment", "Unexpected console statement", or "max-len" in cleave-esm.js

**Phase to address:**
AMD module scaffold phase — `thirdpartylibs.xml` must be created at the same time as `amd/src/cleave-esm.js`.

---

### Pitfall 3: Not Committing amd/build/ Files

**What goes wrong:**
Adding `amd/build/` to `.gitignore` (treating it as a generated artefact like `vendor/`) means the production minified files are absent from the repository. moodle-plugin-ci runs `grunt amd`, rebuilds from source, and compares the output against what is committed. When `amd/build/` is absent the check fails with "File is stale and needs to be rebuilt". In production Moodle, if `amd/build/` is missing, users get no JavaScript masking at all.

**Why it happens:**
Developers familiar with toolchains like Webpack or Vite routinely gitignore build output. Moodle inverts this convention: `amd/src/` is the developer-facing source, `amd/build/` is the production artefact that Moodle PHP serves directly. Neither PHP nor RequireJS reads from `amd/src/` at runtime.

**How to avoid:**
Commit ALL of these files:
- `amd/src/cpf_mask.js` — source (developer-facing)
- `amd/src/cleave-esm.js` — vendored third-party source
- `amd/build/cpf_mask.min.js` — minified output (required at runtime)
- `amd/build/cpf_mask.min.js.map` — source map (required for build-state check)
- `amd/build/cleave-esm.min.js` — side-effect of grunt compiling every file in amd/src/
- `amd/build/cleave-esm.min.js.map`

Do NOT add `amd/build/` to `.gitignore`. The plugin's `.gitignore` should only exclude `/vendor/`, `/node_modules/`, and similar install artefacts.

**Warning signs:**
- `.gitignore` contains `amd/build/` or `*.min.js`
- CI output: "File is stale and needs to be rebuilt"
- Running `grunt amd` locally produces files not tracked by git (`git status` shows untracked `amd/build/`)

**Phase to address:**
AMD module scaffold phase — `.gitignore` must be reviewed before first AMD commit.

---

### Pitfall 4: Missing JSDoc Blocks on AMD Source Functions

**What goes wrong:**
Any exported function (or the module's `init` entry point) in `amd/src/cpf_mask.js` without a full JSDoc block causes `eslint:amd` to fail with `jsdoc/require-jsdoc: error`. When moodle-plugin-ci runs with `--max-lint-warnings 0`, JSDoc warnings also become failures.

**Why it happens:**
Moodle's `.eslintrc` has a strict `overrides` block for `**/amd/src/**/*.js` files:

```json
"jsdoc/require-jsdoc": "error",
"jsdoc/require-param": "error",
"jsdoc/require-param-name": "error",
"jsdoc/require-param-type": "error"
```

This is stricter than the base config and applies exclusively to AMD source files. A minimal AMD module that exports `init` with a `fieldName` parameter needs a full JSDoc comment including `@param {string} fieldName` and a `@module` declaration.

**How to avoid:**
Every exported function in `cpf_mask.js` needs a JSDoc comment. Minimum for the `init` function:

```js
/**
 * Initialise the CPF mask for the given input field.
 *
 * @module profilefield_cpf/cpf_mask
 * @param {string} fieldName The name attribute of the input field to mask
 */
export const init = (fieldName) => {
    // ...
};
```

**Warning signs:**
- ESLint output: "Missing JSDoc comment" or "Missing JSDoc @param ... declaration"
- CI step "Moodle Code Checker" passes but "Grunt" step fails (these are separate checks)

**Phase to address:**
AMD module scaffold phase — write JSDoc from the start, not as a retrofit.

---

### Pitfall 5: console.log Calls Fail eslint:amd

**What goes wrong:**
Any `console.log()`, `console.debug()`, or `console.warn()` call in `amd/src/cpf_mask.js` (not in cleave-esm.js, which is excluded) fails `eslint:amd` with `no-console: error`. This is an error, not a warning — it fails CI regardless of the `--max-lint-warnings` setting.

**Why it happens:**
`no-console: 'error'` is set in Moodle's base `.eslintrc`. It is commonly left in code written during development and removed during review. It is one of the most frequent AMD CI failures in submitted plugins.

**How to avoid:**
Use `M.cfg.developerdebug && window.console.log(...)` conditional pattern during development, or remove all console calls before committing. For debugging, use the `core/log` AMD module:

```js
import Log from 'core/log';
// ...
Log.debug('cpf_mask: init called');
```

**Warning signs:**
- Local `grunt amd` run shows `error  Unexpected console statement  no-console`

**Phase to address:**
AMD module scaffold phase — use `core/log` from day one, or omit debug logging entirely for this simple module.

---

### Pitfall 6: Building with esbuild or Other Tools Produces Non-Matching Artefacts

**What goes wrong:**
Using esbuild, Webpack, or even a different version of the Moodle grunt toolchain to generate `amd/build/` files produces minified output that does not byte-match what Moodle's grunt would generate. moodle-plugin-ci rebuilds from source using the Moodle-installed grunt toolchain and compares the hash of each file. A mismatch fails the check even if the JavaScript is functionally equivalent.

**Why it happens:**
Moodle uses a specific Babel plugin (`babel-plugin-transform-es2015-modules-amd-lazy` + `babel-plugin-add-module-to-define.js`) and `rollup-plugin-terser` with `mangle: false`. Any other toolchain produces structurally different output.

**How to avoid:**
Always build `amd/build/` from the **Moodle root** using the Moodle-provided grunt:

```bash
# From the Moodle root (../../.. relative to plugin)
cd /path/to/moodle
npx grunt amd --root=user/profile/field/cpf
# OR run from within the plugin's amd directory
cd user/profile/field/cpf
npx grunt amd   # Moodle Gruntfile detects the plugin context via cwd
```

Never use standalone esbuild or Webpack for the committed artefacts, even if used locally for iteration speed.

**Warning signs:**
- CI: "File is stale and needs to be rebuilt" despite files being present in git
- `git diff` shows changes to `amd/build/` after running local Moodle grunt on top of files built with other tools

**Phase to address:**
AMD module scaffold phase — establish the build command in the project's local development instructions from day one.

---

### Pitfall 7: CRLF Line Endings in amd/src Files

**What goes wrong:**
Creating `amd/src/cpf_mask.js` on Windows (or with an editor defaulting to CRLF) triggers `linebreak-style: ['error', 'unix']` in ESLint. Every single line in the file is flagged as an error.

**Why it happens:**
Git on Windows sometimes checks out files with CRLF by default. Many editors on macOS also default to CRLF if the `.editorconfig` is absent.

**How to avoid:**
Add a `.editorconfig` to the plugin root with `end_of_line = lf` and `charset = utf-8`. Verify with `file amd/src/cpf_mask.js` — it should report "ASCII text" not "ASCII text, with CRLF line terminators".

**Warning signs:**
- Mass ESLint errors: "Expected linebreaks to be 'LF' but found 'CRLF'" on every line

**Phase to address:**
AMD module scaffold phase — `.editorconfig` should be added alongside the first AMD file.

---

### Pitfall 8: Lines Exceeding 132 Characters

**What goes wrong:**
Moodle's ESLint enforces `max-len: ['error', 132]`. Long lines in comments (particularly JSDoc `@param` descriptions), long selector strings, or inlined configuration objects will fail the check.

**Why it happens:**
Standard code formatters often use 80 or 100 character limits; 132 is Moodle-specific. Developers assume their formatter's limit applies.

**How to avoid:**
Configure the editor's ruler at 132 characters for JavaScript files. Keep cleave initialization options on separate lines. Wrap long JSDoc descriptions.

**Warning signs:**
- ESLint output: "This line has a length of X. Maximum allowed is 132."

**Phase to address:**
AMD module scaffold phase — ESLint errors block rollup; fix all errors before first passing build.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Keeping oninput inline JS alongside AMD | No build step needed yet | Dual masking on some browsers; CSP violations; untestable | Never — this is the problem being solved |
| `/* eslint-disable */` on cpf_mask.js | Silences ESLint quickly | Entire module escapes all rules including real bugs; moodle-plugin-ci fails on unused disable directives | Never for the whole file; use narrow per-rule disables only if truly needed |
| Copy cleave.min.js (not cleave-esm.js) | Smaller file | UMD wrapper defines a global, conflicts with AMD; Babel can't tree-shake it | Never — use the ESM distribution |
| Omit amd/build/ from .gitignore for now | Cleaner git history | CI fails, production breaks | Never |
| Manually editing amd/build/ | Quick fix of minified output | Next grunt run overwrites changes; byte mismatch in CI | Never |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| cleave.js import | `import Cleave from 'cleave.js'` (bare specifier) | `import Cleave from './cleave-esm'` (relative path to file in amd/src/) |
| grunt execution | Running `grunt` from the plugin directory without Moodle context | Run from Moodle root or from within a directory Moodle's Gruntfile recognises as an AMD component (amd/ subdir of an installed plugin) |
| thirdpartylibs.xml location | Placing it in `amd/` or `amd/src/` | Must be at plugin root (`user/profile/field/cpf/thirdpartylibs.xml`) |
| js_call_amd parameters | Passing a PHP object or nested array | Only pass simple scalars or flat arrays; PHP's `json_encode` is used internally |
| ESLint .eslintignore | Expecting `.eslintignore` to persist between CI runs | `.eslintignore` is regenerated by `grunt ignorefiles` on every run; do not manually edit it; rely on `thirdpartylibs.xml` |
| amd/build/ in git | Treating it like Webpack `dist/` and gitignoring it | Commit amd/build/; Moodle PHP serves directly from this directory at runtime |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Cleave initialised multiple times on the same field | Each `js_call_amd` call adds another Cleave instance; input formats double on each keystroke | Guard `init` with a data attribute: check `field.dataset.cleaveMask` before initialising | Any time `$PAGE->requires->js_call_amd()` is called more than once for the same field (e.g., inside a form that is re-rendered via AJAX) |
| Cleave not cleaned up on Moodle form navigation | Memory leak if user navigates between profile field tabs without full page reload | Call `cleave.destroy()` in a `beforeunload` or form-change listener | Fragment-based navigation in Moodle 4.5+ mobile themes |

---

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Using `document.getElementById` with a PHP-injected field name without sanitisation | XSS if field name contains `'` or `"` — would be injected into a CSS selector or attribute | Pass field name through `$PAGE->requires->js_call_amd()` which json-encodes the argument; never concatenate PHP strings into JS |
| Relying solely on client-side masking for data format | User can submit unmasked digits if JS fails or is disabled | Server-side `edit_validate_field()` already validates format — keep it |

---

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Cleave fires on existing data when field is pre-populated (edit profile flow) | Field set to stored digits-only (e.g., `12345678901`) gets re-masked as `123.456.789-01` before user touches it, which is correct, but the `input` event fires and marks the form as dirty | Initialise Cleave on `DOMContentLoaded` with `initValue: false` or format before passing to Cleave; test the edit-existing-CPF flow explicitly |
| Masking strips formatting pasted from clipboard | User pastes `123.456.789-01`, Cleave strips it to digits then re-formats — fine. But if Cleave's delimiter config is wrong (`['.','.','-']` vs `['.','.', '-']`), pasted formatted strings are mangled | Verify Cleave options: `blocks: [3,3,3,2]`, `delimiters: ['.','.','-']`, `numericOnly: true` |

---

## "Looks Done But Isn't" Checklist

- [ ] **amd/build/ committed:** `git status` shows `amd/build/cpf_mask.min.js` and `.map` as tracked files — not untracked or gitignored
- [ ] **thirdpartylibs.xml present at plugin root:** `ls user/profile/field/cpf/thirdpartylibs.xml` exists and lists `amd/src/cleave-esm.js`
- [ ] **ESLint passes locally:** `grunt eslint:amd --plugin=user/profile/field/cpf` returns zero errors
- [ ] **Rollup produces output:** `amd/build/cpf_mask.min.js` exists and is non-empty after `grunt amd`
- [ ] **No console calls:** `grep -n console amd/src/cpf_mask.js` returns nothing
- [ ] **Full JSDoc on init:** `init` function has `@param` and `@module` declarations
- [ ] **LF line endings:** `file amd/src/cpf_mask.js` reports ASCII text, no CRLF
- [ ] **Lines within 132 chars:** `awk 'length > 132' amd/src/cpf_mask.js` returns nothing
- [ ] **cleave-esm.js is the ESM build** (not UMD/CJS): file contains `export default` not `module.exports`
- [ ] **Edit-existing-CPF tested:** Load the edit profile page for a user with an existing CPF; the field should display as `XXX.XXX.XXX-XX` and save correctly

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Bare NPM import broke rollup | LOW | Change import to relative path in `cpf_mask.js`, re-run `grunt amd`, commit build |
| ESLint failing on cleave-esm.js | LOW | Add or correct `thirdpartylibs.xml`, run `grunt ignorefiles` then `grunt amd` |
| amd/build/ was gitignored and now missing from CI | LOW | Remove `amd/build/` from `.gitignore`, run `grunt amd`, commit the build output |
| Build was done with esbuild — byte mismatch in CI | MEDIUM | Delete `amd/build/`, run `grunt amd` from Moodle root, commit new build output |
| JSDoc errors on all exported functions | MEDIUM | Add JSDoc to each function; run `grunt eslint:amd` iteratively; takes 30–60 min for a small module |
| CRLF line endings throughout amd/src/ | LOW | `dos2unix amd/src/cpf_mask.js`, add `.editorconfig`, commit |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Bare NPM import | AMD scaffold (create amd/src/ structure) | `grunt amd` produces amd/build/cpf_mask.min.js with no errors |
| Missing thirdpartylibs.xml | AMD scaffold | `grunt eslint:amd` produces no errors referencing cleave-esm.js |
| amd/build/ not committed | AMD scaffold | `git ls-files amd/build/` lists min.js files |
| Missing JSDoc | AMD scaffold | `grunt eslint:amd --max-lint-warnings 0` exits 0 |
| console.log calls | AMD scaffold | `grunt eslint:amd` exits 0 |
| Wrong build tool | AMD scaffold + local CI run | Run moodle-plugin-ci grunt locally before push |
| CRLF endings | AMD scaffold | Add `.editorconfig` at project start |
| Line length | AMD scaffold | `awk 'length > 132' amd/src/cpf_mask.js` empty |

---

## Sources

- Moodle 4.5 source (verified directly): `/Users/thiagoserrao/Sites/moodle45/.grunt/tasks/javascript.js` — `grunt amd` task definition (`ignorefiles` → `eslint:amd` → `rollup`)
- Moodle 4.5 source (verified directly): `/Users/thiagoserrao/Sites/moodle45/.grunt/tasks/ignorefiles.js` — how `thirdpartylibs.xml` feeds `.eslintignore`
- Moodle 4.5 source (verified directly): `/Users/thiagoserrao/Sites/moodle45/.grunt/components.js` — component discovery scans `version.php` files; plugin's `thirdpartylibs.xml` is picked up once plugin is installed
- Moodle 4.5 source (verified directly): `/Users/thiagoserrao/Sites/moodle45/.eslintrc` — full ESLint config including `jsdoc/require-jsdoc: error`, `no-console: error`, `max-len: 132`
- Moodle 4.5 source (verified directly): `/Users/thiagoserrao/Sites/moodle45/package.json` — no `@rollup/plugin-node-resolve`; rollup v2.67.3
- Moodle 4.5 source (verified directly): `/Users/thiagoserrao/Sites/moodle45/lib/thirdpartylibs.xml` — shows `amd/src/loglevel.js`, `amd/src/mustache.js` as third-party entries
- Moodle 4.5 source (verified directly): `lib/amd/src/icon_system_standard.js` — uses `import * as Mustache from './mustache'` confirming relative-import pattern
- [Moodle AMD Module Docs (5.2)](https://moodledev.io/docs/5.2/guides/javascript/modules) — "Don't forget to add the built files (the ones in amd/build) to your git commits"
- [moodle-plugin-ci CLI reference](https://moodlehq.github.io/moodle-plugin-ci/CLI.html) — grunt runs `amd`, `yui`, `gherkinlint`, `stylelint` by default; `--max-lint-warnings 0` makes warnings failures
- [Using esbuild for Moodle AMD](https://solin.co/guide-esbuild-amd-modules-moodle-plugin/) — "grunt build-state step that rebuilds amd/src/ and compares against the committed amd/build/"; esbuild output will not byte-match grunt's
- ["File is stale" issue](https://github.com/moodlehq/moodle-plugin-ci/issues/319) — community confirmation of build-state check behaviour
- [Plugin third-party libraries (official)](https://moodledev.io/general/community/plugincontribution/thirdpartylibraries) — `thirdpartylibs.xml` required; AMD exception: single `.js` file in `amd/src/`
- [cleave.js npm (1.6.0)](https://www.npmjs.com/package/cleave.js/v/1.6.0) — ESM entry point is `dist/cleave-esm.js`

---

*Pitfalls research for: Moodle AMD module + cleave.js integration (profilefield_cpf)*
*Researched: 2026-06-26*
