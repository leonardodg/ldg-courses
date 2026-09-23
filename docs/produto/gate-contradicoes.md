# Gate — contradições, faltas e pontos em aberto

> Pré-condição 1 do [`plano-implementacao.md`](plano-implementacao.md): varredura
> do que o hub (`docs/produto/*`), os ADRs, o `data-model`, o `architecture` e o
> desenho Start/PRO implementado dizem **ao mesmo tempo** e não batem. Este
> documento é o **portão antes de qualquer código de fase de vídeo**: nada abaixo
> se resolve sem evidência no repo e aceite do usuário. Nenhum item foi corrigido
> em silêncio — só apontado, com caminho e o quê.

**Data da varredura:** 2026-09-23 · **Branch:** `feature/produto-hub`

---

## 1. Contradições

Onde os docs do hub ou o hub vs docs existentes do repo **discordam**.

### C1. Hub diz que o ADR supersor "vai ser escrito" — o ADR-0014 já existe

O ADR-0014 existe e o índice de ADRs já o marca:

- [`../adr/0014-trava-de-resolucao-por-mensalidade-do-vendedor.md`](../adr/0014-trava-de-resolucao-por-mensalidade-do-vendedor.md) — **Situação: Proposta** (2026-09-23), supera o 0005.
- [`../adr/README.md`](../adr/README.md) — linha 0005 = "Superada por ADR-0014"; linha 0014 = "Proposta".

Fora deste gate, **nenhum doc do hub cita o 0014** (`grep -rn 0014 docs/produto/ --exclude=gate-contradicoes.md` = 0; os 16 hits do grep sem exclude são todos deste arquivo). `prd.md`, `trd.md`, `plano-implementacao.md` e `schema-backend.md` ainda citam o 0005 / "novo ADR" sem apontar para o 0014 e descrevem a escrita como futura:

| Arquivo | O quê |
|---|---|
| [`prd.md`](prd.md) § Frente C (l. 44–47) | "está sendo **superado por um novo ADR**… a escrita desse ADR não é escopo deste documento" |
| [`prd.md`](prd.md) "Ver também" (l. 126) | "Trava de resolução (**será superada** pelo novo ADR)" aponta só para o 0005 |
| [`trd.md`](trd.md) (l. 57–63) | "O PRD registra que um **novo ADR** vai superá-lo… **Até lá**, a matemática de custo/margem do 0005 segue vale" |
| [`trd.md`](trd.md) pré-condição 6 (l. 152) | "Novo ADR que supera o 0005" listado como **pendente** — ele já foi escrito |
| [`plano-implementacao.md`](plano-implementacao.md) pré-condição 1 (l. 16) | "ADR novo **ainda não escrito**" |
| [`plano-implementacao.md`](plano-implementacao.md) "Ver também" (l. 112) | "Trava de resolução (**será superada** pelo novo ADR)" |
| [`schema-backend.md`](schema-backend.md) gap do player (l. 52) | aponta só o ADR-0005, não o 0014 |

**Status real:** 0005 superado, 0014 existe (Proposta). Contradição editorial + de apontamento: o hub aponta para o ADR errado como vigente/futuro. **Não corrigido aqui** (fora do escopo desta task — flag apenas).

**Nota de fronteira:** o 0014 está em **Proposta**, não em **Aceita**. Mesmo depois de corrigir os links, ainda resta a decisão de promovê-lo a Aceita antes de código de vídeo (ver A3).

### C2. Degraus do PRD/interview × planos implementados `start_*`/`pro` — tensão documentada, não reconciliada

Duas descrições coexistem e o próprio PRD admite que não batem ([`prd.md`](prd.md) § "Tensão", l. 79–90):

| Fonte | Degraus |
|---|---|
| Interview + [`prd.md`](prd.md) + [`README.md`](README.md) do hub | Free ~**10%** (YouTube **ou** Bunny limitado); ~**R$ 50–100** remove o limite; ~**R$ 300** = ~**5%** + BYOS — tudo **a confirmar** |
| [`../ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md`](../ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md) (implementado) | `start_free` / `start_50` / `start_100` (nativo, comissão **fixa 10%**, R$0/R$50/R$100) e `pro` (BYOS, **5%**, mensalidade **em aberto**) |

Onde divergem, concretamente:

1. **Comissão do degrau intermediário.** PRD: ~5% no degrau BYOS ~300; Start implementado: **10% em todos os tiers** (só resolução muda). O 5% só existe no `pro`. O PRD coloca ~5% **junto com ~300**, o que se alinha ao `pro` — mas o degrau do meio no PRD **não diz a comissão**, e o `start_50`/`start_100` são 10%.
2. **Mensalidade do BYOS.** PRD ~R$ 300 (**a confirmar**) × `pro.monthlyfee` **em aberto** no ai-plan (l. 115).
3. **Rótulos.** "Free / ~50–100 / ~300" × `start_free` / `start_50` / `start_100` / `pro` — quatro shortnames vs três degraus narrados.
4. **"Remove o limite".** PRD não diz qual resolução o tier intermediário destrava; o seed do `install.php` já grava `start_50` → 1080p e `start_100` → 4k. O próprio ai-plan ainda lista "mapeamento exato de resolução por tier" em "O que sobra" (l. 116–117) apesar de o seed já existir — texto do ai-plan desatualizado em relação ao próprio seed.

