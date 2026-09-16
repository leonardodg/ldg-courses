# Planos executados por agentes de IA

Registro de todo trabalho planejado e executado por agente de IA neste projeto.
Um arquivo por plano, em ordem cronológica.

## Por que isto existe

Um agente chega a cada sessão sem memória do que foi feito antes. O código conta
*o que* mudou; o commit conta *por quê* aquela mudança. Nenhum dos dois conta o
que foi **considerado e descartado**, quais premissas se mostraram falsas no
meio do caminho, ou o que ficou por fazer de propósito.

Sem esse registro, a sessão seguinte reabre discussões já encerradas — e às
vezes desfaz uma decisão sem saber que era decisão.

## Convenção de nome

```
AAAA-MM-DD-titulo-em-kebab-case.md
```

A data é a do **início** do trabalho.

**O arquivo chega aqui com nome gerado.** O `.claude/settings.json` da raiz
aponta o `plansDirectory` para este diretório, e o Claude Code batiza o plano com
um nome aleatório (`lovely-wandering-scott.md`). Renomeie para a convenção
**quando o trabalho for registrado no índice**, e não antes: a sessão em curso
ainda está escrevendo naquele caminho, e renomear no meio quebra o arquivo.

**Renomear não é estética — é o que impede a perda.** O nome gerado é
reaproveitado: em 02/09/2026 um plano novo caiu em `shiny-dreaming-popcorn.md` e
**apagou** o plano de 31/08 que morava ali, o maior já escrito neste projeto, com
as seis fases do `theme_ldg` e do `local_partners`. Ele só voltou porque o texto
aprovado ficou na transcrição da sessão. Um arquivo já renomeado para
`AAAA-MM-DD-titulo.md` nunca é alvo de sobrescrita.

## Situações

| Situação | Quer dizer |
|---|---|
| `executado` | terminou, e o que ficou de fora está na seção "Em aberto" |
| `inacabado` | parte do plano **não foi executada**, e a razão está no cabeçalho |
| `pendente` | não começou |
| `descartado` | não será executado; fica pelo que ele descarta |

A situação vai no **cabeçalho de cada arquivo** e na coluna do índice, para que
"o que ainda falta?" se responda lendo esta página, sem abrir sete documentos.

## O que cada plano deve conter

| Seção | Para quê |
|---|---|
| **Contexto** | o problema, e como ele apareceu |
| **Decisões** | o que foi escolhido, com a alternativa recusada e o motivo |
| **O que mudou** | arquivos criados, alterados, removidos |
| **Descobertas** | o que se aprendeu no caminho — em geral a parte mais cara de redescobrir |
| **Verificação** | como se provou que funciona, com o resultado real |
| **Em aberto** | o que ficou de fora, e por quê |

Duas regras que fazem a diferença entre registro útil e ata de reunião:

**Registre o que foi descartado.** "Consideramos X e recusamos porque Y" evita
que a próxima sessão gaste horas chegando ao mesmo Y.

**Registre o resultado medido, não a intenção.** "Deve ficar mais rápido" não
ajuda ninguém; "abrir uma worktree existente leva ~1s, medido" ajuda.

## Índice

