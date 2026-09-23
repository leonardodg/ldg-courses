> **Situação:** em andamento · **Início:** 2026-09-23 · **Última sessão:** 2026-09-23
>
> **Resumo da sessão:** worktree `cs-moodle-extra` via `moodev new` (branch
> `feature/cs-moodle-extra`, base `origin/dev`, upstream a 0 commits).
> Varredura: `moodle` = 0 violações; `moodle-extra` = 0 erros / 134 warnings,
> todas `ConstantVisibility` em 38 arquivos. Usuário escolheu **opção A**.
>
> **Etapa 0 — local verde; commit/PR abertos:** `.phpcs.xml` versionado; 134
> constantes → `public const` (38 arquivos); CI com
> `moodle-plugin-ci phpcs --standard moodle-extra --max-warnings 0`;
> `docs/coding-standards/README.md` reescrito com hierarquia Moodle →
> moodle-cs → projeto (`moodle-extra`) → PSR-12 → PSR-1, PHP 8.3+, limites
> 132/180. **Verificação local:** phpcs `moodle-extra` e via `.phpcs.xml` = 0
> issues em 333 arquivos (`EXIT=0`); `moodle` continua limpo; **740 testes** nas
> 11 suites customizadas, todas OK. PR aberto para `dev`. **Pendente:** CI
> verde + revisão do usuário na doc de CS. **Etapa 1 (hub `docs/produto/`) só
> depois do checkpoint.**

# CS com moodle-extra e hub de produto — Plano

## Contexto

Dois trabalhos encadeados e aprovados na mesma conversa:

1. **Etapa 0 — CS:** o doc de padrão de código aponta só "o que o CI cobra" e
   remete o resto ao guia do dev; o template do Moodle e as convenções reais da
   base (PHP 8.3+, variáveis sem underscore, limites 132/180, classes em
   `classes/`) não estão consolidados num lugar só. O CI rodava
   `moodle-plugin-ci phpcs --max-warnings 0` com o standard **moodle**
   (padrão do moodle-plugin-ci). O `moodle-extra` de `moodlehq/moodle-cs`
   estende `moodle` e acrescenta boas práticas (visibilidade de constante,
   regras de dataProvider etc.).
2. **Etapa 1 — hub de produto:** preencher 6 documentos a partir de uma
   entrevista de 26 perguntas + docs existentes, sem duplicar `adr/`,
   `data-model/`, `brand/`, `architecture/`.

Ordem não negociável: **CS primeiro**, checkpoint (CI verde + revisão da doc),
depois o hub. Depois dos 6 prontos: lista de contradições/faltas/abertos antes
de qualquer código da fase de vídeo.

## Decisões

| Decisão | Alternativa recusada | Motivo |
|---|---|---|
| Adotar `moodle-extra` e corrigir as 134 constantes no mesmo PR | Só documentar (B) ou ficar no moodle (C) | B deixaria CI vermelho com `--max-warnings 0`; C não entrega o escopo C aprovado |
| `.phpcs.xml` na raiz com `phpcs.xml.dist` + `moodle-extra` | Só `phpcs.xml` | `phpcs.xml` é gitignore (grunt ignorefiles); `.phpcs.xml` tem precedência e versiona a escolha |
| CI: `moodle-plugin-ci phpcs --standard moodle-extra --max-warnings 0` | Depender só do `.phpcs.xml` local | O CLI do moodle-plugin-ci tem `--standard` com default `moodle`; explícito não depende de cwd |
| Correção só `const` → `public const` nas linhas do relatório | `phpcbf` global | Restrição permanente do projeto: nunca `phpcbf` global |
| Worktree `cs-moodle-extra` via `moodev new` | Trabalhar em `dev` | Padrão do projeto; worktree isolada, stack offset 0 reaproveitada |
| Hub em `docs/produto/` com índice em `docs/README.md` | Espalhar pelos docs atuais | Escopo A da entrevista; não duplica donos existentes |
| Números comerciais "quase" como campo **a confirmar** | Fechar percentuais agora | Entrevista marcou como quase; gate antes do release comercial |
| ADR novo supersede ADR-0005 | Apagar/reescrever 0005 | Histórico de decisão preservado |
| Não reescrever histórico em `ai-plans/` e `codereview/` com `moodle` | Batch rewrite de docs antigos | Registros históricos documentam o que rodou na época; só o **comando vigente** muda |

### Entrevista (campos que o produto usa)

- Frentes: **C** = trava de resolução **por mensalidade do vendedor**; **B** =
  Bunny nativo multi-tenant (1 library por empresa); **A** = BYOS. Ordem: **B
  com C dentro, depois A**.
- Planos (a confirmar): Free ~10% (YouTube **ou** Bunny limitado); ~50–100
  remove limite (SaaS paga banda — **calcular custos antes**); ~300 = ~5% +
  BYOS. Assinatura = paymentarea `'plan'` existente.
- Fora da v1: Cloudflare, troca do `mod_ldgvideo`, Fase 5 moodledata, cota de
  banda por empresa.
- Sucesso: gate de release (trava de resolução) + 1 venda em cada degrau.
- Base Bunny: fork de `amirtds/moodle-mod_bunnystream` + multi-tenant.
- Trava no player (`core_media_manager`), não na origem (player).
- Usuário protagonista: vendedor/empresa.

