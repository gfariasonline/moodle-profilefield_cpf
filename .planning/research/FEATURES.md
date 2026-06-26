# Feature Research

**Domain:** Client-side input masking — cleave.js CPF integration in a Moodle profile field plugin
**Researched:** 2026-06-26
**Confidence:** HIGH (core configuration), MEDIUM (edge-case behavior)

---

## Feature Landscape

### Table Stakes (Users Expect These)

Features required for the cleave.js integration to be correct and pass CI.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Correct CPF blocks/delimiters config | Without this the mask formats wrong | LOW | `blocks: [3,3,3,2], delimiters: ['.','.','-'], numericOnly: true` — confirmed by official options docs |
| Formatted value submitted on POST | Moodle form POSTs `input.value`, which is the formatted string | LOW | cleave.js sets `input.value` to the formatted string; `normalize_cpf()` already strips non-digits server-side — no server change needed |
| AMD-compatible distribution | Moodle uses RequireJS; cleave.js must load as AMD | LOW | cleave.js 1.6.0 `dist/cleave.min.js` is UMD, which AMD/RequireJS can consume natively |
| Init processes existing input value | Edit-profile form pre-populates field with "123.456.789-01"; cleave.js must accept and reformat it on init | LOW | On init, cleave.js reads the existing `input.value`, strips non-digits, and re-applies its format — produces correct output |
| Paste reformatting works | Users copy CPF from another source and paste | LOW | Cleave.js listens to the `input` event (fires after paste); pasting "12345678901" or "123.456.789-01" both produce correct "123.456.789-01" display |
| `thirdpartylibs.xml` declaration | `moodle-plugin-ci validate` fails if third-party JS files are not declared | LOW | Required file in plugin root; license comment must also appear at top of the `.js` file in `amd/src/` |
| License comment in bundled file | `moodle-plugin-ci` checks for license headers | LOW | cleave.js is MIT; add SPDX header comment before the minified content in `amd/src/cleave.min.js` |

### Differentiators (Competitive Advantage)