**Não resolvido.** PRD e TRD marcam como **a confirmar** / "ler o banco, nunca hardcode" ([`trd.md`](trd.md) l. 107–112). Requer decisão comercial do usuário (ver A1).

### C3. Nome da tabela: `local_marketplace_account` × `local_marketplace_company_account`

A **tabela real** é `local_marketplace_account`:

- [`../data-model/marketplace.md`](../data-model/marketplace.md) § `local_marketplace_account` (l. 132)
- `public/local/marketplace/db/install.xml` (l. 89)
- `public/local/marketplace/classes/company_account.php` → `public const TABLE = 'local_marketplace_account'` (a **classe** se chama `company_account`; a **tabela** não)

Docs que usam o nome **inexistente** `local_marketplace_company_account`:

| Arquivo | O quê |
|---|---|
| [`trd.md`](trd.md) Billing, linha 99 | "sem linha em `local_marketplace_company_account`" |
| [`fluxo-do-app.md`](fluxo-do-app.md) § 5, linha 189 | "sem linha em `local_marketplace_company_account`" |

O hub está **dividido**: [`schema-backend.md`](schema-backend.md) (l. 34) e [`../data-validation/assinatura-saas-plano-empresa.md`](../data-validation/assinatura-saas-plano-empresa.md) (l. 45) usam o nome correto `local_marketplace_account`. O ai-plan Start/PRO também erra nas linhas 46 e 66 (`local_marketplace_company_account`). **Não corrigido** — flag só.

### C4. `decisoes-marketplace.md` §3 "sem aprovação manual" × aprovação manual real

- [`../architecture/decisoes-marketplace.md`](../architecture/decisoes-marketplace.md) § 3 (l. 42–44): título "Dois portões estruturais, **sem aprovação manual**" e "Qualquer um se cadastra e **cria empresa**".
- Realidade: criação de empresa é **manual**, via aprovação em `local_partners` — [`fluxo-do-app.md`](fluxo-do-app.md) § 1 ("Aprovação: **Manual**"), `CLAUDE.md` ("Sem auto-atendimento para criar empresa"), [`../adr/0006-aprovacao-automatica-de-parceiro.md`](../adr/0006-aprovacao-automatica-de-parceiro.md) (**Proposta**: automação futura com defesas).
- O próprio `ai-plan` 2026-09-17 (l. 48) confirma "manual por enquanto", mas ainda cita um setting `approvalmode` que **não existe no código** (`grep approvalmode public/` = 0); o mockup corrigido já anota isso ([`../design/block-marketplace-onboarding/html-mockups/layout-completo.html`](../design/block-marketplace-onboarding/html-mockups/layout-completo.html) l. 225).

**Não corrigido** (relatório da task 4 já abriu a mesma nota). O fluxo do hub segue o comportamento correto; o doc de arquitetura é que está defasado.

### C5. Percentuais de comissão — conflitos reais (só os reais)

| Percentual | Onde | Situação |
|---|---|---|
| **9,9%** | [`../legal/termos-de-uso.md`](../legal/termos-de-uso.md) l. 66 (exemplo numérico da cláusula de comissão) | **Conflito real com o vigente pretendido:** PRD/ai-plan usam 10%/5%; o termo de uso ainda exemplifica 9,9% do plano Starter arquivado |
| **10% / 5%** | PRD, README do hub, ai-plan Start/PRO, mockups `d4-step-plan.html` | Vigente pretendido (a confirmar no PRD) |
| **25%** | fallback do site / provas em `data-validation/`, `decisoes-marketplace.md` l. 117 | Comissão **do site** para venda de curso — não é comissão de plano; **não** é conflito com 10%/5% (eixos diferentes: venda B2C vs degrau SaaS). Só fica aqui para não confundir leitura |
| **9,9% / 3,9% / 0%** (Starter/Pro/Scale) | [`../adr/0005…`](../adr/0005-trava-de-resolucao-por-ticket.md) l. 8, ai-plan 2026-08-31, ADR-0007 contexto histórico | Planos **arquivados**; ADR-0005 está superado. Histórico, não conflito ativo |
| **15% / 8%** | mockups descartados `m3e-screens/` | Descartados; não contam |

