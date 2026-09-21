# Code review — plugins customizados LDG Courses

Histórico do code review completo (dev vs origin/main) dos 11 plugins customizados
do projeto, executado em 2026-09-21. Cada plugin tem um arquivo de detalhamento
com os findings verificados, o cenário de falha, e o status/correção aplicada.

Plano original: [`docs/ai-plans/gleaming-greeting-pascal.md`](../ai-plans/gleaming-greeting-pascal.md).

Regras seguidas no review:
- 8 ângulos de review por plugin, até 6 findings verificados cada.
- Achados avaliados contra `coding-standards/README.md` e as decisões/armadilhas
  já documentadas em `dev/CLAUDE.md` (violação disso é prioridade máxima; decisão
  consciente já registrada não é reaberta como achado novo).
- Todo finding foi confirmado lendo o código real antes de entrar na lista
  (candidatos refutados nessa etapa não aparecem aqui).

## Status geral

| # | Plugin | Findings | Status |
|---|--------|----------|--------|
| 1 | [local/marketplace](01-local-marketplace.md) | 6 | corrigido |
| 2 | [local/partners](02-local-partners.md) | 6 | corrigido |
| 3 | [blocks/marketplace](03-blocks-marketplace.md) | 4 | corrigido |
| 4 | [payment/gateway/mercadopago](04-paygw-mercadopago.md) | 6 | corrigido |
| 5 | [payment/gateway/asaas](05-paygw-asaas.md) | 6 | corrigido |
| 6 | [payment/gateway/pagarme](06-paygw-pagarme.md) | 6 | corrigido |
| 7 | [enrol/marketplace](07-enrol-marketplace.md) | 4 | corrigido |
| 8 | [availability/condition/marketplace](08-availability-marketplace.md) | 1 | corrigido |
| 9 | [course/format/ldg](09-format-ldg.md) | 6 | pendente |
| 10 | [theme/ldg](10-theme-ldg.md) | 5 | pendente |
| 11 | [mod/ldgvideo](11-mod-ldgvideo.md) | 4 | pendente |

Total: 54 findings, 39 corrigidos (checkpoints 1-8) nesta rodada. Checkpoints
9-11 (`format_ldg`, `theme_ldg`, `mod_ldgvideo`, 15 findings) ficam pendentes
para uma próxima rodada — o usuário pediu para fechar e mergear o que já está
pronto em vez de segurar tudo esperando o restante.

## Padrão cross-cutting identificado

O mesmo formato de race condition (guarda de idempotência check-then-write, sem
lock, sem índice único no banco) se repete em `paygw_mercadopago`,
`paygw_asaas`, `paygw_pagarme` e `enrol/marketplace`. Também há duplicação
verbatim entre os três gateways: fallback de comissão `25.0%` hardcoded e a
guarda de isenção da conta da plataforma no split. Ver notas de cada plugin
para o tratamento individual — a correção do padrão comum é discutida no
arquivo do primeiro gateway corrigido (mercadopago) e replicada nos demais.

## Legenda de status

- `pendente` — ainda não corrigido.
- `em andamento` — correção em progresso.
- `corrigido` — correção aplicada e verificada (phpcs/phpunit/behat conforme o caso).
- `descartado` — decidiu-se não corrigir (com justificativa).
