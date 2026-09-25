# TRD — Requisitos técnicos do hub de produto

> Produto: marketplace Moodle 5.2 com split de pagamento e frentes de vídeo.
> Este documento responde **o que precisa ser tecnicamente verdade** para o
> [`prd.md`](prd.md) se sustentar. Não redesenha arquitetura nem schema — o
> detalhe continua nos donos próprios (ADRs, `data-model`, `architecture`,
> `ai-plans`), e aqui ficam só os requisitos e os links.

## Fronteira técnica das frentes

Ordem alinhada ao PRD: **B** (com **C** dentro) → **A**. Cada frente tem uma
fronteira técnica explícita; o que não está listado aqui não é requisito desta
versão.

### Frente B — Bunny multi-tenant

| Requisito | Detalhe |
|---|---|
| Uma `library` do Bunny por empresa | Isolamento de vídeo no mesmo nível do isolamento de empresa que já existe para categorias e contas de pagamento |
| Base de código | Fork de `amirtds/moodle-mod_bunnystream` + camada multi-tenant (mapeamento empresa ↔ `library`) |
| Sem Cloudflare na v1 | Provedor escolhido é Bunny; trocar de provedor depois é reversível **só se a trava existir** (ver riscos) |

O mapeamento empresa ↔ `library` é pré-condição da frente C: sem origem
identificada por empresa, não há de onde aplicar a trava nem de onde medir
custo por empresa.

**Fora da v1 nesta fronteira:** Cloudflare, cota de banda por empresa,
qualquer caminho que sirva vídeo pelo `moodledata` (Fase 5 — bloqueada por
decisão de negócio, não por técnica; ver
[`../architecture/estado-e-proximas-fases.md`](../architecture/estado-e-proximas-fases.md)).

### Frente C — trava de resolução

A trava é aplicada **no player**, consumida via `core_media_manager`. A
**source of truth** do teto continua sendo o dado de plano/tier
(`local_marketplace_plan_tier` e `plan::max_resolution_for()`), **não** uma
regra nova no front-end.

Verificado no código desta worktree:

- `plan::max_resolution_for(float $price)` continua com assinatura **por
  preço de ticket**; os tiers semeados dos planos Start já são **uma faixa
  única com `maxprice = null`** por plano (`start_free` → `720p`,
  `start_50` → `1080p`, `start_100` → `4k`), ou seja: o parâmetro preço
  deixou de discriminar e o retorno passou a refletir o **tier contratado
  pela empresa** (`public/local/marketplace/db/install.php`).
- `plan` BYOS (`pro`) nasce **sem tiers** — `max_resolution_for()` devolve
  `null` (não há margem de banda da plataforma a proteger).
- **Nada no player consome o método ainda**: só testes unitários e a
  própria classe. A trava é promessa no banco, como o ADR-0005 já registra.

| Camada | Requisito | Obrigatório? |
|---|---|---|
| Player (`core_media_manager`) | Ler o teto do plano/tier da empresa e **não oferecer** trilha acima dele no seletor de qualidade | **Sim** — é o que o aluno vê; sem isso o limite parece defeito |
| Origem (URL assinada com teto de resolução) | Restringir trilhas na origem quando o provedor **cobra por volume** (Bunny) | **Sim, em Bunny** — player-side sozinho é contornável (`devtools` → `.m3u8` → trilha 4K) e a trava deixa de proteger o custo |

O ADR-0005 ([`../adr/0005-trava-de-resolucao-por-ticket.md`](../adr/0005-trava-de-resolucao-por-ticket.md))
descrevia o gatilho antigo (ticket do curso) e a dualidade player+origem.
Ele foi **superado pelo [ADR-0014](../adr/0014-trava-de-resolucao-por-mensalidade-do-vendedor.md)**
("trava por mensalidade do vendedor + player"), **Aceito em 2026-09-25**. A
matemática de custo/margem do 0005 segue valendo como referência histórica.

### Frente A — BYOS

