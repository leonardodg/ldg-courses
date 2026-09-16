# Os três gateways, comparados por medição

Comparativo do que **foi exercitado**, não do que a documentação promete. Cada
número aqui tem uma cobrança com id por trás, e as fontes estão em
[`../data-validation/`](../data-validation/README.md).

O documento que escolheu o Pagar.me e o Asaas —
[`levantamente.txt`](levantamente.txt) — foi escrito a partir de material
comercial, e **três afirmações dele não sobreviveram à medição**. Este arquivo é
o que ficou no lugar.

> **Conclusão, para quem só lê o começo:** o **Asaas é o gateway do produto**,
> porque é o único com split em assinatura — e assinatura é o coração do modelo.
> Os outros dois servem para venda avulsa. Isso custa caro em taxa de Pix, e a
> seção de taxas mostra quanto.

---

## O que cada um consegue fazer

| Capacidade | Asaas | Mercado Pago | Pagar.me |
|---|---|---|---|
| Split em venda avulsa | **provado** | **provado** | **provado** |
| Split em **assinatura** | **provado, em cada ciclo** | **impossível** | recusado pela conta |
| Pix liquidando de verdade | **provado** | **provado** | **provado** |
| Boleto | sim, sem prova de liquidação | não usado | sim, sem prova de liquidação |
| Cartão | provado | provado | provado |
| Estorno total | provado | não implementado | provado |
| Estorno parcial | **não reduz o split** | não implementado | **não reduz o split** |
| Cancelar assinatura | provado | não há assinatura | não há assinatura |
| Fatura em aberto do ciclo | provado | não há assinatura | não há assinatura |
| Troca de cartão | provado | não há assinatura | não há assinatura |

**Métodos do contrato implementados** (`\paygw_<nome>\gateway`):

| | `get_supported_currencies` | `cancel_recurring` | `refund` | `refund_blocker` | `pending_invoice` |
|---|---|---|---|---|---|
| Asaas | sim | sim | sim | sim | sim |
| Mercado Pago | sim | **sim** | **sim** | **sim** | **sim** |
| Pagar.me | sim | sim | sim | sim | sim |

Os quatro do Mercado Pago entraram em 16/09/2026, e dois deles significam coisa
**diferente** do que significam no Asaas:

- **`cancel_recurring` não cancela nada no gateway.** Lá existe um objeto de
  assinatura que cobra sozinho, e cancelar é pedir que pare; aqui quem dispara
  cada ciclo é a plataforma, então cancelar é **parar de disparar**. A marca
  vive na coluna `subscriptionstatus`.
- **`pending_invoice` devolve uma página NOSSA.** Não há fatura hospedada: o
  ciclo é cobrança automática no cartão guardado, e quando falha não sobra
  documento para alguém pagar. O que resolve é o aluno informar um cartão que
  funcione — que é o `subscribe.php`.

O Pagar.me implementa os cinco, mas `cancel_recurring` e `pending_invoice` nunca
disparam: o gateway recusa oferta recorrente na porta.

---

## As taxas, medidas em cobrança real

Nenhum destes números veio de tabela comercial.

### Pix

| Gateway | Bruto | Taxa | % efetivo | Evidência |
|---|---|---|---|---|
| **Mercado Pago** | R$ 5,00 | R$ 0,05 | **1,0%** | pagamento `178004552586` |
| **Pagar.me** | R$ 5,00 | R$ 0,05 | **1,0%** | `ch_4WDo30rCJ4SlY1vN` |
| **Asaas** | R$ 5,00 | **R$ 1,99** | **39,8%** | `pay_o45qdajghsqi5uwj` |

**A taxa de Pix do Asaas é FIXA**, e é o número que mais pesa neste documento.
R$ 1,99 numa venda de R$ 5,00 come 40%; na mesma venda o Mercado Pago e o
Pagar.me cobram R$ 0,05. A conta só se inverte em ticket alto:

| Ticket | Asaas (R$ 1,99 fixo) | MP / Pagar.me (1%) | Quem sai mais barato |
|---|---|---|---|
| R$ 5,00 | 39,8% | 1,0% | MP / Pagar.me |
| R$ 50,00 | 4,0% | 1,0% | MP / Pagar.me |
| R$ 199,00 | 1,0% | 1,0% | **empatam** |
| R$ 500,00 | 0,4% | 1,0% | **Asaas** |

O ponto de equilíbrio é **R$ 199,00**. Abaixo disso o Asaas é mais caro em Pix;
acima, mais barato.

### Cartão

| Gateway | Bruto | Taxa | % efetivo | Evidência |
|---|---|---|---|---|
| **Asaas** | R$ 100,00 | R$ 2,48 | **2,48%** | homologação, `netValue` 97,52 |
| **Pagar.me** | R$ 100,00 | R$ 4,49 | **4,49%** | `ch_KME2JgJuJnT1XlX7` |
| Mercado Pago | — | — | — | não medido em cartão |

### A taxa que não aparece na cobrança

