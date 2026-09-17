> **Situação:** inacabado · **Início:** 2026-09-17 · **Última sessão:** 2026-09-17
>
> **Por que inacabado:** as Fases A–E foram todas implementadas e testadas
> (162 testes no `local_marketplace`, 127 no MP, 69 no Asaas, 119 no
> Pagar.me, 85 no `local_partners`, todos verdes) na worktree
> `saas-planos-start-pro`, branch `feature/saas-planos-start-pro` — mas **a
> prova com dinheiro real não aconteceu ainda**. Falta: vincular o Mercado
> Pago de produção na conta da plataforma e completar um pagamento real pela
> tela. Roteiro completo em
> `docs/data-validation/assinatura-saas-plano-empresa.md`.

# Assinatura SaaS: implementação dos planos Start e PRO

## Contexto

A plataforma cobra hoje só um lado da venda: o aluno paga a empresa parceira
por um curso, com comissão via split (`paygw_mercadopago`/`paygw_asaas`). O
outro lado — a empresa parceira pagando a PRÓPRIA PLATAFORMA pela hospedagem
e uso do produto — nunca foi cobrado. `local_marketplace_plan::monthlyfee`
existe desde 04/09/2026 e o próprio docblock da classe avisa: "a mensalidade
é informativa até existir a paymentarea 'plan' no service_provider".

O desenho comercial e de arquitetura já foi fechado em
`docs/ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md` (worktree
`paygw-mp-assinatura`): dois planos (**Start**, nativo, 10% de comissão, três
tiers de mensalidade R$0/R$50/R$100 que só destravam qualidade de vídeo; e
**PRO**, BYOS, 5% de comissão, mensalidade própria), cobrança automática
recorrente para qualquer tier com mensalidade > 0, e uma conta de pagamento
da própria plataforma (sem empresa dona) como recebedora.

Este plano é a implementação técnica dessa decisão. A exploração do código
atual (feita nesta sessão, com file:line verificados) confirmou que o motor
de ciclo do Mercado Pago já é genérico o bastante para ser reaproveitado
quase sem mudança — mas achou **três pontos que o desenho anterior não
cobria em detalhe**, e que mudam o escopo do trabalho:

1. `local_marketplace\api::recurrence_for()`, `commission_terms_for()` e
   `record_sale()` **não recebem `$paymentarea`** — todos tratam `$itemid`
   como um `offerid` sempre que `$component === 'local_marketplace'`. Sem
   ajuste, uma cobrança de plano com `itemid` = companyid seria interpretada
   como oferta inexistente (ou, pior, colidiria com uma oferta real de mesmo
   id) e cairia no fallback de comissão do SITE (25%) em vez de comissão
   zero — a plataforma "dividiria" consigo mesma sem necessidade.
2. `paygw_asaas\payment_processor::describe_item()` **hardcoda** a busca de
   uma `\local_marketplace\offer` pelo `itemid` — funcionaria mal para uma
   cobrança de plano (sem oferta nenhuma envolvida).
3. `paygw_asaas\link.php` e `paygw_pagarme\link.php` têm uma guarda própria
   (`errorsamewallet`/`errorsamerecipient`) que **recusa** vincular a
   carteira/recebedor da própria plataforma como "vendedor" — escrita para
   proteger a venda de curso (onde vendedor e plataforma têm que ser partes
   diferentes), mas que bloqueia exatamente o cenário que a conta da
   plataforma precisa fazer de propósito. **É guarda nossa, não do gateway**
   — confirmado lendo o código: compara o valor colado contra
   `credentials::platform_wallet($environment)`/`$platformrecipient` já
   configurados no `settings.php` do próprio plugin, e redireciona com um
   `moodle_exception` nosso. A API do Asaas/Pagar.me não rejeita nada aqui.

Decidido nesta sessão: **contornar a guarda nos três gateways**, pulando a
checagem quando a conta sendo vinculada for a conta identificada da
plataforma — não para todo mundo, só para essa conta especificamente
marcada. O pagamento do SaaS vai inteiro (100%) para essa única conta; não
há split, não há segundo destino.

## Abordagem

### Fase 0 — Pré-requisito (fora deste plano)

Esperar o PR #106 (`feature/paygw-mp-assinatura`) mergear na `dev`, e só
então `moodev new saas-planos-start-pro --from origin/dev`. O motor de ciclo
que este plano reaproveita só existe na `dev` depois desse merge.

### Fase A — Dados: planos e conta da plataforma

**`local_marketplace/db/upgrade.php`** (novo passo):
- Arquiva `Starter`/`Pro`/`Scale` (`plan::STATUS_ARCHIVED`) — antes,
  confirmar por query que nenhuma `company.planid` aponta pra eles; se
  apontar, migrar para o plano novo equivalente antes de arquivar.
