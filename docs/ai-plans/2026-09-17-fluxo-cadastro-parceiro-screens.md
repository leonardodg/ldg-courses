# Instrução de Uso: m3e-canvas para Fluxo de Cadastro de Empresa Parceira

## Visão Geral

Este documento explica como abrir e validar as 11 telas do wizard de ativação de empresa parceira no **m3e-canvas** (https://lnkiai.github.io/m3e-canvas/), projeto: https://github.com/lnkiai/m3e-canvas.

O arquivo de design: `dev/docs/ai-plans/2026-09-17-fluxo-cadastro-parceiro-m3e-canvas.json`

**Stack visual**: Bootstrap 5.3 + Tema LDG (estende Boost) + Tokens CSS custom properties (`--ldg-*`) + Templates Mustache

---

## Como Abrir no m3e-canvas

### Opção 1: Via URL direta (recomendado)

1. Acesse: **https://lnkiai.github.io/m3e-canvas/**
2. No painel lateral esquerdo, clique em **"Import"** ou arraste o arquivo `.json`
3. Selecione o arquivo: `2026-09-17-fluxo-cadastro-parceiro-m3e-canvas.json`
4. As 11 telas carregarão automaticamente nas duas larguras (mobile 412px / desktop 1280px)

### Opção 2: Via link #docz= (compartilhável)

O m3e-canvas gera um link codificado em base64url. Para gerar:

```bash
# No terminal, na raiz do projeto m3e-canvas (se clonado localmente):
node scripts/link.mjs dev/docs/ai-plans/2026-09-17-fluxo-cadastro-parceiro-m3e-canvas.json
# ou
python scripts/link.py dev/docs/ai-plans/2026-09-17-fluxo-cadastro-parceiro-m3e-canvas.json
```

O comando retorna uma URL como:
```
https://lnkiai.github.io/m3e-canvas/#docz=eyJ2ZXJzaW9uIjoiMS4wIiwicHJvamVjdCI6IkxERyBDb3Vyc2VzIC0gRmx1eG8gQ2FkYXN0cm8gRW1wcmVzYSBQYXJjZWlyYSIsIn...
```

**Cole essa URL no navegador** para abrir direto no estado exato.

### Opção 3: Local (se tiver o m3e-canvas clonado)

```bash
git clone https://github.com/lnkiai/m3e-canvas.git
cd m3e-canvas
npm install
npm run dev
# Abre em http://localhost:5173
# Faça import do JSON pela UI
```

---

## Stack Visual - O Que Validar

### Tema LDG + Bootstrap 5
- **Tema pai**: Boost (core do Moodle 5.x)
- **Tema filho**: theme_ldg (em `dev/public/theme/ldg/`)
- **Default**: Dark mode (`[data-bs-theme="dark"]` no `<html>`)
- **Light mode**: `[data-bs-theme="light"]` (usuário pode alternar)
- **Tokens**: CSS custom properties `--ldg-*` definidos em `_tokens.scss`
- **Componentes**: Bootstrap 5.3 (card, button, form, progress, badge, table, modal, accordion)
- **Utilitários**: Classes Bootstrap (d-flex, gap, p-*, m-*, text-*, bg-*, border-*, rounded-*, shadow-*)

### Tokens LDG Principais (funcionam nos DOIS modos)

```css
/* Cores de fundo/superfície */
--ldg-bg, --ldg-surface, --ldg-surface-raised
--ldg-border

/* Texto */
--ldg-text, --ldg-text-muted, --ldg-text-label

/* Acento (azul da marca) */
--ldg-accent, --ldg-accent-hover, --ldg-accent-text
--ldg-accent-fill, --ldg-btn-fill, --ldg-btn-fill-hover, --ldg-on-accent

/* Status */
--ldg-status-success

/* Campos */
--ldg-field-bg

/* Sombras */
--ldg-shadow-card, --ldg-shadow-raised, --ldg-hover-overlay

/* Tipografia */
--ldg-font-sans, --ldg-font-mono
--ldg-fs-h1 até --ldg-fs-caption

/* Espaçamento */
--ldg-space, --ldg-gutter, --ldg-container
--ldg-navbar-height

/* Border radius */
--ldg-border-radius, --ldg-border-radius-sm, --ldg-border-radius-lg
```

**No m3e-canvas**: os tokens estão definidos no `theme.tokens.light` e `theme.tokens.dark`. O canvas aplica via `[data-bs-theme]` no wrapper.

---

## Checklist de Validação por Tela

### Todas as telas
- [ ] **Tokens LDG aplicados**: cores via `--ldg-*`, não hardcoded
- [ ] **Bootstrap 5 components**: card, btn, form-control, progress, badge, table
- [ ] **Utilitários Bootstrap**: d-flex, gap-3, p-3, m-2, text-muted, bg-surface, border, rounded, shadow-sm
- [ ] **Dark mode (padrão)**: fundo `#121212`, surface `#1E1E1E`, texto `#FFFFFF`/`#B0B3B8`, acento `#4B8EFF`
- [ ] **Light mode**: fundo `#F4F6FA`, surface `#FFFFFF`, texto `#0E192B`, acento `#0062CC`
- [ ] **Tipografia**: Inter (--ldg-font-sans), JetBrains Mono (--ldg-font-mono)
- [ ] **Espaçamento**: base 0.5rem (8px), gutter 1.5rem (24px), container 1440px
- [ ] **Border radius**: 0.5rem (8px) padrão, pill apenas em badges/chips
- [ ] **Sombras**: `--ldg-shadow-card` / `--ldg-shadow-raised` (não shadow-* do Bootstrap)
- [ ] **Mobile (≤412px)**: coluna única, sem scroll horizontal
- [ ] **Alvos de toque ≥44px** (btn-lg, form-control-lg, checkboxes)
- [ ] **Focus visible**: `outline: 2px solid var(--ldg-accent)` / `box-shadow: 0 0 0 2px var(--ldg-accent)`
- [ ] **Contraste AA** em ambos os modos
- [ ] **Templates Mustache**: variáveis `{{{variable}}}`, seções `{{#section}}...{{/section}}`

### D1 - Wizard Dashboard (Empresa cadastrando)
- [ ] Card com progress bar (Bootstrap progress + `--ldg-accent`)
- [ ] Checklist: 1 done (check), 1 current (primary), 4 blocked (secondary disabled)
- [ ] Badges de estado: `bg-success` (done), `bg-primary` (current), `bg-secondary` (pending/blocked)
- [ ] CTA "Aceitar termos" → `btn-primary btn-lg w-100`
- [ ] Accordion/colapsável "Como funciona"

### D2 - Painel Ativo (Empresa aprovada)
- [ ] 3 cards métricas: `card bg-surface border` + `card-body` + ícone FontAwesome + trend badge
- [ ] Badge "Ativa": `badge bg-success`
- [ ] Ações rápidas: btn-primary, btn-outline-primary, btn-outline-secondary
- [ ] Card "Plano atual" com badge de plano

### D3 - Conta de Pagamento
- [ ] 3 gateways em cards: badge `bg-success` (connected) vs `bg-secondary` (available)
- [ ] Estados visuais distintos
- [ ] CTA "Escolher plano" habilitado apenas se houver gateway connected

### D4 - Escolha do Plano
- [ ] 4 cards em grid: `row g-3` + `col-12 col-md-6 col-lg-3`
- [ ] Cards: Free (outline-primary), HD (primary), 4K (primary + badge warning), BYOS (secondary disabled)
- [ ] Badge "Recomendado" no 4K: `badge bg-warning text-dark`
- [ ] Features/restrictions em `list-unstyled` com ícones check/x

### D5 - Documento CNPJ/CPF
- [ ] Radio group: `form-check` + `form-check-input` + `form-check-label`
- [ ] Input com máscara: `form-control form-control-lg` + `data-mask="cnpj|cpf"`
- [ ] Validação visual: `is-valid`/`is-invalid` + `invalid-feedback`
- [ ] Select país: `form-select form-select-lg`
- [ ] Alert info: `alert alert-info` com ícone

### D6 - Termos de Contrato
- [ ] Card com `overflow-auto` max-height para scroll
- [ ] Seções com `h6` + `text-muted`
- [ ] Checkbox: `form-check form-check-lg` (alvo ≥44px)
- [ ] Botão desabilitado até checkbox checked: `btn-primary btn-lg w-100 disabled`

### D7 - Aprovação Admin
- [ ] Badge modo: `badge bg-warning text-dark` (Manual) / `bg-success` (Auto)
- [ ] Alert warning no topo
- [ ] Table: `table table-hover table-striped` + `table-responsive` no mobile
- [ ] Prioridade: badge `bg-danger` (Alta) / `bg-secondary` (Normal)
- [ ] Ações: btn-group com btn-sm (outline-secondary, success, outline-danger)
- [ ] Modal para nota: `modal` + `form-control` required

### C1 - Bloco Curso Admin
- [ ] Card empresa + badge plano
- [ ] 4 métricas em `row g-3` cards
- [ ] Ações em `d-flex gap-2 flex-wrap`

### C2 - Bloco Curso Empresa Cadastrando
- [ ] Card compacto: etapa atual em `badge bg-primary`
- [ ] Progress steps: stepper visual (Bootstrap não tem nativo, custom com `--ldg-accent`)
- [ ] CTA full-width

### C3 - Bloco Curso Empresa Ativa
- [ ] 4 métricas cards com trend
- [ ] 3 ações: 2 outline-secondary + 1 primary

### C4 - Bloco Curso Aluno
- [ ] Card assinatura: badge `bg-success` + próxima fatura
- [ ] Tabela faturas: `table table-sm` (date, amount, status badge)
- [ ] Link histórico: `btn-outline-secondary w-100`
- [ ] Nota acesso: `alert alert-info` small

---

## Exportar Prompt para Geração de Código

Após validar visualmente no m3e-canvas:

1. No painel do m3e-canvas, clique em **"Export"** → **"Web Prompt"**
2. Copie o prompt gerado
3. Salve em: `dev/docs/ai-plans/2026-09-17-fluxo-cadastro-parceiro-prompt.md`
4. Este prompt será usado na Fase 2 para gerar:
   - **Templates Mustache** em `dev/public/blocks/marketplace/templates/*.mustache`
   - **Styles.css** escopado em `.block_marketplace` (usa tokens `--ldg-*`)
   - **Classes templatable** em `dev/public/blocks/marketplace/classes/output/*.php`
   - **Renderer** em `dev/public/blocks/marketplace/classes/renderer.php`
   - **AMD ESM** em `dev/public/blocks/marketplace/amd/src/*.js` (`export const init`)
   - **Lang strings** em `dev/public/blocks/marketplace/lang/{en,pt_br,es}/`

---

## Próximos Passos (após aprovação visual)

1. **Você aprova** as 11 telas (marca cada uma ✅ ou pede ajustes)
2. **Eu gero** o prompt de exportação Web do m3e-canvas
3. **Iniciamos Fase 2**: implementação no `block_marketplace` (worktree moodev, TDD, templates, renderer, AMD ESM, testes Behat/PHPUnit)

---

## Arquivos Relacionados

| Arquivo | Descrição |
|---------|-----------|
| `2026-09-17-fluxo-cadastro-parceiro-m3e-canvas.json` | Design completo (11 telas, Bootstrap 5 + LDG tokens) |
| `dev/docs/brand/design_system_leodg.md` | Design system LDG (referência visual) |
| `dev/public/theme/ldg/` | Tema LDG (parent: boost, default dark) |
| `dev/public/theme/ldg/scss/ldg/_tokens.scss` | Tokens CSS custom properties |
| `dev/docs/dev/padrao-de-implementacao.md` | Padrão de implementação do projeto |
| `dev/public/blocks/marketplace/` | Plugin alvo (será atualizado na Fase 2) |

---

## Contato / Dúvidas

- **Projeto m3e-canvas**: https://github.com/lnkiai/m3e-canvas
- **LDG Courses (dev)**: branch `dev` em https://github.com/leonardodg/ldg-courses
- **Tema LDG**: `dev/public/theme/ldg/` (estende Boost, Bootstrap 5, default dark)
- **Design System**: `dev/docs/brand/design_system_leodg.md`

---

> **Importante**: Esta fase é **apenas design/validação visual**. Nenhum código PHP/JS é escrito ainda. A implementação (Fase 2) começa somente após sua aprovação explícita de todas as 11 telas. O design usa **Bootstrap 5 components + LDG tokens** para funcionar nativamente no tema `theme_ldg` (que estende `boost`) em ambos os modos light/dark via `[data-bs-theme]`.