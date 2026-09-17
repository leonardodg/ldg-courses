> **Situação:** pendente · **Início:** 2026-09-17

# Assinatura SaaS: planos Start e PRO

## Contexto

A plataforma vende duas coisas diferentes, e só uma delas cobra de verdade hoje:

- **Venda de curso com split** — o aluno paga a empresa parceira, a plataforma
  fica com uma comissão. Está construída: `paygw_mercadopago` (assinatura por
  ciclo, cartão/Pix/boleto) e `paygw_asaas` (recorrência nativa do gateway).
- **Venda de SaaS** — a empresa parceira paga a PLATAFORMA pela hospedagem e
  pelo uso do produto. **Nunca foi cobrada.** `local_marketplace_plan::monthlyfee`
  existe desde 04/09/2026, e o próprio docblock da classe já avisa: *"o plano
  diz quanto a empresa paga por mês... ele NÃO cobra nada: a mensalidade é
  informativa até existir a paymentarea 'plan' no service_provider."*

Este documento fecha o desenho comercial (quantos planos, o que cada um cobra)
e o esqueleto de arquitetura (onde a cobrança entra no código) **antes** de
escrever qualquer linha — nenhum código deste plano existe ainda.

Relacionado: `docs/history/desenhar_fluxo_cadastrar_empresa.txt` desenha o
wizard de ativação de empresa (`block_marketplace`), cuja etapa 5 ("Plano
escolhido") hoje presume que escolher plano é só gravar um `planid`, sem
cobrança nenhuma. Este documento é a peça que faltava para aquela etapa cobrar
de verdade.

## Decisões

| Decisão | Consequência |
|---|---|
| **Dois planos, não três** | Substitui a tabela Free/Starter/PRO (arquivada em `docs/private/planos.md`) e os planos `Starter`/`Pro`/`Scale` já semeados em `local_marketplace_plan` — esses vão precisar de migração/reconciliação quando a implementação começar |
| **Start**: hospedagem nativa, comissão fixa **10%** sobre toda venda, três tiers de mensalidade (**R$0 / R$50 / R$100**) que só destravam qualidade de vídeo | A comissão não muda com o tier — só a resolução liberada muda |
| **PRO**: hospedagem BYOS (fora da plataforma), comissão **5%**, mensalidade própria (valor ainda **em aberto**, ver abaixo) | Comissão menor porque a banda de vídeo não é custo nosso — a lógica é a mesma do PDF original de aquisição |
| **Comissão e mensalidade são CONFIGURÁVEIS, nunca fixas em código** | Já são campos de banco (`commissionpct`, `monthlyfee` em `local_marketplace_plan`) — o "5%" do PRO é o valor-alvo de hoje, não uma constante a codificar. A implementação lê o plano, nunca hardcoda percentual |
| **Cobrança automática recorrente vale para QUALQUER tier com `monthlyfee > 0`** | Não é caso especial do "plano mais top": Start-R$50, Start-R$100 e PRO usam o **mesmo motor de ciclo**, só muda o valor |
| **Start-R$0 é promocional/temporário** | O código não pode presumir que sempre existe um plano com mensalidade zero — ele pode ser desligado ou fechado para novos cadastros no futuro |
| **Troca de tier só vale no PRÓXIMO ciclo, sem proração** | Alternativa recusada: cobrança/crédito proporcional na hora — mais justo, mais caro de implementar e testar; fica para quando houver demanda real |
| **Trava de qualidade migra de "por ticket do curso" para "por tier de assinatura da empresa", mas continua a MESMA tabela** | `plan_tier`/`plan::max_resolution_for()` já existem e já são configuráveis (era o gatilho de `ADR-0005`, nunca ligado em produção) — não é tabela nova, é o **gatilho** que troca de "preço do curso" para "tier pago pela empresa". `ADR-0005` fica **superada** quando isto entrar; a matemática de banda/margem dela continua valendo |
| **Inadimplência só muda o STATUS da assinatura — a consequência é responsabilidade de CADA plugin consumidor** | O `paygw_*`/motor de ciclo do plano não sabe (nem deve saber) o que significa "perder resolução" ou "não poder criar curso novo". Ele só grava que a assinatura ficou `overdue`/`suspended`. Quem aplica a trava é `mod_ldgvideo` (Start: cai pra resolução do tier abaixo) e a capability de criação de curso (PRO: bloqueia curso novo) — mesmo padrão do `ADR-0009` (restrição do plano Free por capability, não por código de pagamento) |
| **A conta que recebe o SaaS é uma `core_payment\account` comum, sem empresa** | O vínculo empresa↔conta vive em `local_marketplace_company_account`, tabela separada — não é propriedade da conta. Basta uma conta no contexto do site, sem linha nessa tabela, vinculada aos mesmos gateways/credenciais que a plataforma já usa para receber comissão. Nenhum mecanismo novo |
| **Se o Start-R$0 deixar de existir, o cadastro de empresa nova passa a exigir plano pago** (decisão provisória — "provavelmente") | Não há mais um caminho gratuito para começar a vender; o wizard de ativação (`docs/history/...`) precisaria bloquear a etapa "plano" sem uma mensalidade paga. **Não decidido:** o que acontece com quem já estava no R$0 no dia do fechamento |
| **Aprovação de empresa continua MANUAL por enquanto; automática é objetivo futuro** | Confirma o que o wizard já previa (`approvalmode = manual\|auto`, padrão manual) — este documento não muda essa decisão, só o gatilho de cobrança que a alimenta |
| **Reconciliação dos planos antigos: ARQUIVAR os três (`Starter`/`Pro`/`Scale`), não reaproveitar em código** | Nenhum dos três mapeia 1:1 pro desenho novo (comissões diferentes, e "Scale" com 0% não tem equivalente). `plan.status = archived` já existe pra isto — os três somem da vitrine mas continuam no banco por histórico. Nascem QUATRO planos novos: `start_free` (R$0), `start_50`, `start_100` (todos native, 10%) e `pro` (byos, 5%, mensalidade em aberto) |
| **Cada tier do Start é um `plan` PRÓPRIO, não uma faixa dentro de um `plan` só** | `plan.monthlyfee` é campo do PLANO, não do tier — então R$0/R$50/R$100 só cabem como três `shortname` diferentes. A resolução de cada um vira **um único `plan_tier` por plano**, com `maxprice = null` (a "faixa sem teto" que `max_resolution_for()` já resolve): não precisa mudar a assinatura do método nem a tabela, só o CONTEÚDO da linha |

## Arquitetura proposta

Levantado no código atual (`local_marketplace`, `paygw_mercadopago`) antes de
desenhar, para não propor reuso que não serve:

- **Nova `paymentarea = 'plan'`** em `local_marketplace\payment\service_provider`,
  ao lado da `'offer'` já existente — é literalmente o gancho que o docblock de
  `plan.php` já previa.
- **`get_payable()` desta área resolve o recebedor como uma conta da PRÓPRIA
  PLATAFORMA**, não da empresa. Isso é mais simples do que parecia: a comissão
  das vendas HOJE não passa por conta nenhuma do Moodle — é automática, no
  próprio gateway (`application_fee` do Mercado Pago, `walletId` do split no
  Asaas), dinheiro que a API entrega direto pra fora sem o `core_payment`
  saber. Mas o vínculo empresa↔conta vive numa tabela SEPARADA
  (`local_marketplace_company_account`), e não é propriedade da conta em si —
  então basta criar uma `core_payment\account` comum (contexto do site, sem
  linha em `company_account`), vinculada aos MESMOS gateways/credenciais que a
  plataforma já usa. Nenhum mecanismo novo — só uma conta sem empresa.
- **`entitlement` e `sale` não servem para isto.** Os dois têm `offerid` NOT
  NULL, e `sale` carrega `feepercent`/`feebase`/`feesource` para representar um
  **split** entre empresa e plataforma — aqui não há split, a plataforma fica
  com 100%. É preciso uma tabela nova: um ledger de cobrança do plano, no
  mesmo formato de linha-por-ciclo que `paygw_mercadopago`/`paygw_asaas` já
  usam para assinatura de curso.
- **O motor de ciclo é reaproveitado, não reinventado**: `card_capture`,
  `payment_methods`, a confirmação de CVV no ciclo 2+ do Mercado Pago, a
  recorrência nativa do Asaas — tudo isso já existe e não sabe (nem precisa
  saber) se está cobrando uma oferta de curso ou um plano de empresa. Só muda
  a `paymentarea` e quem é o recebedor.
- **Acesso não ganha tabela nova.** `company.planid` mais um campo de estado da
  assinatura (nome a definir — `planstatus`/`planexpiry`) decidem a qualidade
  de vídeo liberada e, possivelmente, a ativação da empresa. Mesma filosofia
  do desenho do `block_marketplace`: estado **derivado** de campos existentes,
  sem tabela de "acesso" paralela à de aluno.

## Em aberto

**Fechado em 17/09/2026, segunda rodada:**

- ~~Valor da mensalidade do PRO~~ → é configurável por desenho (não é número
  fixo a decidir agora); o alvo comercial de hoje é 5% de comissão, mensalidade
  em aberto mas isso não bloqueia a arquitetura.
- ~~Mapeamento tier → resolução~~ → reaproveita `plan_tier`/`max_resolution_for()`
  como já existem, só trocando o gatilho (ticket do curso → tier pago da
  empresa); é configuração de banco, não mapeamento a fixar em código.
- ~~Inadimplência~~ → o motor de cobrança do plano só muda o **status da
  assinatura**; quem decide o que isso bloqueia (resolução no Start, curso novo
  no PRO) é cada plugin consumidor, lendo o status — implementação separada,
  fora do escopo deste desenho.
- ~~Identidade técnica da conta "plataforma"~~ → decidido, ver tabela de
  Decisões. Era o único item que bloqueava código, e não bloqueia mais.
- ~~Sunset do Start-R$0~~ → decidido (provisoriamente), ver tabela de
  Decisões: sem R$0, cadastro novo exige plano pago. Falta só o que fazer com
  quem já estava no R$0 — não urgente, porque hoje o tier continua aberto.
- ~~Aprovação manual vs automática~~ → confirmado: manual agora, automática é
  objetivo futuro. Não muda nada deste documento, só confirma o que o wizard
  já previa.
- ~~Reconciliação dos planos `Starter`/`Pro`/`Scale`~~ → decidido, ver tabela
  de Decisões: arquivar os três, nascer quatro novos (`start_free`,
  `start_50`, `start_100`, `pro`).

**O que sobra, e não bloqueia nada:**

- Valor exato da mensalidade do `pro` (só a comissão de 5% foi fechada).
- Mapeamento exato de resolução por tier (Start-R$50 → qual? Start-R$100 →
  qual? Start-R$0 → 720p ou só embed externo?).
- Confirmar que nenhuma empresa real hoje tem `planid` apontando para
  `Starter`/`Pro`/`Scale` antes de arquivá-los — se houver, o upgrade que
  arquiva precisa também migrar essas empresas para o plano novo equivalente.

## Próximos passos

1. Plano de implementação em passos pequenos (TDD, worktree própria), no
   mesmo padrão usado para o `paygw_mercadopago` — nenhum item de arquitetura
   ou de negócio segue bloqueando o início.
