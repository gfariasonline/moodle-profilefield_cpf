# profilefield_cpf — Cleave.js Masking

## What This Is

Fork do plugin Moodle [profilefield_cpf](https://moodle.org/plugins/profilefield_cpf) que adiciona um campo de perfil de usuário para CPF (Cadastro de Pessoas Físicas) brasileiro. O plugin valida o CPF matematicamente, impõe unicidade entre usuários e formata a exibição como `XXX.XXX.XXX-XX`. Esta versão substitui o inline JavaScript de máscara por cleave.js carregado como módulo AMD nativo do Moodle.

## Core Value

O campo CPF deve mascarar a entrada do usuário de forma confiável e nativa ao Moodle, sem inline JS, usando cleave.js empacotado localmente como AMD module.

## Requirements

### Validated

- ✓ Campo CPF renderiza como input de texto no formulário de edição de perfil — existing
- ✓ Máscara inline JS formata a entrada como `XXX.XXX.XXX-XX` durante a digitação — existing (será substituído)
- ✓ Validação server-side do CPF (algoritmo de dígito verificador) — existing
- ✓ Unicidade do CPF entre usuários (rejeita duplicatas) — existing
- ✓ Armazena apenas os 11 dígitos no `user_info_data` — existing
- ✓ Exibe CPF formatado como `XXX.XXX.XXX-XX` na página de perfil — existing
- ✓ Compatibilidade com Moodle 4.5+ (PSR-12, Privacy API) — existing
- ✓ Privacy API implementada como null_provider — existing
- ✓ Strings de idioma pt_BR e en — existing

### Active

- [ ] Substituir o `oninput` inline JS por cleave.js via AMD module
- [ ] Bundlar cleave.js localmente em `amd/src/` (sem dependência de CDN externo)
- [ ] Inicializar cleave.js via `$PAGE->requires->js_call_amd()` em `edit_field_add()`
- [ ] Configurar cleave.js para blocos `[3,3,3,2]` com delimitadores `['.','.','-']`
- [ ] Garantir que grunt/AMD build passe no moodle-plugin-ci
- [ ] Manter compatibilidade com Moodle 4.5–5.2

### Out of Scope

- Mudança na lógica de validação server-side — já funciona corretamente
- Mudança no formato de storage — continua armazenando só dígitos
- Redesign da UI além da máscara de entrada
- Suporte a novos idiomas nesta versão
- Migração de dados existentes (formato de storage não muda)

## Context

O plugin atual (`field.class.php:40-48`) usa um atributo `oninput` com regex inline para mascarar o campo. Isso funciona mas é frágil, não testável e viola boas práticas Moodle/Content Security Policy. Cleave.js é uma biblioteca JavaScript madura para formatação de inputs que resolve exatamente este caso de uso.

A integração AMD no Moodle requer:
1. `amd/src/cpf_mask.js` — módulo AMD que importa cleave e inicializa o campo
2. `amd/build/cpf_mask.min.js` + `.min.js.map` — artefatos do grunt build
3. `edit_field_add()` chama `$PAGE->requires->js_call_amd('profilefield_cpf/cpf_mask', 'init', [$this->inputname])`
4. `package.json` + `Gruntfile.js` para o build AMD (ou inline no grunt do Moodle)

O cleave.js não é mantido ativamente desde 2020 mas é estável para este caso de uso simples. A versão 1.6.0 é a última release.

## Constraints

- **Tech stack**: PHP 8.2/8.3, Moodle AMD (RequireJS), cleave.js 1.6.0
- **Compatibility**: Moodle 4.5 → 5.2 (conforme `$plugin->supported = [405, 502]`)
- **CI**: Deve passar em todos os checks do moodle-plugin-ci (incluindo `grunt`)
- **No CDN**: cleave.js bundlado localmente — funciona em instâncias offline

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| AMD module sobre CDN | Padrão Moodle, funciona offline, passa CI | — Pending |
| Cleave.js 1.6.0 | Última versão estável; caso de uso simples não precisa de manutenção ativa | — Pending |
| Manter storage como digits-only | Evita migração de dados, normalização existente continua funcionando | ✓ Good |

---
*Last updated: 2026-06-26 after initialization*
