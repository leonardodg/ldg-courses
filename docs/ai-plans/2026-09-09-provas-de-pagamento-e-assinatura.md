# Provas de pagamento e assinatura — sessão de 08–09/09/2026

Registro do que foi feito, do que foi medido e do que sobrou. Escrito para quem
abrir a próxima sessão — provavelmente a do `paygw_pagarme`.

Quinze PRs, do #77 ao #91, todos merjeados em `dev` com CI verde.

## O que estava aberto quando a sessão começou

O split do Mercado Pago **nunca tinha sido visto transferir nada**. Numa
tentativa anterior vendedor e marketplace eram a mesma conta: o
`marketplace_fee` foi aceito, o pagamento aprovou, e nenhum centavo mudou de
dono — sem erro nenhum, que é o pior desfecho possível.

## O que foi provado, com número

| Prova | Evidência |
|---|---|
| Split no Mercado Pago | pagamento `178004552586`: R$ 5,00 − R$ 0,05 (taxa) − R$ 1,25 (`application_fee`) = R$ 3,70, e R$ 1,25 no extrato da plataforma |
| Ordem de dedução | a taxa do gateway sai primeiro; a comissão sai do que sobra |
| Vendedor pessoa física | a conta que vendeu é PF — o CNPJ é exigido da **plataforma**, não de quem vende ([ADR-0010](../adr/0010-vendedor-pessoa-fisica-no-mercado-pago.md)) |
| Compra pela vitrine | `177042328687`, webhook chegando sozinho, `feesource = company`, direito de 30 dias e matrícula |
| Ciclo da assinatura | renovação **soma** (11/09 → 11/10), `cycles = 2`, um único direito, `application_fee` em cada ciclo |
| Corte e volta | corte suspende **por diferença**; pagar a atrasada devolve o acesso |
| Assinatura no Asaas | split em cada cobrança do ciclo, cancelamento, estorno e reenvio |

## Os achados que mudaram decisão

**O `preapproval` do Mercado Pago engole a comissão em silêncio.** O ADR-0001
dizia que ele *não aceita* `marketplace_fee`. Ele aceita: devolve `201` e
descarta o campo. As duas requisições, com e sem, são indistinguíveis. Isso é
pior que recusar — recusa segura o engano na porta; silêncio produz assinatura
cobrando todo mês com comissão zero.

**Estornar um ciclo de assinatura não para a assinatura.** A cobrança vira
`REFUNDED`, o split é cancelado, e as futuras seguem pendentes. Sem regra, o
gerente devolveria um mês e o aluno continuaria sendo cobrado. Daí: estorno só no
primeiro ciclo, e sempre com o cancelamento na mesma operação.

**Estorno parcial não reduz a comissão.** Pedir R$ 40 de R$ 100 devolveu sucesso,
deixou o status `CONFIRMED` e manteve o split cheio. Só existe estorno total.

**Boleto não tem estorno**, em circunstância nenhuma — nem depois de baixa
manual. O Asaas recusa pela forma de pagamento.

**O Asaas gera várias cobranças de uma vez**, e a lista volta da mais distante
para a mais próxima. Isso virou bug real: o aluno era mandado para a fatura do
mês seguinte.

**Quem guarda o cartão é o gateway.** A assinatura nasce sem cartão; o aluno paga
a primeira fatura, e o Asaas passa a guardar aquele cartão — as cobranças
seguintes já nascem com ele. O Moodle não guarda nada, e não deve.

**Não existe Pix automático nesta API**, e `DEBIT_CARD`/`TRANSFER` são recusados
para assinatura. Só cartão debita sozinho, só cartão pode ser recusado, e só
cartão tem validade para expirar — é o mesmo fato visto de três ângulos.

## Bugs achados por exercitar, não por ler

Nenhum destes apareceria em teste unitário.

