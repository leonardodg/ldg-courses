# Mockups HTML — fluxo de ativação de empresa parceira

Substitui a tentativa via m3e-canvas (ver `../descartado/`). Abre direto no
Chrome, sem servidor.

**Layout completo, uma página só:** `layout-completo.html` — as 11 telas em
sequência (D1–D7, C1–C4), com navbar e rodapé aproximados do `theme_ldg` (cores
e estrutura reais de `navbar.mustache`/`footer.mustache`/`_navbar.scss`, sem
reproduzir a lógica de servidor que não importa pro mockup) e um índice fixo no
topo para pular direto a cada tela. As telas C1–C4 vêm dentro de um layout de
duas colunas (`.course-shell`) com um placeholder à esquerda representando a
área de conteúdo do curso, porque o bloco de verdade sempre aparece ao lado de
algo, nunca sozinho.

Os 11 arquivos avulsos (`d1-wizard-dashboard.html` etc.) continuam existindo
para quem quiser abrir uma tela isolada.

Usa Bootstrap 5.3 (CDN) + `_tokens.css` (cópia dos custom properties reais de
`public/theme/ldg/scss/ldg/_tokens.scss` — qualquer ajuste de cor/token vai no
arquivo fonte do tema, nunca aqui). `data-bs-theme="dark"` é o padrão do site;
troque para `"light"` no `<html>` para conferir o outro modo.

## Telas

| # | Arquivo | Contexto |
|---|---|---|
| D1 | `d1-wizard-dashboard.html` | Dashboard, empresa em cadastro |
| D2 | `d2-dashboard-active.html` | Dashboard, empresa ativa |
| D3 | `d3-step-gateway.html` | Etapa: conta de pagamento |
| D4 | `d4-step-plan.html` | Etapa: plano (corrigida — ver nota abaixo) |
| D5 | `d5-step-document.html` | Etapa: documento CNPJ/CPF (corrigida) |
| D6 | `d6-step-terms.html` | Etapa: termos de contrato (corrigida) |
| D7 | `d7-admin-approval.html` | Fila de aprovação do admin (corrigida) |
| C1 | `c1-course-block-admin.html` | Bloco no curso, perfil admin |
| C2 | `c2-course-block-pending.html` | Bloco no curso, empresa cadastrando |
| C3 | `c3-course-block-active.html` | Bloco no curso, empresa ativa |
| C4 | `c4-course-block-student.html` | Bloco no curso, aluno |

## Correções aplicadas nesta rodada (ver Fase 2 do plano)

- **D4**: resolução do Start-R$50 é **1080p**, não "1024"; **PRO já é plano
  real e cobrável**, não "em breve" (só a hospedagem própria na plataforma,
  Fase 5, segue bloqueada por decisão de negócio).
- **D5**: CNPJ/CPF é **opcional** (ADR-0010) — nunca bloqueia o avanço.
- **D6**: reaproveita o `termsaccepted` que já existe em
  `local_partners\application`, não cria campo novo em `company`.
- **D7**: removido o toggle manual/automático — hoje só existe aprovação
  manual, via `local_partners` (sem auto-atendimento). Modo automático fica
  como funcionalidade futura, fora do escopo desta fase.

## Checklist de revisão por tela

- [ ] Contraste AA nos dois modos (claro/escuro)
- [ ] Alvo de toque ≥44px (botões, checkboxes)
- [ ] Coluna única no mobile (sem rolagem horizontal)
- [ ] Conteúdo consistente com o modelo de planos real (`plan_tier::RESOLUTIONS`)
- [ ] Nenhuma tela assume campo/config que não existe no código

## Depois da aprovação

Só então estas telas viram templates Mustache reais em
`public/blocks/marketplace/templates/*.mustache` — trabalho da Fase de
implementação (fora deste plano), com `classes/output/*.php` + `renderer.php`.