O Asaas cobra **R$ 0,99 de "taxa de mensageria"** por avisar o comprador — e ela
**não entra no `netValue`**. Numa venda de R$ 5,00 o `netValue` dizia R$ 3,01 e o
vendedor ficou com R$ 0,77:

```
+5,00  PAYMENT_RECEIVED
-1,99  PAYMENT_FEE (Pix)
-1,25  INTERNAL_TRANSFER_DEBIT (a comissao)
-0,99  PAYMENT_MESSAGING_NOTIFICATION_FEE   <- fora do netValue
```

Quem calcular o líquido do vendedor pelo `netValue` **erra para mais, toda
vez**. Desligada no PR #101, com `notificationDisabled` na criação do cliente.

---

## Onde a comissão incide, e por que isso muda o código

| Gateway | Base do percentual | Consequência |
|---|---|---|
| **Asaas** | **líquido** | comissão sobre o bruto exige valor fixo, que **congela** entre ciclos |
| **Pagar.me** | **bruto** | o percentual já faz o que queremos; base `net` é inatendível |
| Mercado Pago | absoluto (`marketplace_fee`) | sempre `gross`; pedir `net` grava a divergência |

São opostos — e por isso `commission::with_base()` existe: o gateway grava a base
que **aplicou**, não a que foi pedida ([ADR-0007](../adr/0007-comissao-sobre-o-bruto.md)).

---

## Onde se lê a comissão de verdade

| Gateway | Fonte | Armadilha |
|---|---|---|
| Asaas | `payment.split[]` no `GET` | baixa manual (`receiveInCash`) **cancela** o split |
| Mercado Pago | `fee_details` | o `preapproval` aceita `marketplace_fee` e **descarta calado** |
| **Pagar.me** | **`GET /payables?recipient_id=`** | `charge.splits` volta **`null` mesmo funcionando** |

O caso do Pagar.me é o mais perigoso dos três, e virou
[ADR-0011](../adr/0011-o-extrato-e-a-fonte-da-comissao.md): os outros fazem
acreditar que deu certo quando não deu — prejuízo de uma comissão. Esse faz
acreditar que deu **errado quando deu certo**, e a reação natural (desligar o
split) custaria a comissão de **todas** as vendas.

---

## Limitações, uma a uma

### Asaas

- **Taxa de Pix fixa de R$ 1,99** — proibitiva em ticket baixo.
- **Mensageria de R$ 0,99 fora do `netValue`** (desligada).
- **Percentual sobre o líquido**, o que congela a comissão sobre o bruto.
- **Sandbox não liquida Pix nem boleto**; a baixa manual cancela o split.
- **Estorno parcial não reduz o split**, e boleto não estorna nunca.
- **Débito automático nunca foi visto** — as cobranças do ciclo foram pagas uma a
  uma.

### Mercado Pago

- **Sem recorrência com split.** O `preapproval` aceita `marketplace_fee` e o
  descarta. É impossibilidade da API, não configuração
  ([ADR-0001](../adr/0001-gateways-alem-do-mercado-pago.md)).
- **Nenhum método do contrato além de moedas** — sem estorno, sem cancelamento.
- **Split só entre contas do mesmo país**, e as três partes precisam ser do mesmo
  ambiente.
- Tokens ainda **em texto puro** no `payment_gateways.config`.

### Pagar.me

- **Sem split em assinatura.** Recusa em quatro formatos; dentro de `items[]`
  aceita e descarta. O gateway **recusa oferta recorrente na porta**.
- **`?subscription_id=` é ignorado** — não há como listar cobranças de uma
  assinatura.
- **Pix bloqueado em homologação** (funciona em produção).
- **`charge.splits` sempre nulo**; a comissão sai do extrato.
- **O payable demora ~16 s** (já passou de 100 s) — a venda entra com comissão
  zero e a reconciliação corrige.
- **Taxa de cartão de 4,49%**, quase o dobro do Asaas.
- **Pix pendente não cancela**, e o status não vira "expirado".
- `GET /transfers` exige **allowlist de IP**.

---

## O que o levantamento original errou

| Afirmação de [`levantamente.txt`](levantamente.txt) | O que a medição mostrou |
|---|---|
| Host de homologação `sdx-api.pagar.me` | **não existe** — `404`; o ambiente vem do prefixo da chave |
| Recebedor com prefixo `re_`… | certo, e a **documentação** é que diz `rp_` |
| `type: corporation` no recebedor | recusado; aceita `individual` ou `company` |
| Pagar.me tem split em assinatura "só por porcentagem" | **não tem**, em nenhum formato |

E o [ADR-0001](../adr/0001-gateways-alem-do-mercado-pago.md) precisou de correção
própria: ele dizia que o `preapproval` do Mercado Pago **não aceita**
`marketplace_fee`. Ele aceita — devolve `201` e descarta. Pior que recusar.

---

## O que continua sem prova

- **Débito automático de ponta a ponta** em qualquer gateway.
- **Boleto liquidando**, em qualquer um.
- **Retentativa de cartão vencido** no meio da assinatura.
- **Vendedor pessoa jurídica** no Mercado Pago.
- **Split em assinatura no Pagar.me**, se a conta for habilitada.