- Semeia quatro `plan` novos: `start_free` (monthlyfee 0), `start_50`,
  `start_100` (todos `hostingmodel = native`, `commissionpct = 10`), e `pro`
  (`hostingmodel = byos`, `commissionpct = 5`, `monthlyfee` com um valor
  placeholder configurável depois pela tela de admin — não é decisão deste
  plano).
- Para cada plano `start_*`, um único `plan_tier` (`maxprice = null`,
  `maxresolution` = placeholder) — reaproveita `plan::max_resolution_for()`
  sem mudar a assinatura do método, só o conteúdo da linha (a "faixa sem
  teto" já cobre o caso de um resolução fixa por plano).

**`local_marketplace\classes\company.php`**: novo campo `planexpiry`
(`PARAM_INT`, nullable, default null) via `db/upgrade.php`. **Sem novo
enum de status** — inadimplência é derivada (`planexpiry !== null &&
planexpiry < time()`), do mesmo jeito que `entitlement::timeend` já decide
vencimento hoje. Consistente com a filosofia já usada no desenho do
`block_marketplace` (estado derivado, não tabela nova).

**`local_marketplace\classes\api.php`**, dois métodos novos:
- `get_or_create_platform_account(string $country): \core_payment\account` —
  mesmo padrão de `create_payment_account()` (linha 593), mas sem `company`:
  contexto `\context_system::instance()`, `idnumber = 'platform_' .
  strtolower($country)`, **sem** criar linha em `company_account`. Busca por
  idnumber antes de criar (idempotente).
- `is_platform_account(int $accountid): bool` — carrega a conta e confere se
  o `idnumber` começa com `'platform_'`. Simples porque só é alcançável por
  quem já tem `moodle/payment:manageaccounts` (telas de admin) — não é
  superfície atacável por empresa nenhuma.

### Fase B — `service_provider`: a paymentarea nova

**`local_marketplace\classes\payment\service_provider.php`**:
- Nova constante `PAYMENT_AREA_PLAN = 'plan'` (mantém `PAYMENT_AREA = 'offer'`
  como está — só 3 usos, todos em `tests/sale_test.php`, sem necessidade de
  renomear).
- `get_payable('plan', $itemid)`: `$itemid` é `companyid`. Resolve
  `$company = new company($itemid)`, `$plan = $company->get_plan()`; lança
  `moodle_exception` clara se não há plano ou `monthlyfee <= 0` (evita
  cobrar quem está no Start-R$0). Conta = `api::get_or_create_platform_account($plan->get('country'))`.
- `get_success_url('plan', $itemid)`: volta para uma página simples do
  próprio `local_marketplace` (não o wizard completo — isso é escopo do
  `block_marketplace`, fora deste plano).
- `deliver_order('plan', $itemid, $paymentid, $userid)`: **não cria
  `entitlement` nem `sale`** (os dois têm `offerid` `NOT NULL`, incompatível
  — confirmado no desenho anterior). Só atualiza `company.planexpiry`: soma
  30 dias ao atual se ainda no futuro, ou 30 dias a partir de agora se já
  vencido — mesma regra de `entitlement::extend()`.

### Fase C — As três funções genéricas do `api.php`

`recurrence_for()`, `commission_terms_for()` e `record_sale()` ganham um
último parâmetro `string $paymentarea = 'offer'` — **default preserva 100%
do comportamento atual em todo call site que não passar nada**.

- `recurrence_for(..., 'plan')`: `$itemid` é companyid; monta a partir de
  `company->get_plan()` — `days = 30`, `maxcycles = 0` (até cancelar, mesmo
  código que `offer::ACCESS_RECURRING` já usa para "sem limite"). Sem plano
  ou mensalidade zero → `null` (cai em cobrança avulsa, que nunca deveria
  ser chamada nesse caso — é defesa, não o caminho esperado).
- `commission_terms_for(..., 'plan')`: devolve comissão **0%** direto, sem
  consultar oferta nenhuma — a plataforma é a única parte, não há o que
  dividir.
- `record_sale(..., 'plan')`: devolve `null` imediatamente — nunca grava
  `local_marketplace_sale` (ela pressupõe split entre empresa e
  plataforma, que não existe aqui).

### Fase D — Gateways generalizados

**`paygw_mercadopago\classes\payment_processor.php`, `task/charge_due_cycles.php`,
`task/remind_upcoming_cycles.php`**: os call sites de `recurrence_for()`/
`commission_terms_for()`/`record_sale()` passam a repassar `$paymentarea`
(hoje não passam nada — confirmado, são só dois argumentos). Mudança
mecânica, mesmo comportamento pra `'offer'`.

**`paygw_asaas\classes\payment_processor.php::describe_item()`**: generaliza
o `if ($component === 'local_marketplace' ...)` para também reconhecer
`$paymentarea === 'plan'` e descrever pelo nome do plano
(`company::get_record($itemid)->get_plan()->get('name')`), mantendo o
fallback genérico atual pra qualquer outro caso.

**`paygw_asaas\link.php` e `paygw_pagarme\link.php`**: a checagem
`errorsamewallet`/`errorsamerecipient` passa a pular quando
`\local_marketplace\api::is_platform_account($accountid)` for verdadeiro.
Mercado Pago não precisa de nada aqui — confirmado, sem guarda equivalente
em `oauth_start.php`/`oauth_callback.php`.

### Fase E — Disparo da cobrança (mínimo testável)

Uma página simples nova em `local_marketplace` (ex.: `plan_checkout.php`)
que a empresa acessa logada: mostra o plano atual e um botão que chama o
fluxo padrão de checkout do `core_payment` com `paymentarea = 'plan'`,
`itemid = companyid`. **Não é o wizard completo** — isso é o próximo passo,
do `block_marketplace` (`docs/history/desenhar_fluxo_cadastrar_empresa.txt`),
fora do escopo deste plano.

Cancelamento reaproveita `api::stop_recurring_billing()`, já genérico por
gateway — sem mudança nenhuma.

### Fase F — Testes e verificação

- `tests/sale_test.php` (já cobre os três métodos do `api.php`) ganha os
  casos novos com `paymentarea = 'plan'`; confirma que chamadas sem o
  parâmetro continuam se comportando como `'offer'` (compatibilidade).
- Novo teste de `service_provider::get_payable/get_success_url/deliver_order`
  para `'plan'` — sem plano, com plano de mensalidade zero (deve recusar),
  com plano pago (deve resolver a conta da plataforma e atualizar
  `planexpiry`).
- `db_schema_test.php` do `local_marketplace` cobre o campo novo
  (`planexpiry`) e a tabela `plan`/`plan_tier` sem mudança de schema.
- phpunit dos três gateways continua verde (127 MP, suíte do Asaas, suíte
  do Pagar.me) — os call sites mudados têm teste de regressão pra confirmar
  que assinatura de CURSO continua idêntica.
- `describe_item()` do Asaas: teste novo pro caso `paymentarea = 'plan'`.
- Guarda de auto-vínculo: teste que confirma que a conta da plataforma
  PASSA e qualquer outra conta continua BLOQUEADA.
- phpcs limpo, `admin/cli/upgrade.php` + `check_database_schema.php` +
  `purge_caches.php` conferidos nos três plugins tocados.
- Prova com dinheiro real (sessão própria, fora deste plano): assinar
  Start-R$50 de verdade, conferir o valor caindo na conta da plataforma
  (não na de nenhuma empresa), e o ciclo 2 vencendo e cobrando de novo.

## Arquivos críticos

- `public/local/marketplace/classes/api.php` — `recurrence_for()` (~254),
  `commission_terms_for()` (~217), `record_sale()` (~332),
  `create_payment_account()` (~593, modelo para o método novo)
- `public/local/marketplace/classes/payment/service_provider.php` — os três
  métodos do contrato `core_payment\local\callback\service_provider`
- `public/local/marketplace/classes/company.php` — `planid` (71),
  `get_plan()` (439), novo `planexpiry`
- `public/local/marketplace/classes/plan.php` e `plan_tier.php` — sem
  mudança de código, só de dados (upgrade)
- `public/payment/gateway/mercadopago/classes/payment_processor.php` +
  `task/charge_due_cycles.php` + `task/remind_upcoming_cycles.php`
- `public/payment/gateway/asaas/classes/payment_processor.php::describe_item()`
  e `public/payment/gateway/asaas/link.php`
- `public/payment/gateway/pagarme/link.php`
- `public/local/marketplace/tests/sale_test.php`

## Fora do escopo deste plano

- O wizard completo de ativação de empresa (`block_marketplace`) — este
  plano entrega o mecanismo de cobrança, não a tela de onboarding.
- Valor exato da mensalidade do PRO e mapeamento exato de resolução por
  tier do Start — continuam configuráveis pela tela de admin, sem bloquear
  este plano.
- Aplicar a consequência de inadimplência (perder resolução, perder criação
  de curso) — cada plugin consumidor lê `company.planexpiry` por conta
  própria, implementação separada (decisão já registrada no desenho
  anterior).
