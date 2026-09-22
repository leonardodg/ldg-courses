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
| 9 | [course/format/ldg](09-format-ldg.md) | 6 | corrigido |
| 10 | [theme/ldg](10-theme-ldg.md) | 5 | corrigido |
| 11 | [mod/ldgvideo](11-mod-ldgvideo.md) | 4 | corrigido |

Total: 54 findings, 54 corrigidos (checkpoints 1-11).

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

## Checkpoint 9 — course/format/ldg (2026-09-22)

Seis achados corrigidos em worktree `dev` (ainda não commitados): bloqueio em
`lessonviewer` + CSS do cadeado, `catalog::has_visible()` na navegação,
`get_selected_cm()` devolvendo a lição pedida bloqueada, `durations_for()`
uma vez por página (contagem de leituras em teste), catálogo compartilhado
por página, e teste de paridade `section_progress` vs `cmsummary` do core.
phpunit `format_ldg_testsuite`: 70 testes OK. phpcs `--standard=moodle` limpo
em `public/course/format/ldg`.

## Checkpoint 10 — theme/ldg (2026-09-22)

Cinco achados corrigidos em worktree `dev` (ainda não commitados): cache
estático de `theme_config` via `settings::theme_config()`, helper
`util\layouthead` para more-menu e sitename das duas layouts, regra de sigla
única em `landing_page::language_short()` com delegação do tema, docblock do
`version.php` apontando Boost. phpunit `theme_ldg_testsuite`: 15 testes OK;
`local_partners_testsuite`: 86 OK (1 skip). phpcs limpo em
`public/theme/ldg`.

## Checkpoint 11 — mod/ldgvideo (2026-09-22)

Quatro achados corrigidos em worktree `dev` (ainda não commitados): a
canonicalização do Vimeo passou a usar o host stripado (www prefixado não
quebrava mais), a regra de YouTube ganhou guarda contra `videoseries` (embed
de playlist deixou de virar `watch?v=videoseries`), a checagem de
self-hosted virou fronteira de domínio via `url::is_self_hosted()` (prefixo
de string não marcava mais CDN que só compartilha o prefixo do wwwroot), e o
webservice passou a validar contexto e `mod/ldgvideo:view` por cm, com
`errornopermissions` em `warnings`. phpunit `mod_ldgvideo_testsuite`:
43 testes, 118 asserções, OK. phpcs limpo em `public/mod/ldgvideo`.
