# Telas m3e-canvas - Fluxo Cadastro Empresa Parceira

## 11 arquivos JSON separados (um por tela)

Cada arquivo é um projeto m3e-canvas válido e independente. Importe um por vez.

| # | Arquivo | Tela | Contexto |
|---|---------|------|----------|
| 1 | `01-wizard-dashboard.json` | **D1** Wizard Dashboard | Empresa em ativação - checklist 6 etapas |
| 2 | `02-dashboard-active.json` | **D2** Painel Ativo | Empresa aprovada - métricas + ações |
| 3 | `03-step-gateway.json` | **D3** Conta de Pagamento | Gateways: Mercado Pago, Asaas, Pagar.me |
| 4 | `04-step-plan.json` | **D4** Escolha do Plano | 4 cards: Free, HD (R$50), 4K (R$100), BYOS |
| 5 | `05-step-document.json` | **D5** Documento CNPJ/CPF | Form com máscara + radio PJ/PF |
| 6 | `06-step-terms.json` | **D6** Termos de Contrato | Scroll + checkbox habilita botão |
| 7 | `07-admin-approval.json` | **D7** Aprovação Admin | Tabela + modal nota + badge manual/auto |
| 8 | `08-course-block-admin.json` | **C1** Bloco Curso Admin | Métricas do curso + configurações |
| 9 | `09-course-block-pending.json` | **C2** Bloco Curso Pendente | Etapa atual + stepper visual |
| 10 | `10-course-block-active.json` | **C3** Bloco Curso Ativo | Métricas escopadas ao curso |
| 11 | `11-course-block-student.json` | **C4** Bloco Curso Aluno | Assinatura + faturas + histórico |

---

## Como importar no m3e-canvas

### Opção 1: Drag & Drop (recomendado)
1. Abra https://lnkiai.github.io/m3e-canvas/
2. Arraste **um arquivo .json** por vez para a área de import
3. Valide a tela
4. Repita para as demais

### Opção 2: Menu Import
1. No painel lateral esquerdo → **Import**
2. Selecione o arquivo .json
3. A tela carrega automaticamente

---

## Stack Visual (igual em todos)

- **Tema**: LDG (estende Boost, default dark)
- **CSS**: Bootstrap 5.3 + tokens `--ldg-*` (funcionam light/dark via `[data-bs-theme]`)
- **Components**: card, button, form, progress, badge, table, modal
- **Templates**: Mustache syntax
- **Responsive**: Mobile-first (coluna única ≤412px)

---

## Ordem de validação sugerida

```
Fluxo principal (Dashboard):
01 → 06 → 05 → 03 → 04 → 07 → 02

Blocos no Curso:
09 (empresa cadastrando) → 08 (admin) / 10 (empresa ativa) / 11 (aluno)
```

---

## Checklist rápido por tela

- [ ] Dark mode: fundo `#121212`, surface `#1E1E1E`, acento `#4B8EFF`
- [ ] Light mode: fundo `#F4F6FA`, surface `#FFFFFF`, acento `#0062CC`
- [ ] Bootstrap 5 classes: `card`, `btn-*`, `form-control`, `progress`, `badge`, `table`
- [ ] Tokens LDG: `--ldg-bg`, `--ldg-surface`, `--ldg-accent`, `--ldg-text`, `--ldg-border`
- [ ] Mobile ≤412px: coluna única, sem scroll horizontal
- [ ] Touch targets ≥44px (`btn-lg`, `form-control-lg`, checkboxes)
- [ ] Focus visible: `outline: 2px solid var(--ldg-accent)`
- [ ] Contraste AA em ambos os modos

---

## Próximo passo

Após validar todas as 11 telas → **Fase 2: Implementação no block_marketplace**
- Templates Mustache em `dev/public/blocks/marketplace/templates/`
- Classes output em `dev/public/blocks/marketplace/classes/output/`
- Renderer, styles.css, AMD ESM, lang strings