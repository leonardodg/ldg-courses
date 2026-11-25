# Provar o ciclo da assinatura no Asaas

Roteiro da assinatura recorrente: criada, ciclo cobrado, acesso cortado por
falta de pagamento e devolvido ao pagar a atrasada — tudo sem esperar mês nenhum.

**Há débito automático quando o aluno paga com cartão, e quem guarda o cartão é o
gateway** — nunca o Moodle. A assinatura nasce sem cartão; o aluno paga a
primeira fatura na página do Asaas, e o Asaas passa a guardar aquele cartão. As
cobranças seguintes já nascem com ele anexado.

Com Pix ou boleto não há instrumento guardado: cada ciclo é uma fatura, e não há
cartão para expirar.

O irmão deste documento é [`asaas-sandbox.md`](asaas-sandbox.md), que prova o
split da cobrança avulsa. Aqui o que se prova é o **ciclo**.

## O que torna isto possível sem esperar

**O Asaas gera várias cobranças de uma vez.** Uma assinatura semanal nasce com
quatro ou cinco cobranças `PENDING`, com vencimentos espaçados. Dá para pagar uma
por vez, na ordem que quiser, e cada pagamento dispara o webhook de verdade.

Duas consequências, e as duas custaram correção:

**A lista volta da mais distante para a mais próxima.** Pegar a primeira do array
mandava o aluno pagar a fatura do mês seguinte, deixando a de hoje vencer atrás
dele. Por isso existe `payment_processor::earliest_charge()`.

**Não existe ciclo diário.** `DAILY` é recusado — o Asaas responde *"O parâmetro
cycle deve ser informado"*, tratando o valor como ausente. O mais curto é
`WEEKLY`. Como o marketplace conta acesso em dias, a tradução é lossy, e o
**empate vai para o ciclo maior**: cobrar mais cedo que o combinado tira do aluno
dinheiro que ele não contratou.

## Preparar

Sandbox não liquida Pix. O que processa na hora é **cartão fictício**, então a
oferta de teste usa `CREDIT_CARD`.

```bash
# Configuracao do site
moodev cli cfg.php --component=paygw_asaas --name=environment --set=sandbox
moodev cli cfg.php --component=paygw_asaas --name=billingtype --set=CREDIT_CARD
moodev cli cfg.php --component=paygw_asaas --name=usecallback --set=0
```

`usecallback=0` evita a checagem de domínio: o `returnUrl` exige que o endereço
da plataforma esteja cadastrado na conta do vendedor, e para esta prova o webhook
basta — ele não passa por essa validação.

O aluno precisa de **CPF num campo de perfil**, apontado em `documentfield`. Sem
ele o Asaas recusa a cobrança com *"é necessário preencher o CPF ou CNPJ"*, e a
falha só aparece no meio do checkout.

Webhook, pela API em vez do painel:

```bash
curl -s -X POST "$ASAAS_BASE_URL/webhooks" \
  -H "access_token: $ASAAS_SELLER_PJ_API_KEY" -H "Content-Type: application/json" \
  -d '{"name":"Moodle","url":"<site>/payment/gateway/asaas/webhook.php",
       "email":"...","enabled":true,"authToken":"'"$ASAAS_WEBHOOK_TOKEN"'",
       "sendType":"SEQUENTIALLY","events":["PAYMENT_RECEIVED","PAYMENT_CONFIRMED"]}'
```

A oferta: `accessmode = recurring`, `billingdays = 7`, `accessdays = 7`.

## O roteiro

1. **Assinar.** A compra cria a assinatura e manda o aluno para a cobrança que
   vence primeiro.
2. **Pagar o ciclo 1** com `POST /payments/{id}/payWithCreditCard`.
3. **Ver o aviso**, movendo o `timeend` do direito para dentro de cada marco e
   rodando `notify_expiring`.
4. **Não pagar:** `timeend` para o passado e `sync_entitlements` — o acesso cai.
5. **Pagar a atrasada** com o mesmo `payWithCreditCard` numa cobrança seguinte —
   o acesso volta sozinho, pelo webhook.

Cartão do sandbox: `5162306219378829`, `05/2029`, CCV `318`.

## Resultado — 09/09/2026

Assinatura `sub_3of118zoofc4al9x`, R$ 10,00 por ciclo, comissão 25% sobre o bruto
(`fixedValue: 2.50`).

| Etapa | O que aconteceu |
|---|---|
| Assinatura criada | 4 cobranças `PENDING`, e o plugin escolheu a de vencimento **mais próximo** |
| Ciclo 1 pago | webhook entregou sozinho: direito ativo, 4 matrículas, venda com `feesource company` |
| Avisos | 2 mensagens, uma por marco, com textos diferentes |
| Não pagou | `sync_entitlements` suspendeu **as 4** matrículas |
| Pagou a atrasada | ciclo adotado, acesso de volta, **1 direito** com `cycles = 3` |

Três linhas em `paygw_asaas`, uma por ciclo, `fee 2.50` em cada — e três vendas
em `local_marketplace_sale`. É isso que faz o relatório mostrar quanto entrou em
cada mês.

## O bug que este roteiro encontrou

**Pagar a atrasada criava um segundo direito em vez de reviver o primeiro.**

O `deliver_order()` procurava direito **ativo** para estender. Quem deixa vencer
tem o direito marcado como `expired` pelo cron — então nada era encontrado, e um
novo nascia ao lado.

O estrago não é visual: `cycles` voltava a `1`, e com ele o `maxcycles` de uma
assinatura de doze meses nunca terminaria; a tela do aluno listava a oferta duas
vezes; e todo `get_record` que espera um só quebrava com *"found more than one
record"*.

