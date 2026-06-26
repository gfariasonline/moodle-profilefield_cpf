# Research Summary: profilefield_cpf — Cleave.js Integration

**Synthesized:** 2026-06-26
**Sources:** STACK.md, FEATURES.md, ARCHITECTURE.md, PITFALLS.md
**Overall Confidence:** HIGH

---

## Executive Summary

Integrar cleave.js neste plugin significa colocar o dist do cleave em `amd/src/`, declarar em `thirdpartylibs.xml`, escrever um módulo AMD `cpf_mask.js` e chamar `$PAGE->requires->js_call_amd()` em `edit_field_add()`. O código server-side (normalize_cpf, validate_cpf, edit_save_data_preprocess) **não precisa de nenhuma alteração** — já lida com valores formatados e com dígitos puros.

O maior risco é o pipeline de build AMD: `amd/build/` **deve ser commitado** no git (o moodle-plugin-ci reconstrói e compara SHA1). Há uma decisão de design a resolver antes de começar: **estratégia de import** (ver abaixo).

---

## Stack

| Ferramenta | Versão | Nota |
|-----------|--------|------|
| Node.js | 22.x (LTS Jod) | Obrigatório — `.nvmrc` do Moodle. Versão errada = build não-determinístico = CI falha |
| Grunt | Moodle root | Único toolchain aceito pelo CI; esbuild/Webpack produzem output incompatível |
| cleave.js | 1.6.0 | Última release; ships UMD (`dist/cleave.js`) e ESM (`dist/cleave-esm.js`) |
| RequireJS | Moodle-provided | Serve `amd/build/*.min.js` em runtime |

---

## Features

**Configuração CPF para cleave.js:**
```js
new Cleave(el, {
    blocks: [3, 3, 3, 2],
    delimiters: ['.', '.', '-'],
    numericOnly: true
});
```

**Comportamento no submit:** cleave.js deixa o valor formatado no input (`123.456.789-01`). `normalize_cpf()` já faz `preg_replace('/[^0-9]/', '', $cpf)` — zero mudanças no server-side.

**Anti-features a evitar:**
- `swapHiddenInput: true` — renomeia o input, quebra exibição de erros inline do Moodle
- CDN externo — quebra instâncias offline
- Hook pré-submit para strip de formatação — desnecessário

**P2 (não bloqueante):** `inputmode="numeric"` no elemento HTML para teclado numérico em iOS/Android.

---

## Architecture

**Fluxo:**
```
PHP edit_field_add() → js_call_amd() → RequireJS → cpf_mask.min.js → Cleave(input)
```

**Arquivos novos:**
```
amd/
├── src/
│   ├── cpf_mask.js          ← módulo AMD com init(fieldName)
│   └── [cleave dist].js     ← copiado verbatim, declarado em thirdpartylibs.xml
└── build/
    ├── cpf_mask.min.js      ← gerado pelo grunt (commitar)
    ├── cpf_mask.min.js.map  ← gerado pelo grunt (commitar)
    └── [cleave].min.js*     ← se usar AMD define (commitar)
thirdpartylibs.xml           ← na raiz do plugin
```

**Modificação no PHP:** Remover `id="..."` e `oninput="..."` de `edit_field_add()`. Adicionar:
```php
global $PAGE;
$PAGE->requires->js_call_amd('profilefield_cpf/cpf_mask', 'init', [$this->inputname]);
```
O Moodle gera `id="id_<fieldname>"` automaticamente — passar `$this->inputname` para o JS que monta o id.

---

## Decisão de Design Aberta: Estratégia de Import

Duas abordagens válidas, ambas passam no CI:

| | **Opção A — AMD define + UMD** | **Opção B — ESM import + ESM dist** |
|---|---|---|
| Fonte | `dist/cleave.js` (UMD) | `dist/cleave-esm.js` (ESM) |
| cpf_mask.js | `define(['./local/cleave'], fn)` | `import Cleave from './cleave-esm'` |
| Build output | 2 módulos em `amd/build/` | 1 módulo (cleave inlined) em `amd/build/` |
| Precisa de thirdpartylibs.xml | Sim | Sim |
| Padrão Moodle core | prism.js, videojs | — |
| Simplicidade | Menor | Maior (arquivo único) |

**Recomendação:** Opção B (ESM inline) — menos arquivos para manter, build simples.

---

## Pitfalls Críticos

1. **Import bare NPM** (`from 'cleave.js'`) — rollup sem node-resolve → build falha. Usar path relativo.
2. **`thirdpartylibs.xml` ausente** — ESLint roda no cleave e falha com 100+ erros.
3. **`amd/build/` não commitado** — moodle-plugin-ci faz SHA1 diff → "File is stale" → CI falha.
4. **Node version errada** — output não-determinístico → stale file mesmo com código idêntico.
5. **JSDoc ausente em cpf_mask.js** — `.eslintrc` tem `jsdoc/require-jsdoc: error` (erro, não warning).
6. **CRLF no amd/src/** — `linebreak-style: ['error', 'unix']` → falha em todo arquivo com `\r\n`.
7. **Linha > 132 chars** — `max-len: ['error', 132]` — Moodle-específico.
8. **`id="..."` customizado no form** — Moodle auto-gera `id_<name>`; sobrescrever quebra namespacing.

---

## Roadmap Sugerido

### Fase 1 — AMD Scaffold
**Goal:** Pipeline AMD funcionando. `grunt amd` passa sem erros. `amd/build/` commitado.
- Escolher estratégia de import (Opção A ou B)
- Copiar cleave dist para `amd/src/`
- Criar `cpf_mask.js` stub (init vazio, JSDoc completo)
- Criar `thirdpartylibs.xml`
- Rodar `grunt amd` com Node 22, commitar `amd/build/`
- Verificar `moodle-plugin-ci grunt`

### Fase 2 — Implementação da Máscara
**Goal:** Campo CPF usa cleave.js. Inline JS removido.
- Implementar `init()` no `cpf_mask.js`
- Modificar `edit_field_add()`: remover `id` e `oninput`, adicionar `js_call_amd()`
- Testar: nova digitação, paste, load de valor existente (pré-formatado)

### Fase 3 — CI Full Pass
**Goal:** Todos os checks do moodle-plugin-ci passam.
- phplint, phpcs, phpdoc, validate, savepoints, grunt, phpunit

---

*Pesquisa concluída: 2026-06-26*
