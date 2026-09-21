# Code review dos 11 plugins customizados do LDG Courses

## Contexto medido

- Repo bare com worktrees `dev`, `main`, `MOODLE_502_STABLE`. `dev` é a
  worktree de repouso onde os 11 plugins vivem.
- `git diff --stat origin/main...origin/dev` nos 11 diretórios pedidos: **482
  arquivos, ~73.800 linhas inseridas**. Isto não é um PR — é o histórico
  inteiro de implementação de cada plugin desde que nasceu, sem baseline de
  revisão anterior. O contrato de saída do skill de review (8 achados no
  total, JSON) foi desenhado para diff de PR, não para 11 plugins inteiros.
- Tamanho por plugin (inserções, diff dev vs main):
  1. `local/marketplace` — 80 arquivos, 15.050 linhas (núcleo: empresas,
     ofertas, direitos, vendas, `service_provider`)
  2. `local/partners` — 74 arquivos, 13.271 linhas (captação/onboarding)
  3. `blocks/marketplace` — 12 arquivos, 1.316 linhas
  4. `payment/gateway/mercadopago` — 60 arquivos, 10.527 linhas
  5. `payment/gateway/asaas` — 31 arquivos, 5.494 linhas
  6. `payment/gateway/pagarme` — 42 arquivos, 7.406 linhas
  7. `enrol/marketplace` — 13 arquivos, 713 linhas
  8. `availability/condition/marketplace` — 10 arquivos, 627 linhas
  9. `course/format/ldg` — 54 arquivos, 6.799 linhas
  10. `theme/ldg` — 70 arquivos, 9.034 linhas
  11. `mod/ldgvideo` — 35 arquivos, 3.581 linhas
- `dev/CLAUDE.md` lido por inteiro: contém decisões de arquitetura não
  revisitáveis, armadilhas já mapeadas (tabela "Armadilhas do Moodle nesta
  base") e erros já cometidos. A revisão deve tratar violações dessas regras
  como achados de prioridade máxima, e não repetir armadilhas já catalogadas
  como se fossem descoberta nova.
- 395+ testes automatizados já existem e passam (por plugin, conforme
  `dev/CLAUDE.md`). A revisão não deve reimplementar cobertura que os testes
  já dão — deve mirar no que teste unitário/behat não pega (mencionado
  explicitamente no próprio CLAUDE.md: "sete defeitos só apareceram no
  navegador, e nenhum quebrou um teste").
- Modo plano ativo: nenhuma edição de código será feita nesta fase. Achados
  confirmados serão listados; correções reais (o "corrigir o código" do
  pedido) só acontecem depois de aprovação deste plano, plugin por plugin.

## Por que não dá para rodar o skill de review "como está"

O template pedido (`3+5 angles × 6 candidates → verify → ≤8 findings`) é
calibrado para um diff de tamanho de PR. Aplicado literalmente a 73.800
linhas, ele produziria no máximo 8 achados no total para 11 plugins — a
maioria do sinal se perderia por causa do teto de saída, não por ausência de
bugs. Vou adaptar assim:

- **Unidade de revisão = 1 plugin**, não o diff inteiro de uma vez.
- Rodar os 8 ângulos **por plugin**, na ordem de prioridade dada pelo
  usuário, mas com teto de candidatos por ângulo reduzido (até 4, não 6) e
  foco nos arquivos de maior risco do plugin (lógica de pagamento, split,
  webhook, controle de acesso, SQL, autenticação) em vez de 100% dos arquivos
  — plugins de UI pura (`theme_ldg`, parte do `course/format/ldg`) recebem
  menos ângulos de correção e mais peso em altitude/convenção.
  Cross-file tracer.
- **Verificação 1-voto** por candidato, como o skill pede.
- Teto de saída **por plugin**: até 6 achados confirmados/plausíveis,
  priorizando bugs de correção sobre limpeza — não 8 no total.
- Consolidar tudo em um relatório final, plugin por plugin, na ordem de
  prioridade pedida.

## Escopo por prioridade (ordem do pedido do usuário)

1. `local/marketplace` — núcleo (contas, ofertas, `entitlement`, split,
   `service_provider`)
2. `local/partners` — onboarding/captação (formulários públicos, XSS,
   validação)
3. `blocks/marketplace` — dashboard/onboarding widget
4. `payment/gateway/mercadopago` — split, assinatura por ciclo manual, `mp_client`
5. `payment/gateway/asaas` — split, webhook, criptografia de credencial
6. `payment/gateway/pagarme` — split (nunca provado em produção — ver
   memória "Pagar.me bloqueado no suporte"; olhar com cautela extra por não
   ter prova de campo)
7. `enrol/marketplace` — matrícula por diferença
8. `availability/condition/marketplace` — liberação de seção
9. `course/format/ldg` — formato de curso / landing
10. `theme/ldg` — tema, SCSS, layouts, renderer
11. `mod/ldgvideo` — embed de vídeo, regex de canonicalização

## Passos de execução (após aprovação)

Para cada plugin, na ordem acima:

1. Fork/agent le os arquivos de maior risco do plugin (identificados via
   `grep` por padrões sensíveis: SQL direto, `require_capability`, split de
   pagamento, webhook/assinatura, `sesskey`, saída HTML sem `s()`/`format_string`,
   criptografia).
2. Rodar os ângulos aplicáveis (A/B/C sempre; Reuse/Simplificação/Eficiência
   quando há lógica não-trivial; Altitude e Convenções sempre, citando a
   regra exata de `dev/CLAUDE.md` ou do CLAUDE.md de diretório mais próximo
   quando houver violação).
3. Verificar cada candidato (1 voto: CONFIRMED/PLAUSIBLE/REFUTED), descartar
   REFUTED.
4. Reportar até 6 achados por plugin, formato JSON pedido
   (`file`, `line`, `summary`, `failure_scenario`), mais severo primeiro.
5. Pausar após cada 2-3 plugins para checkpoint com o usuário (dado o volume,
   evita gastar o orçamento inteiro sem visibilidade), a menos que o usuário
   quera tudo de uma vez.

Correções de código (edição real) só serão aplicadas depois que os achados
de cada plugin forem revisados e aprovados pelo usuário — a listagem de
achados não implica edição automática.

## Perguntas antes de começar

1. **Quer o relatório completo dos 11 plugins de uma vez (pode levar muito
   tempo/tokens), ou prefere checkpoints a cada 2-3 plugins** para ajustar o
   foco no meio do caminho?
2. **Quando um achado for confirmado, aplico a correção na hora** (voltando a
   pedir aprovação para sair do modo plano) **ou prefere só o relatório
   primeiro, e decide depois quais corrigir**?
3. Devo tratar o `pagarme` com o mesmo nível de profundidade que os outros
   dois gateways, mesmo sem prova de campo (split nunca rodou em produção,
   conforme memória), ou focar ali só em bugs estruturais óbvios (não vale a
   pena caçar bug fino de webhook que nunca recebeu tráfego real)?