Corrigido procurando **ativo ou vencido**. `cancelled` fica de fora de propósito:
revogar é decisão de negócio, e um pagamento novo não pode desfazê-la em
silêncio — nesse caso nasce direito novo e a revogação continua no histórico.

O período novo conta **a partir de agora** quando o vencimento já passou: quem
ficou dois dias sem pagar não ganha os dois dias de volta.

## Os quatro tipos de cobrança, medidos

Todos aceitam assinatura, todos geram as cobranças com split. O que muda é se
existe **instrumento guardado** — e é dele que saem tanto o débito automático
quanto o problema do cartão vencido.

| Tipo | Cobra sozinho | Cartão para expirar |
|---|---|---|
| `PIX` | não — um Pix por ciclo | **não existe** |
| `BOLETO` | não — um boleto por ciclo | **não existe** |
| `CREDIT_CARD` | **sim, depois que o aluno paga a 1ª fatura** | **sim** |
| `UNDEFINED` | depende do que o aluno usar na 1ª | só se ele usar cartão |

O que decide não é como a assinatura foi criada, e sim **com que instrumento o
aluno pagou**. A assinatura nasce sem cartão; pagando com cartão, o Asaas guarda
e os ciclos seguintes saem sozinhos. Pagando com Pix ou boleto, não há nada
guardado e cada ciclo é uma fatura.

### Cartão vencido

Na **compra**, é recusado na hora: `invalid_creditCard`, *"O cartão informado
está expirado"*. A cobrança fica `PENDING`, a assinatura `ACTIVE`, e **nada é
guardado** — o aluno tenta de novo com outro cartão.

O caso que machuca é comprar com cartão que vence **no mês seguinte**. O Asaas
guarda, e o ciclo seguinte falha. O aluno cai no caminho manual que já existe:
recebe os avisos de vencimento, abre a fatura e paga com outro cartão — e esse
pagamento **atualiza o cartão guardado**, então o ciclo seguinte volta a ser
automático.

**Não medido:** o que a cobrança automática faz exatamente quando o cartão
guardado falha — quantas vezes o Asaas tenta e que status intermediários produz.
A política de retentativa é do painel do Asaas, e exige esperar um ciclo real
para observar.

### Pix e boleto não descontam sozinhos

Medido em 09/09/2026, e a resposta vale para os dois: **cada ciclo gera um
documento que alguém precisa pagar.** A assinatura Pix não guarda instrumento
nenhum (`creditCard` vazio), as cinco cobranças nascem `PENDING`, e cada uma tem
o seu QR.

Não existe Pix automático nesta API: `PIX_AUTOMATIC`, `AUTOMATIC_PIX` e
`PIX_RECURRING` são recusados como valor inválido. `DEBIT_CARD` e `TRANSFER` são
recusados com mensagem explícita — *"a forma de pagamento não é permitida para
assinaturas"*.

Consequência prática: **não há cenário de "Pix recusado" para testar.** Recusa é
coisa de cobrança automática, e só o cartão a tem. Um Pix simplesmente não é
pago — a cobrança vence, e o caso cai no fluxo de aviso e corte que este roteiro
já prova.

### Trocar o cartão de uma assinatura

Não é preciso cancelar e recriar. O endpoint é
**`PUT /subscriptions/{id}/creditCard`**.

O `PUT /subscriptions/{id}` comum, com `creditCard` no corpo, **aceita e ignora
em silêncio** — o cartão continua o antigo, sem erro nenhum. É o mesmo formato de
falha do `marketplace_fee` no `preapproval` do Mercado Pago, e vale desconfiar
dele por padrão nesta API.

Pelo endpoint certo, o `subscriptionid` não muda e o split sobrevive
(`percentual 25.0 | ACTIVE`) — então `cycles`, histórico e as linhas da nossa
tabela continuam válidos.

### Boleto e Pix, medidos

**O boleto do ciclo já nasce pronto.** As cinco cobranças têm `bankSlipUrl` e
linha digitável desde a criação, sem ninguém pedir. A ação que falta ao aluno é
**pagar**, e não gerar.

**Estorno depende da forma de pagamento**, e é o gateway que decide:

| Forma | Estorno |
|---|---|
| Cartão | sim, quando `CONFIRMED`/`RECEIVED` |
| Pix | sim, quando recebido |
| **Boleto** | **nunca** — nem depois de baixa manual |

O Asaas recusa boleto com *"somente é possível estornar cobranças cuja a forma de
pagamento seja cartão de crédito ou Pix"*, e continua recusando com o status
`RECEIVED_IN_CASH`. Devolver dinheiro de boleto é ato fora da plataforma; aqui o
que cabe é cancelar a assinatura.

Isso virou regra no `refund_blocker()`: sem ela o botão aparecia numa venda por
boleto e o clique morria com erro cru da API, diante de quem está resolvendo um
problema de dinheiro com um aluno.

## Armadilhas

**Direito de outra oferta segura o acesso.** Na primeira tentativa o corte
suspendeu zero matrículas — o aluno ainda tinha direitos de outras ofertas
cobrindo os mesmos cursos. É o sync trabalhando por diferença, e está certo; para
demonstrar o corte, isole a assinatura.

**Baixa manual não prova nada.** `receiveInCash` cancela o split e zera os
saldos. Ver `asaas-sandbox.md`.

**O webhook fica na conta do VENDEDOR**, não na da plataforma: é ele quem cria as
cobranças.