**Conflito real a decidir:** termos de uso vs planos novos (C5-1). Os ADRs (0007 etc.) não conflitam entre si sobre **base** (bruto, configurável, fotografada) — só o **número exemplo** do legal está velho.

### C6. Editorial: "6 documentos" × 7 arquivos

- [`../ai-plans/2026-09-23-cs-moodle-extra-e-hub-produto.md`](../ai-plans/2026-09-23-cs-moodle-extra-e-hub-produto.md) l. 38: "preencher **6 documentos**"; checklist l. 100–101 lista **7 caminhos** (`README.md` + os 6).
- [`README.md`](README.md) do hub indexa **7** documentos na tabela + ele mesmo (inclui este gate).

Editorial (README + 6 ≠ "6 documentos" sem qualificar). Sem impacto de produto; marcar se o ai-plan for atualizado.

---

## 2. Faltas

Peças que o produto/requisitos pedem e **não existem** no repo (verificado, não presumido).

### F1. Mapeamento `company` ↔ `library` do Bunny — não modelado

- Requisito: [`trd.md`](trd.md) Frente B; [`plano-implementacao.md`](plano-implementacao.md) sub-passo 1 (pré-condição da C e do medidor de custo).
- Verificado: nenhuma tabela em `docs/data-model/marketplace.md` liga empresa a biblioteca Bunny; `grep -iE 'bunny|library'` em `public/local/marketplace` = 0. Gap já listado em [`schema-backend.md`](schema-backend.md) l. 51.

### F2. Storage da chave de API BYOS — não existe

- [`trd.md`](trd.md) Frente A: `plan.hostingmodel` é **rótulo** `native|byos`; sem coluna/tabela de chave e sem código que troque destino de upload.
- Confirmado em `data-model` (`hostingmodel` só documenta o enum) e no ADR-0014 (l. 88–93, dívida herdada do 0005). Gap em [`schema-backend.md`](schema-backend.md) l. 50.
- Enquanto não fechar: **BYOS não pode ser vendido como entregue** (TRD e plano dizem o mesmo).

### F3. Player consumidor de `max_resolution_for` — dado existe, consumidor não

- `plan::max_resolution_for(float $price)` e `local_marketplace_plan_tier` existem (`public/local/marketplace/classes/plan.php` l. 218; tiers semeados com `maxprice = null` em `db/install.php`).
- `grep max_resolution_for` em `public/` **fora** de `local/marketplace/{classes,tests,db}` = **0**. Nada no `core_media_manager` lê o teto.
- Assinatura **ainda é por preço de ticket**; o retorno passou a refletir o tier do plano só porque `maxprice = null` — a chamada de player ainda não existe (TRD l. 41–50; ADR-0014 l. 38–39).

### F4. Ferramenta de cálculo de banda × mensalidade — não existe

- Pré-condição 2/3 do [`plano-implementacao.md`](plano-implementacao.md) e critério de sucesso 3 do [`prd.md`](prd.md): gate de custo **antes** de qualquer degrau pago.
- Verificado: nenhuma calculadora/script em `docs/`, `scripts/` ou código — só a exigência textual (TRD pré-condição 4). **Gate de release do PRD não tem artefato.**

### F5. Prova com dinheiro real da assinatura SaaS — ainda `inacabado`

- [`../ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md`](../ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md) cabeçalho: **inacabado** — "falta a prova com dinheiro real".
- [`../ai-plans/2026-09-17-assinatura-saas-implementacao-fases-a-e.md`](../ai-plans/2026-09-17-assinatura-saas-implementacao-fases-a-e.md) l. 6–10: Fases A–E verdes, "prova com dinheiro real **não aconteceu ainda**".
- [`../data-validation/assinatura-saas-plano-empresa.md`](../data-validation/assinatura-saas-plano-empresa.md) l. 9: "**Nada disto foi clicado ainda.**"
- O critério de pronto da B+C exige "**1 venda real em cada degrau**" — sem provar a cobrança do plano (B2B), o degrau comercial não tem lastro.

### F6. `approvalmode` citado em docs, ausente do código

- Citado como se existisse em `ai-plans/2026-09-17-ativacao-empresa-desenho-telas.md` l. 23 e `ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md` l. 48.
- `grep approvalmode` em `public/` = **0**. Mockup já corrigido; os dois ai-plans e o `decisoes-marketplace.md` §3 (C4) seguem defasados.

---

## 3. Abertos

Decisões e confirmações que **só o usuário** fecha. Nada aqui foi presumido como resolvido.

### A1. Todos os números comerciais `a confirmar`

Comissões ~10% / ~5%, mensalidades ~R$ 50–100 / ~R$ 300, e o valor exato de `pro.monthlyfee` — em [`prd.md`](prd.md) l. 64–71, 128–129; [`README.md`](README.md) do hub l. 36–39; [`plano-implementacao.md`](plano-implementacao.md) pré-condição 2. Enquanto abertos: **nenhum código assume percentual/faixa** (TRD). Inclui a reconciliação C2 (rótulos e tier→resolução).