| Requisito | Detalhe |
|---|---|
| Chave de API da conta de streaming do produtor | O produtor **guarda a própria chave**; a plataforma não hospeda nem paga a banda neste degrau |
| `plan.hostingmodel` | Coluna `native` \| `byos` em `local_marketplace_plan` — **é rótulo**: não há armazenamento de chave e não há código que troque destino de upload conforme o plano |

**Lacuna documentada (gap):** enquanto não existir (a) onde gravar a chave
por empresa e (b) quem aplica a chave no upload/stream, **BYOS é promessa
comercial sem peça técnica** — mesmo raciocínio do ADR-0005 sobre
`hostingmodel`. Vender o degrau BYOS antes disso é vender o que não se
entrega.

### O que **não** muda na v1

- **`mod_ldgvideo` continua.** Nenhuma substituição nesta versão. É a peça
  do plano Free: embed de serviço externo, banda zero para a plataforma.
- **Embed continua pelo `core_media_manager`** — reconhecimento e player são
  do core; o plugin guarda URL e corrige proporção por CSS. Fronteira:
  qualquer player habilitado, menos o `wwwroot`. Ver
  [`../adr/0008-embed-multiplataforma-pelo-core.md`](../adr/0008-embed-multiplataforma-pelo-core.md).

## Billing / assinatura (B2B — empresa paga a plataforma)

A cobrança da mensalidade SaaS **já está implementada**; este TRD só fixa os
requisitos que o PRD pressupõe. Mecânica, fases e pendências de prova com
dinheiro real:  
[`../ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md`](../ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md)  
(**não redesenhar aqui**).

| Requisito | Como está |
|---|---|
| Paymentarea | **`'plan'`** em `local_marketplace\payment\service_provider` (`PAYMENT_AREA_PLAN`), ao lado de `'offer'` |
| `itemid` | **`companyid`** — nunca `offerid`; cada callback confere a paymentarea antes de interpretar o id |
| Recebedor | Conta **`core_payment\account` da plataforma**, no **contexto do site**, sem linha em `local_marketplace_account` (`api::get_or_create_platform_account()` / `api::is_platform_account()`) |
| Split | **Não existe em `'plan'`.** A plataforma fica com 100%. `record_sale()` não grava venda para esta paymentarea |
| O que a empresa entrega ao pagar | Não é `local_marketplace_entitlement` (tabela de venda de curso). O acesso/degrau fica em `company.planid` + estado da assinatura do plano — ver desenho Start/PRO |

As três assinaturas permanecem distintas ([`../adr/0012-duas-assinaturas-e-so-uma-tem-split.md`](../adr/0012-duas-assinaturas-e-so-uma-tem-split.md)):
B2B (`'plan'`, sem split) e B2C (oferta recorrente, com split) não podem ser
confundidas no mesmo fluxo.

**Conflito em aberto (de produto, refletido aqui):** o PRD ainda fala em
degraus Free / ~50–100 / ~300 **a confirmar**, enquanto o desenho
implementado usa `start_free` / `start_50` / `start_100` / `pro`. Enquanto
não reconciliados, o requisito técnico é: **ler o plano do banco, nunca
hardcodar comissão ou mensalidade** (campos `commissionpct` /
`monthlyfee` / tiers). Detalhe da tensão: seção "Tensão" no PRD.

## Interfaces e dependências

### Gateways

- **Asaas, Mercado Pago, Pagar.me** — a plataforma **não faz fork do Moodle**
  nem embute lógica de gateway no core; cada plugin declara moedas/países e
  o núcleo não cita nome de gateway.
- **Não criar paymentarea nova** para a assinatura de plano: `'plan'` já
  existe e cobre o caso. Paymentarea nova só se um fluxo de cobrança
  genuinamente distinto aparecer (hoje não há).
- Pagar.me recusa recorrência por `supports_recurring()` — comportamento
  desejado para plano, que é sempre recorrente.
- A cobrança B2B usa o mesmo motor de ciclo genérico dos gateways
  (`recurrence_for` / `commission_terms_for` / `record_sale` com parâmetro
  `$paymentarea`), já generalizado.