1. **A página de assinaturas quebrava para todo aluno.** `get_records(…, 'timeend
   DESC')` produzia `ORDER BY timeend DESC ASC`. A consulta vem antes da checagem
   de lista vazia, então atingia até quem nunca comprou (#78).
2. **Pagar a mensalidade atrasada criava um segundo direito** em vez de reviver o
   primeiro. `cycles` voltava a 1, e com ele o `maxcycles` nunca terminaria (#82).
3. **A matrícula não tinha prazo.** O acesso dependia inteiramente do cron; com
   ele parado, o aluno entrava indefinidamente (#80).
4. **Cancelar não alcançava gateway desabilitado.** Desligar o Asaas deixaria
   toda assinatura dele cobrando para sempre (#83).
5. **O botão de estorno aparecia em venda por boleto** e o clique morria com erro
   cru da API (#89).

## Meus erros, e o que os causou

Vale mais que a lista de acertos, porque o padrão se repete.

**Chamei de "débito automático" sem medir** se o cartão ficava guardado (#81).
Depois medi *"assinatura criada com cartão via API"* — que **não é o nosso
fluxo** — e concluí que não havia débito automático (#86). O certo só apareceu
ao medir o caminho real, o aluno pagando a fatura (#87). Duas correções no mesmo
dia, e a causa foi a mesma: medir algo *próximo* do fluxo real.

**Empurrei dois commits para um PR já merjeado**, e eles ficaram órfãos (#79 os
recuperou). É a quinta vez que isso acontece no projeto. A regra: confirme que o
PR ainda está aberto antes de empurrar.

**Escrevi uma asserção de behat por palavra solta** (`should not see "error"`), e
ela deu falso positivo em marcação legítima da página.

## O contrato que um gateway precisa cumprir

O núcleo não sabe o nome de gateway nenhum: pergunta a cada um por
`component_class_callback`. Um gateway novo implementa o que souber, e o que não
implementar cai no padrão.

| Método em `\paygw_<nome>\gateway` | Para quê | Padrão se ausente |
|---|---|---|
| `get_supported_currencies()` | montar a lista de meios por país | `[]` |
| `get_supported_countries()` | idem | `[]` |
| `cancel_recurring($component, $itemid, $userid)` | parar de cobrar | `false` |
| `refund($paymentid)` | estornar | `false` |
| `refund_blocker($paymentid)` | esconder o botão quando não dá | `errorrefundunknown` |
| `pending_invoice($component, $itemid, $userid)` | fatura em aberto do ciclo | `null` |

E do lado do marketplace, o que o gateway consome:
`api::commission_terms_for()`, `api::recurrence_for()`, `api::record_sale()`.

## Para a sessão do Pagar.me

O plano original está em
[`2026-08-27-plano-original-asaas-e-pagarme.md`](2026-08-27-plano-original-asaas-e-pagarme.md).
O que esta sessão acrescenta é **o que medir antes de escrever código**.

### Meça primeiro, e nesta ordem

1. **O split chega na cobrança?** Não basta a API aceitar o campo — confira que
   ele aparece no `GET` da cobrança, com valor. Foi o que separou o Asaas do
   Mercado Pago.
2. **Qual é a base do percentual?** No Asaas, `percentualValue` incide sobre o
   **líquido**; comissão sobre o bruto exige valor fixo, que congela.
3. **Há assinatura com split?** E o split vale em **cada ciclo**, ou só na
   primeira cobrança?
4. **O estorno reverte o split?** E funciona para quais formas de pagamento?
5. **Estornar um ciclo cancela a assinatura?** Se não, o cancelamento tem que
   andar junto no nosso código.
6. **Quantas cobranças a assinatura gera de uma vez**, e em que ordem a lista
   volta?
7. **Onde o instrumento fica guardado**, e o que acontece quando ele falha.

### Desconfie de sucesso sem erro

Foi o fio condutor de toda a sessão. Duas APIs aceitaram um campo e o
descartaram calado: o `marketplace_fee` no `preapproval` do Mercado Pago, e o
`creditCard` no `PUT /subscriptions` comum do Asaas. Nos dois casos a resposta
foi `2xx`.

**Depois de mandar, leia de volta.** E quando o dinheiro estiver envolvido, leia
o extrato — `fee_details` diz o que foi cobrado; o extrato diz o que chegou.

### O que ainda não tem prova

- Vendedor **pessoa jurídica** no Mercado Pago — o caso convencional, e o único
  que sobrou dessa frente.
- **Retentativa de cartão guardado**: quantas vezes o Asaas tenta quando o cartão
  vence no meio da assinatura, e que status intermediários produz. Exige esperar
  um ciclo real.
- **Pix e boleto liquidando de verdade** — o sandbox do Asaas não liquida
  nenhum dos dois; tudo foi provado com cartão fictício.
- **Débito automático de ponta a ponta**: ninguém viu o Asaas cobrar o segundo
  mês sozinho. As cobranças foram pagas uma a uma, o que exercita o mesmo código
  mas não o agendador dele.

### Ferramentas que já existem e servem

- `docs/data-validation/mercadopago-split.md` — roteiro do split, três contas
- `docs/data-validation/asaas-assinatura.md` — roteiro do ciclo, sem esperar mês
- `docs/data-validation/painel-de-testes.md` — todas as telas por papel
- `docs/data-validation/scripts/provar-split-mercadopago.py` — aborta quando
  `collector_id` é o dono da aplicação, que é a guarda que dá sentido ao número