## O que mudou (ou vai mudar)

### Etapa 0 (concluída no local; falta commit/PR/checkpoint)

- [x] `docs/ai-plans/2026-09-23-cs-moodle-extra-e-hub-produto.md` (este)
- [x] `.phpcs.xml` novo (estende `./phpcs.xml.dist` + `moodle-extra` + excludes
      de terceiros)
- [x] `docs/coding-standards/README.md` reescrito (hierarquia, PHP 8.3+,
      `public const`, limites 132/180, comando `moodle-extra`)
- [x] 134 constantes → `public const` em 38 arquivos / 11 plugins
      (**135** linhas no diff; 0 `const` sem visibilidade restantes)
- [x] `.github/workflows/deploy.yml`: phpcs com `--standard moodle-extra`
- [x] Docs de comando atualizados: `padrao-de-implementacao.md`,
      `guia-desenvolvedor.md`, `CLAUDE.md`, README do `paygw_pagarme`
- [x] Verificação local: phpcs total + 11 suites phpunit
- [x] Commit + push + PR para `dev`
- [ ] CI verde + revisão do usuário na doc de CS (checkpoint Etapa 0)

### Etapa 1 (depois do checkpoint)

- [ ] `docs/produto/`: `README.md`, `prd.md`, `trd.md`, `fluxo-do-app.md`,
      `briefing-ui-ux.md`, `schema-backend.md`, `plano-implementacao.md`
- [ ] Linha em `docs/README.md`
- [ ] ADR que supersede ADR-0005 (trava por mensalidade do vendedor + player)
- [ ] Lista de contradições/faltas/abertos para o usuário

## Descobertas

- `moodle-extra` **já estende** `moodle`; as 134 acusações do standard extra
  eram **só** `PSR12.Properties.ConstantVisibility.NotFound`.
- `phpcs.xml` está no `.gitignore` (gerado por `npx grunt ignorefiles`);
  versionar a escolha do projeto é `.phpcs.xml`.
- `moodle-plugin-ci phpcs` aceita `--standard|-s` (default `moodle`).
- Na imagem do devcontainer, `moodle-extra` está instalado; o ruleset.xml
  interno se chama `moodle-strict` mas o standard se invoca como `moodle-extra`.
- Report `full` multi-linha: o código do sniff fica na **linha seguinte** ao
  número; parser ingênuo perde 14 de 134 hits — o fechamento final foi
  varredura `^\s*const\s+` nos 11 plugins (0 restantes) + phpcs de confirmação.
- Container offset 0 (`ldg-courses-moodle-1`) monta a worktree
  `cs-moodle-extra` em `/var/www/html` — medido no `docker inspect`.

## Verificação

| O quê | Como | Resultado |
|---|---|---|
| phpcs `moodle` baseline | `phpcs --standard=moodle --report=summary` nos 11 | **0 violações** (333 arquivos) |
| phpcs `moodle-extra` baseline | idem com `moodle-extra` | **0 erros / 134 warnings** (antes da correção) |
| phpcs após correção (`moodle-extra`) | `--report=source` + exit code | **0 issues, EXIT=0** em 333 arquivos |
| phpcs após correção (`.phpcs.xml` na raiz) | sem `--standard`, usa `.phpcs.xml` | **0 issues** em 333 arquivos |
| `const` sem visibilidade | scan `^\s*const` nos 11 plugins | **0** |
| phpunit `local_marketplace` | `--testsuite local_marketplace_testsuite` | **OK (165 tests, 570 assertions)** |
| phpunit `local_partners` | … | **OK (86 tests, 248 assertions), 1 skipped** |
| phpunit `theme_ldg` | … | **OK (15 tests, 30 assertions)** |
| phpunit `paygw_asaas` | … | **OK (69 tests, 157 assertions)** |
| phpunit `paygw_mercadopago` | … | **OK (127 tests, 308 assertions)** |
| phpunit `paygw_pagarme` | … | **OK (119 tests, 213 assertions)** |
| phpunit `enrol_marketplace` | … | **OK (13 tests, 35 assertions)** |
| phpunit `availability_marketplace` | … | **OK (12 tests, 21 assertions)** |
| phpunit `block_marketplace` | … | **OK (21 tests, 32 assertions)** |
| phpunit `format_ldg` | … | **OK (70 tests, 182 assertions)** |
| phpunit `mod_ldgvideo` | … | **OK (43 tests, 118 assertions)** |
| **Total phpunit** | 11 suites customizadas | **740 tests, todas OK** (1 skipped em partners) |
| CI | job `validate` do PR com `--standard moodle-extra` | **a medir no PR** |

## Em aberto

- **Checkpoint Etapa 0:** commitar → push → PR → CI verde → revisão do usuário
  em `docs/coding-standards/README.md`. Só então Etapa 1.
- Etapa 1 (hub) **não começou**.
- Números de planos/comissão: **a confirmar** no PRD.
- Custo de banda vs mensalidade: gate no PRD antes de liberar degrau pago.
- Prova com dinheiro real da assinatura SaaS Start/PRO (plano anterior, ainda
  `inacabado`).
- Lista final de contradições/faltas **só depois** dos 6 docs da Etapa 1.