| Data | Plano | Situação | Resultado |
|---|---|---|---|
| 2026-08-24 | [Plataforma, marketplace e Mercado Pago](2026-08-24-plataforma-marketplace-e-mercadopago.md) | `executado` | plano fundador; `paygw_mercadopago` nos PRs #27 a #48 |
| 2026-08-24 | [Tema filho do Moove (LeoDG Academy)](2026-08-24-leodg-academy-tema-filho-do-moove.md) | `descartado` | 46 passos, nenhum executado; o `theme_ldg` deixou de depender do Moove no PR #64 |
| 2026-08-24 | [Spec do tema filho do Moove](2026-08-24-leodg-academy-tema-spec.md) | `descartado` | a spec do plano acima; a paleta dela não é a que valeu |
| 2026-08-26 | [Plano original: `cf` e as worktrees](2026-08-26-plano-original-cf-worktrees.md) | `executado` | o plano; entregue nos PRs #50, #51, #52 |
| 2026-08-26 | [`cf`, fluxo de worktrees](2026-08-26-cf-fluxo-de-worktrees.md) | `executado` | o registro do ciclo, com o que se aprendeu depois |
| 2026-08-27 | [Plano original: Asaas e Pagar.me](2026-08-27-plano-original-asaas-e-pagarme.md) | **`inacabado`** | Fases 0 e 1 entregues; **Fase 2 `paygw_pagarme` travada no CNPJ**, e dois itens do `paygw_mercadopago` em aberto |
| 2026-08-27 | [Gateways além do Mercado Pago](2026-08-27-gateways-asaas-e-pagarme.md) | `executado` | registro do ciclo: Asaas entregue e **split provado** |
| 2026-08-31 | [`theme_ldg`, `local_partners` e planos no banco](2026-08-31-theme-ldg-local-partners-e-planos.md) | `executado` | sete fases no PR #61; a última ponta da Fase 0 fechou no #68. **Recuperado da transcrição** — o arquivo tinha sido sobrescrito |
| 2026-09-02 | [`format_ldg` e o fechamento do tema](2026-09-02-format-ldg-e-fechamento-do-tema.md) | `executado` | PR #65 — `format_ldg` em `MATURITY_BETA` |
| 2026-09-03 | [Guarda de versão no `cf new`](2026-09-03-cf-new-guarda-de-versao.md) | `executado` | 2 commits na `dev`; destravou a worktree `fix-theme-ldg` |
| 2026-09-03 | [`mod_video`, YouTube para contas free](2026-09-03-mod-video-youtube.md) | `descartado` | o recorte virou o plano de 04/09; **o nome e a fronteira mudaram** |
| 2026-09-03 | [Portal do aluno no `format_ldg`](2026-09-03-portal-do-aluno-format-ldg.md) | `executado` | desenho aprovado, e implementado pelos planos 1 a 5 — PRs #66 e #67 |
| 2026-09-03 | [Portal, plano 1: chrome e layout](2026-09-03-portal-plano-1-chrome-e-layout.md) | `executado` | 5 commits, testes verdes; planos 2 a 4 a escrever |
| 2026-09-03 | [Portal, plano 2: destinos e catálogo](2026-09-03-portal-plano-2-destinos-e-catalogo.md) | `executado` | 5 commits; 52 testes e 12 cenários verdes |
| 2026-09-03 | [Portal, plano 3: marca e tipografia](2026-09-03-portal-plano-3-marca-e-tipografia.md) | `executado` | 4 commits; contraste e fontes medidos no Chrome |
| 2026-09-03 | [Portal, plano 4: conferência visual](2026-09-03-portal-plano-4-conferencia-visual.md) | `executado` | medidas no alvo, axe-core limpo |
| 2026-09-03 | [Portal, plano 5: ajustes do layout](2026-09-03-portal-plano-5-ajustes-do-layout.md) | `executado` | inclui o portal para gestor e o tema nas páginas de admin |
| 2026-09-04 | [Planos no projeto e limpeza de worktrees](2026-09-04-planos-no-projeto-e-limpeza-de-worktrees.md) | `executado` | PR #68 — trouxe os planos para o git e apontou o `plansDirectory` |
| 2026-09-04 | [`mod_ldgvideo` e os papéis de empresa](2026-09-04-mod-ldgvideo.md) | `executado` | plugin novo + a fronteira do plano Free nas duas camadas; 151 testes e 10 cenários verdes |
| 2026-09-08 | [Provar o split do Mercado Pago com duas contas](2026-09-08-mercadopago-split-duas-contas.md) | `executado` | o plano da prova; a conta usada como controle era PF, e isso mudou o resultado |
| 2026-09-09 | [Provas de pagamento e assinatura](2026-09-09-provas-de-pagamento-e-assinatura.md) | `executado` | PRs #77–#91 — split provado, assinatura, estorno, permissões; **leia antes de abrir a sessão do Pagar.me** |
| 2026-09-09 | [Briefing para a sessão do `paygw_pagarme`](2026-09-09-briefing-para-a-sessao-do-pagarme.md) | `executado` | o prompt inicial, o que ler, o que medir antes de codar e os cenários a reproduzir |
| 2026-09-09 | [`paygw_pagarme` — o terceiro gateway](2026-09-09-paygw-pagarme.md) | `executado` | split provado em homologação **e em produção com dinheiro real**; assinatura com split é recusada pelo gateway, e a recorrência passa a ser negada na porta. Fecha a decisão: **o Asaas é o gateway do produto** |
| 2026-09-10 | [Runtime do `claude-seo`](2026-09-10-runtime-do-claude-seo.md) | `executado` | launcher com CRLF e `python3` sem `ensurepip`; o venv nasce do 3.13. **Chegou ao índice só em 14/09** — ficou com nome gerado por quatro dias |
| 2026-09-11 | [Rename do projeto: `courses-free` → `ldg-courses`](2026-09-11-rename-para-ldg-courses.md) | `executado` | PR #100, com guarda no deploy que reprova se o nome antigo voltar. **Chegou ao índice só em 14/09** |
| 2026-09-15 | [Mercado Pago: assinatura com split, e uma aplicação por tipo](2026-09-15-mercadopago-assinaturas.md) | `inacabado` | o `preapproval` **não tem campo de comissão nenhum**, medido com a aplicação do tipo certo; a assinatura passa a sair por `/v1/payments` com `application_fee` e cartão guardado no MP. Três aplicações, captura de cartão em três modos com escopo PCI declarado. **A prova com dinheiro real não passou** — o ponto de retomada está no topo do plano |
