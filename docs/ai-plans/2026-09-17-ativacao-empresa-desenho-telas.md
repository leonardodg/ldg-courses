# Ativação de empresa parceira — desenho das telas

Situação: `pendente` · Início: 2026-09-17

> **Nota de atualização (2026-09-25):** este documento cita um setting
> `approvalmode = manual|auto` (linha "Aprovação" da tabela de decisões) que
> **não existe no código** (`grep approvalmode public/` = 0). Registro
> histórico mantido como estava; a aprovação de empresa em produção é manual
> por fluxo em `local_partners`, sem esse setting. Ver
> [`../produto/gate-contradicoes.md`](../produto/gate-contradicoes.md) F6.

## Contexto

O `block_marketplace` hoje mostra só as assinaturas do aluno no Dashboard. O
objetivo é transformá-lo no **passo-a-passo de ativação de uma empresa parceira**
no LDG Courses: cadastro → dados → conta de pagamento → plano → aprovação do
admin → ativa → criar curso/oferta/vínculo.

O usuário pediu ordem explícita: **desenhar e aprovar as telas primeiro**, e só
depois, com o layout aprovado, implementar as funcionalidades.

## Decisões

| Tema | Decisão |
|---|---|
| Onde vive o wizard | Block = widget/summary; fluxo completo em páginas próprias do bloco (`/blocks/marketplace/*.php`) |
| Estado das etapas | **Derivado dos campos existentes** — sem tabela nova (`onboarding::step_state()`) |
| Empresa em cadastro | ADR-0006: empresa+categoria nasce **escondida na submissão**; ativação = aprovação |
| Plano free | Reaproveita a trava existente (papel restritivo PROHIBIT, ADR-0009) |
| Aprovação | Setting `approvalmode = manual\|auto`; **padrão `manual`** hoje. Auto previsto por trás do setting |
| JS | **ESM 5.x** (`amd/src/*.js` → `export const init`), `npx grunt amd` → `amd/build/*.min.js` |
| Estilo | Design system LDG (grafite + azul elétrico), `styles.css` escopado `.block_marketplace` |

## O que mudou

- `docs/design/block-marketplace-onboarding/design.json` — 11 telas (22 frames:
  phone + desktop) no formato do m3e-canvas.
- `docs/design/block-marketplace-onboarding/design.link` — link compartilhável
  `#docz=`.
- `docs/design/block-marketplace-onboarding/README.md` — instrução de uso,
  paleta LDG, como gerar o prompt.
- `docs/ai-plans/README.md` — linha do plano no índice.

## Verificação

- JSON válido (`json.load` OK).
- Navegação íntegra: nenhum `action.to` aponta para frame inexistente (o único
  quebrado, `d3mpd`, foi corrigido).
- 22 frames, 144 grupos, ~38 KB (limite 100 KB).

## Em aberto

- **Aprovação do desenho pelo usuário** — gate da Fase 1. As telas D1–C4
  precisam ser conferidas no canvas e marcadas como `aprovada`/`ajustar`.
- Depois da aprovação: escrever o spec do desenho e o plano de implementação
  (`writing-plans`) do `block_marketplace` (Fase 2).