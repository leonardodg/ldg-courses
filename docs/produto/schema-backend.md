# Schema backend — visão de produto

> A **fonte da verdade** para tabelas e campos continua sendo
> [`../data-model/marketplace.md`](../data-model/marketplace.md) — não há lista
> completa de colunas aqui, nem um segundo dicionário de dados. Este documento
> responde outra pergunta: **que conceitos de produto o modelo precisa
> expressar**, e onde cada um vive hoje. Nomes de tabela conferidos no
> data-model; lacunas marcadas como **proposto (não implementado)**.

## Conceitos de produto → estruturas existentes

| Conceito de produto | Estrutura no modelo | O que isso sustenta |
|---|---|---|
| Empresa é uma categoria de cursos | `local_marketplace_company` (`categoryid`) | Isolamento, papel de vendedor, tema e contexto da conta de pagamento no mesmo lugar |
| Domínio próprio do vendedor | `local_marketplace_company` (`hostname`) | Mapa Host→empresa lido na configuração; sem tabela paralela |
| Conta de pagamento **por país** | `local_marketplace_account` (empresa × país × conta do core) | O `core_payment` escopa conta por contexto; sem a chave por país, a empresa receberia "na primeira que aparecesse". Ver [ADR-0002](../adr/0002-conta-de-pagamento-por-pais.md) |
| Oferta com país em ISO | `local_marketplace_offer` (`country`, moeda derivada) | `get_payable()` resolve valor, moeda e conta só pelo `itemid` — o país vive na oferta, não no comprador |
| Direito de acesso (fonte única do aluno) | `local_marketplace_entitlement` | Matrícula e liberação de seção leem o direito; **ninguém** lê a venda para decidir acesso |
| Venda neutra de gateway | `local_marketplace_sale` | Uma linha por pagamento, com os termos de comissão **fotografados**. Ver [ADR-0007](../adr/0007-comissao-sobre-o-bruto.md) |
| Planos comerciais (SaaS B2B) | `local_marketplace_plan` | Comissão, mensalidade, `hostingmodel` e país do plano; seed idempotente por `shortname` |
| Degraus de resolução do plano | `local_marketplace_plan_tier` | Teto de resolução por faixa; **degraus comerciais a confirmar** (ver [`prd.md`](prd.md)) |

Detalhe campo a campo: [`../data-model/marketplace.md`](../data-model/marketplace.md).

## Duas cobranças, duas paymentareas

O mesmo motor de ciclo serve a venda de curso e a assinatura SaaS da empresa —
separadas pela paymentarea do `service_provider`, nunca por tabela nova de
acesso:

| Paymentarea | `itemid` | Recebedor | Split |
|---|---|---|---|
| `'offer'` | `offerid` | Conta da empresa no país da oferta (`local_marketplace_account`) | Sim — comissão fotografada em `local_marketplace_sale` e nas tabelas dos gateways |
| `'plan'` | `companyid` | Conta da **plataforma**: `core_payment\account` no contexto do site, **sem** linha em `local_marketplace_account` | **Não existe.** A plataforma fica com 100%; `record_sale()` não grava venda para esta área |

A ausência da linha empresa↔conta **é** o que identifica a conta da plataforma
— o vínculo vive em tabela própria, então basta não criá-lo. O acesso de plano
também não entra em `local_marketplace_entitlement` (tabela de venda de curso):
fica em `company.planid` + estado da assinatura. Ver
[TRD — Billing](trd.md) e
[`../ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md`](../ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md).

## Gaps — o que o produto ainda precisa modelar

**proposto (não implementado)** — nenhum destes itens existe no modelo de
hoje; as linhas abaixo são intenção de produto, não schema em produção.

| Gap | Situação verificada | Por quê importa ao produto |
|---|---|---|
| Armazenamento da chave de API BYOS por empresa | `local_marketplace_plan.hostingmodel` (`native` \| `byos`) é **rótulo**: não há coluna nem tabela onde gravar a chave, nem código que troque destino de upload | Sem peça técnica, vender o degrau BYOS é vender o que não se entrega (ver [`trd.md`](trd.md), Frente A) |
| Mapeamento empresa ↔ `library` do Bunny | **Não modelado** — nenhuma tabela no data-model liga empresa a biblioteca de vídeo | Pré-condição da frente B e da trava: sem origem identificada por empresa, não há o que travar nem de onde medir custo |
| Player consumindo o teto de resolução | **Dados existem** (`local_marketplace_plan_tier`, `plan::max_resolution_for()`), **consumidor pendente**: nada no `core_media_manager` lê o teto ainda | Enquanto o player não consumir, a trava é promessa no banco — ver [ADR-0005](../adr/0005-trava-de-resolucao-por-ticket.md) e [`trd.md`](trd.md) |

## Fora da v1 (não-objetivos de schema)

Não é "depois sim" — é **não agora**, e nenhum campo ou tabela destes entra
nesta versão:

- **Campos Cloudflare** — provedor escolhido é Bunny.
- **Tabelas de cota de banda por empresa** — controle fino fica depois da
  trava de resolução.
- **`moodledata` (Fase 5)** — bloqueio de decisão de negócio de cobrança, não
  de técnica.

Mesma lista de não-objetivos em [`prd.md`](prd.md); narrativa de arquitetura em
[`../architecture/decisoes-marketplace.md`](../architecture/decisoes-marketplace.md).