### A2. Cloudflare fora da v1 — **fechado** (reafirmação, não é aberto)

Decidido e repetido sem ambiguidade em [`prd.md`](prd.md) não-objetivos, [`trd.md`](trd.md) Frente B + riscos, [`README.md`](README.md) do hub, [`schema-backend.md`](schema-backend.md), [`plano-implementacao.md`](plano-implementacao.md) § 3, ADR-0014. **Não reabrir por esta lista.**

### A3. Situação do ADR-0014: Promover de `Proposta` para `Aceita` antes do código?

O 0014 está **Proposta** ([`../adr/README.md`](../adr/README.md)). O gate exige "registro formal" (TRD pré-condição 6 — que já aponta para um ADR que existe, mas não está Aceita). Depender de um ADR Proposta para abrir branch de vídeo é ambíguo: **aceitar o 0014 (ou recusar) é decisão do usuário.**

### A4. Ordem de refinamento residual

Ordem **B+C → A** está aprovada e coerente no hub. Resíduos que podem mudar o miolo da sequência:

- Quem **provisiona** a `library` por empresa (admin? upgrade? automático no `create_company`?) — TRD/plano deixam "onde viverá e quem provisiona" em aberto (sub-passo 1).
- Assinatura de `max_resolution_for` ainda `float $price` — se o player passar a chamar pelo **plano da empresa**, a assinatura provavelmente muda (parâmetro/planid). Não decidido; afeta o sub-passo 3 do plano.
- Origem assinada no Bunny: TRD marca como **obrigatória em Bunny**; ADR-0014 rebaixa a "consequência do modelo de cobrança". Compatível, mas a ordem interna da frente C (player primeiro ou os dois juntos) não está fechada além do outline do plano.

### A5. Pressupostos que PRD/TRD deixaram como hipóteses abertas (listar para aceite explícito)

| Pressuposto | Onde | O que falta |
|---|---|---|
| Free = YouTube **ou** Bunny limitado (dois modos no mesmo degrau) | PRD tabela de degraus | Qual dos dois (ou ambos) no dia do gate; `mod_ldgvideo` cobre só o YouTube embed |
| "1 venda real em cada degrau" inclui venda **B2B de plano** e não só venda de curso | PRD critério 2 / plano critério 2 | Confirmar que a prova SaaS (F5) é exigência do mesmo gate |
| Pagar.me nunca cobra plano (recorrência recusada) | TRD billing | Confirmar que o gate de venda real aceita só MP e/ou Asaas para o degrau |
| Degrau intermediário "remove o limite" = 1080p (seed `start_50`) | seed `install.php` × PRD que não nomeia a resolução | Confirmar o mapeamento publicamente vs só no banco |
| Termos de uso com exemplo 9,9% podem ficar até o fechamento comercial | `legal/termos-de-uso.md` | Se publicar planos novos antes disso, o termo mente (C5) |

---

## Como usar este gate

1. O usuário lê as três seções e responde item a item (aceitar / corrigir em task própria / recusar).
2. Correções de doc (C1 links→0014, C3 nome de tabela, C4 `decisoes-marketplace`, C5 termos) são **tasks de doc separadas** — este gate **não** as faz.
3. Só com **C1–C6 tratadas ou aceitas como estão**, **F1–F5 com dono e data** e **A1/A3/A5 decididos**, a pré-condição 1 do [`plano-implementacao.md`](plano-implementacao.md) se fecha e a frente B+C pode nascer.
4. A pré-condição 3 (cálculo de banda — F4) e a venda real por degrau (F5 + critério 2) **não** são desfeitas por este documento: continuam gates antes de liberar degrau pago.

---

## Ver também

| Assunto | Dono |
|---|---|
| Intenção de produto, ordem das frentes, gates | [`prd.md`](prd.md) |
| Requisitos técnicos, pré-condições, riscos | [`trd.md`](trd.md) |
| Sequência e critérios de pronto (gate formal) | [`plano-implementacao.md`](plano-implementacao.md) |
| Gaps de schema | [`schema-backend.md`](schema-backend.md) |
| Trava de resolução (vigente: 0014 Proposta) | [`../adr/0014-…`](../adr/0014-trava-de-resolucao-por-mensalidade-do-vendedor.md) · [`../adr/0005-…`](../adr/0005-trava-de-resolucao-por-ticket.md) (superado) |
| Planos Start/PRO implementados | [`../ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md`](../ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md) |
| Prova SaaS com dinheiro real | [`../data-validation/assinatura-saas-plano-empresa.md`](../data-validation/assinatura-saas-plano-empresa.md) |
| Tabelas e campos | [`../data-model/marketplace.md`](../data-model/marketplace.md) |