Nice-to-have features that improve UX or robustness without being required.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| `inputmode="numeric"` on the input element | Shows numeric keyboard on iOS/Android without the bugs of `type="number"` | LOW | Add as an HTML attribute in `edit_field_add()` alongside the cleave.js init; cleave.js does not set this automatically |
| `destroy()` call on form reset/unload | Removes cleave.js event listeners; avoids potential memory leaks in SPAs or Moodle's dynamic form handling | LOW | Call `cleave.destroy()` in an AMD module cleanup; low priority for a static profile form |
| Maxlength aligned to 14 characters | Prevents users from typing beyond the formatted length | LOW | The existing `maxlength="14"` attribute in `edit_field_add()` already does this; cleave.js also enforces `blocks` sum (11 digits = 14 chars with delimiters) |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| `swapHiddenInput: true` | Sends raw digits on POST; seems cleaner for server-side | Renames the original input to hidden and creates a visible clone with no `name` attribute. Moodle's form error rendering locates the element by its field name to display inline errors ("CPF inválido"). With the named element hidden, validation error display will break or become misaligned. | Do not use it. Submit the formatted value and let the existing `normalize_cpf()` strip formatting server-side — this already works correctly. |
| CDN loading of cleave.js | Simpler initial setup, no grunt step | Breaks offline Moodle installations; violates project constraint; not compatible with strict Content-Security-Policy; `moodle-plugin-ci grunt` will flag unreferenced AMD source files | Bundle cleave.js locally in `amd/src/cleave.min.js` and reference it via a local AMD path or `define()` |
| `numeral` mode | Appears to format numbers | Numeral mode is for currency/financial numbers with thousands separators. CPF is an identifier, not a number. Enabling numeral mode produces completely wrong output and ignores `blocks`/`delimiters` | Use `blocks` + `delimiters` mode only |
| `type="number"` on the input element | Restricts to digits without JavaScript | Known bug on Samsung Android Chrome (#195 in cleave.js repo): `type="number"` inputs lose formatting mid-edit. Also breaks Moodle's PARAM_TEXT type handling and strips leading zeros. | Keep `type="text"` (Moodle default) and use `numericOnly: true` + `inputmode="numeric"` |
| Using a pre-submit JS hook to replace field value with raw digits | Ensures raw digits reach the server | Requires additional JS complexity; adds a failure mode if the hook fires before or after cleave.js reformats. `normalize_cpf()` on the server already handles any format. | No hook needed; trust the server-side normalization |

---

## Feature Dependencies

```
[AMD module: amd/src/cpf_mask.js]
    └──requires──> [amd/src/cleave.min.js]  (the bundled library)
    └──requires──> [thirdpartylibs.xml]     (for moodle-plugin-ci validate)
    └──requires──> [amd/build/cpf_mask.min.js] (grunt output, checked into repo)

[edit_field_add() calls js_call_amd()]
    └──requires──> [amd/build/cpf_mask.min.js]  (served by Moodle AMD loader)

[Formatted POST value → normalize_cpf()]
    └──enhances──> [edit_save_data_preprocess()] (already strips formatting)
    └──enhances──> [edit_validate_field()]       (already normalizes before validate_cpf())
```

### Dependency Notes

- **cpf_mask.js requires cleave.min.js:** The AMD wrapper module must declare cleave as a dependency via `define(['profilefield_cpf/cleave.min'], function(Cleave) { ... })` or inline the import.
- **thirdpartylibs.xml enables validate check:** Without this file declaring the library, `moodle-plugin-ci validate` will report an undeclared third-party file.
- **grunt build produces the checked-in build artefact:** Moodle's AMD loader serves `amd/build/*.min.js`, not `amd/src/`. The built file must be committed to the repository.

---

## Form Submit Behavior (Quality Gate Item)

**Default behavior (no `swapHiddenInput`):**

- As the user types "12345678901", cleave.js progressively formats the `input.value` to "123.456.789-01".
- On form submit (standard Moodle HTML POST), the browser sends the field's current `input.value`.
- The submitted value is **"123.456.789-01"** (formatted string, 14 chars).

**Server-side interaction:**

```
POST: profile_field_cpf_1 = "123.456.789-01"
  → edit_validate_field()
      → normalize_cpf("123.456.789-01") → "12345678901"
      → validate_cpf("12345678901")     → true
      → cpf_is_unique("12345678901")    → true/false
  → edit_save_data_preprocess("123.456.789-01", ...)
      → normalize_cpf("123.456.789-01") → "12345678901" stored
```

`normalize_cpf()` (line 193–198, `field.class.php`) does `preg_replace('/[^0-9]/', '', $cpf)`, which correctly strips dots and dash. **No server-side change is needed.**

Confidence: HIGH — confirmed by reading `field.class.php` source and cleave.js source behavior.

---

## Mobile / Paste / Autofill Edge Cases (Quality Gate Item)

| Scenario | Behavior | Risk | Mitigation |
|----------|----------|------|------------|
| User types digits on desktop | Cleave.js formats progressively on each keydown/input event | None | N/A — core use case |
| User pastes raw digits "12345678901" | `input` event fires; cleave.js strips non-digits and applies format → "123.456.789-01" | None | Working by design |
| User pastes formatted "123.456.789-01" | `input` event fires; cleave.js strips "." and "-" → "12345678901" → formats → "123.456.789-01" | None | Working by design |
| Android numeric keyboard | `type="text"` + `numericOnly: true` works correctly; no Samsung Chrome bug | None | Add `inputmode="numeric"` to show number pad |
| iOS Safari autofill (saved CPF) | Browser autofills the field with a stored value, then fires `input` event; cleave.js reformats | LOW — autofill could temporarily show double-formatted value before `input` event fires | Acceptable for profile fields (infrequent interaction); no fix required |
| iOS Safari autofill with prefix fields (known issue #722) | CPF has no prefix, so issue #722 does not apply | None | N/A |
| Form loaded with existing CPF (edit profile) | `edit_field_set_data()` sets `input.value = "123.456.789-01"`; cleave.js on init reads this value, strips non-digits, and re-applies formatting | LOW — init must process existing value correctly | Use `cleave.setRawValue()` if init produces incorrect output; alternatively `new Cleave(el, opts)` processes existing `el.value` automatically |
| JavaScript disabled | Cleave.js does not run; user types raw digits or formatted string; `normalize_cpf()` handles both | LOW — degraded UX but functional | Acceptable; server-side validation is the safety net |

Confidence: MEDIUM — paste and init behavior inferred from source code review; autofill from issue #722 (filed 2020, iOS-specific prefix issue does not apply to CPF without prefix).

---

## MVP Definition

### Launch With (v1)

- [x] Exact configuration: `blocks: [3,3,3,2], delimiters: ['.','.','-'], numericOnly: true`
- [x] AMD module `amd/src/cpf_mask.js` wrapping cleave.js, initialized via `js_call_amd()`
- [x] `amd/src/cleave.min.js` bundled locally (from npm `cleave.js@1.6.0/dist/cleave.min.js`)
- [x] `thirdpartylibs.xml` declaring cleave.js with MIT license
- [x] `amd/build/cpf_mask.min.js` and `amd/build/cpf_mask.min.js.map` committed (grunt output)
- [x] Remove `oninput` attribute from `edit_field_add()`

### Add After Validation (v1.x)

- [ ] `inputmode="numeric"` attribute — improves mobile UX; add to `edit_field_add()` attributes string

### Future Consideration (v2+)

- [ ] Migrate to `cleave-zen` (the maintained successor) if Moodle compatibility allows ES module import — low urgency since cleave.js 1.6.0 is stable for this use case

---

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| CPF blocks/delimiters config | HIGH | LOW | P1 |
| AMD module structure + grunt build | HIGH | MEDIUM | P1 |
| thirdpartylibs.xml | LOW (internal CI) | LOW | P1 |
| Formatted POST → normalize_cpf() compatibility | HIGH | LOW (no change needed) | P1 |
| `inputmode="numeric"` | MEDIUM | LOW | P2 |
| `destroy()` cleanup | LOW | LOW | P3 |

---

## Sources

- [cleave.js options.md (official)](https://github.com/nosir/cleave.js/blob/master/doc/options.md) — blocks, delimiters, numericOnly, swapHiddenInput
- [cleave.js public-methods.md (official)](https://github.com/nosir/cleave.js/blob/master/doc/public-methods.md) — getRawValue, getFormattedValue, setRawValue, destroy
- [cleave.js README — AMD usage](https://github.com/nosir/cleave.js/blob/master/README.md) — `require(['cleave.js/dist/cleave.min', ...], function(Cleave){})` pattern
- [cleave.js issue #454 — paste behavior](https://github.com/nosir/cleave.js/issues/454) — confirmed paste triggers `input` event; underlying value is raw
- [cleave.js issue #177 — raw value by default](https://github.com/nosir/cleave.js/issues/177) — confirmed `input.value` is formatted by default; `rawValue` is separate
- [cleave.js release v1.5.9 — swapHiddenInput](https://github.com/nosir/cleave.js/releases/tag/v1.5.9) — swapHiddenInput added to move raw value to hidden field
- [cleave.js issue #722 — iOS autofill prefix bug](https://github.com/nosir/cleave.js/issues/722) — only affects fields with prefix option (not CPF)
- [Moodle third-party libraries docs](https://moodledev.io/general/community/plugincontribution/thirdpartylibraries) — thirdpartylibs.xml requirement, grunt ignorefiles
- [Moodle AMD modules docs](https://moodledev.io/docs/5.0/guides/javascript/modules) — amd/src structure, js_call_amd, grunt build

---

*Feature research for: cleave.js CPF masking in profilefield_cpf Moodle plugin*
*Researched: 2026-06-26*