### Acesso

- **`local_marketplace_entitlement` continua a fonte única de acesso de
  aluno** (matrícula e liberação de seção leem o direito; ninguém lê a venda
  para decidir acesso). A assinatura de plano **não** substitui nem se mistura
  a essa tabela.
- A trava de resolução do jogador lê **plano/tier da empresa**, não o
  entitlement do aluno — dois eixos diferentes (quem pode assistir ≠ em qual
  qualidade a plataforma serve).

### Pré-condições antes de código da fase de vídeo

Lista do que **precisa existir** (chave, política ou ferramenta) **antes** de
começar a implementar as frentes B/C/A:

| # | Pré-condição | Por quê |
|---|---|---|
| 1 | Chaves/conta Bunny (e política de `library` por empresa) provisionáveis | Sem origem multi-tenant não há o que hospedar nem o que travar |
| 2 | Mapeamento empresa ↔ `library` (onde viverá e quem provisiona) | Fronteira da B; dependência da C e do medidor de custo |
| 3 | Hook/decisão no player (`core_media_manager`) consumindo `max_resolution_for` (ou equivalente do tier da empresa) | Sem isso a trava não existe no UX, mesmo com tiers no banco |
| 4 | Ferramenta de cálculo de custo de banda vs mensalidade | **Gate de release do PRD**: nenhum degrau pago liberado sem custo calculado |
| 5 | (BYOS) Definição de **onde** guarda a chave do produtor e quem a injeta no stream | Sem isso a frente A não tem peça técnica |
| 6 | ~~Novo ADR que supera o 0005~~ — **fechado**: [ADR-0014](../adr/0014-trava-de-resolucao-por-mensalidade-do-vendedor.md), Aceito em 2026-09-25 | Registro formal da trava por mensalidade + player |

## Riscos e limites técnicos

| Risco / limite | Consequência |
|---|---|
| **HLS/DASH adaptativo** | Não existe "entregar só 720p" sem intervir **no player ou na origem**. Manifesto único traz todas as trilhas |
| **Bunny cobra GB transferido** | 4K custa várias vezes 720p; bloqueio só no player é contornável e não protege a fatura. URL assinada (ou controle equivalente de origem) é adicional, não opcional, quando o provedor fatura por volume |
| **Cloudflare fatura por minuto** (fora da v1) | Com CF, player bastaria para o custo; a frente escolhida é Bunny — daí a trava precisar do par player+origem. Adiar CF mantém um provedor só e evita dois modelos de cobrança em paralelo; o custo é que a troca futura exige a trava de origem **antes** de migrar vídeo |
| **Fase 5 (`moodledata`)** | Fora da v1; bloqueio de negócio (modelo de cobrança de storage/banda), não de código |
| **Cota de banda por empresa** | Fora da v1; controle fino fica depois da trava de resolução (PRD não-objetivos) |
| **`hostingmodel` sem storage de chave** | Frente A não pode ser vendida como entregue até o gap fechar |
| **Trava sem consumidor no player** | Enquanto o `core_media_manager` não ler o teto, o gate de release 1 do PRD é falso — tiers no banco não valem como entrega |
| **Reconciliação de rótulos de plano** (PRD vs `start_*`/`pro`) | Código que assumir um dos dois conjuntos envelhece; requisito é configurar no banco |

## Onde viver o resto (não duplicar)

| Assunto | Dono |
|---|---|
| Schema completo (tabelas, campos) | [`../data-model/marketplace.md`](../data-model/marketplace.md) |
| Narrativa de arquitetura e decisões de cobrança | [`../architecture/decisoes-marketplace.md`](../architecture/decisoes-marketplace.md) |
| Estado e fases | [`../architecture/estado-e-proximas-fases.md`](../architecture/estado-e-proximas-fases.md) |
| Mecânica da assinatura SaaS implementada | [`../ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md`](../ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md) |
| Intenção de produto, ordem das frentes, gates | [`prd.md`](prd.md) |
| Registros de decisão | [`../adr/`](../adr/) |
