# Code review — plugins customizados LDG Courses (dev vs origin/main)

## Context

O usuário pediu um code review completo do código implementado nas últimas versões de todos os plugins customizados do projeto LDG Courses, em ordem de prioridade. Medição prévia (`git diff --stat origin/main...origin/dev`) mostrou que o escopo é **482 arquivos, ~73.800 linhas inseridas** — o histórico de implementação inteiro de cada plugin, não um diff de PR. O contrato padrão da skill de code-review (8 achados totais, 8 ângulos × 6 candidatos) é calibrado para diffs de tamanho de PR; aplicado literalmente aqui descartaria quase tudo só para caber no limite.

Decisões do usuário:
- **Checkpoint de 1 plugin por vez**, não em grupos, não tudo de uma vez.
- **Relatório completo primeiro** — nenhuma correção é aplicada até o usuário revisar os findings e escolher o que corrigir.
- **paygw_pagarme recebe o mesmo nível de escrutínio** que Mercado Pago/Asaas, incluindo lógica fina de webhook, apesar do split nunca ter rodado em produção.

`dev/CLAUDE.md` foi lido por completo e contém uma tabela de armadilhas conhecidas do Moodle/projeto e decisões arquiteturais que o review deve respeitar (não reportar como achado novo) e tratar violações como prioridade máxima.

## Unidade de review

Uma unidade de review = um plugin (não o diff inteiro de uma vez). Para cada plugin, rodam os 8 ângulos padrão da skill de code review, com até 6 findings por plugin (em vez de 8 no total), cada candidato passando por 1 rodada de verificação conforme a skill especifica.

## Ordem de prioridade e checkpoints

Um checkpoint = um plugin. Reportar findings do plugin e aguardar antes de seguir ao próximo.

1. `public/local/marketplace` (80 arquivos, 15.050 linhas)
2. `public/local/partners` (74 arquivos, 13.271 linhas)
3. `public/blocks/marketplace` (12 arquivos, 1.316 linhas)
4. `public/payment/gateway/mercadopago` (60 arquivos, 10.527 linhas)
5. `public/payment/gateway/asaas` (31 arquivos, 5.494 linhas)
6. `public/payment/gateway/pagarme` (42 arquivos, 7.406 linhas) — mesma profundidade dos outros dois gateways
7. `public/enrol/marketplace` (13 arquivos, 713 linhas)
8. `public/availability/condition/marketplace` (10 arquivos, 627 linhas)
9. `public/course/format/ldg` (54 arquivos, 6.799 linhas)
10. `public/theme/ldg` (70 arquivos, 9.034 linhas)
11. `public/mod/ldgvideo` (35 arquivos, 3.581 linhas)

## Regras de execução

- **Sem correções durante o review.** Apenas reportar. Correções só depois que o usuário revisar o relatório completo (todos os 11 checkpoints) e indicar o que corrigir.
- Respeitar as decisões arquiteturais e armadilhas conhecidas documentadas em `dev/CLAUDE.md` — não reabrir como achado o que já é decisão consciente registrada lá.
- **Usar os padrões de programação do projeto** (`coding-standards/README.md`) como referência ao avaliar cada achado — sinalizar onde o código diverge desses padrões, e ao propor correção (na fase posterior), seguir o padrão documentado em vez de inventar convenção nova.
- Usar `ReportFindings` ao final de cada checkpoint (um plugin), achados mais graves primeiro.

## Verificação

Não aplicável nesta fase (revisão apenas, sem edição de código). A verificação ocorrerá na fase de correção, depois que o usuário aprovar quais findings corrigir.
