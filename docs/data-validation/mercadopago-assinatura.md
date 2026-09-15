# Assinatura no Mercado Pago: o que a aplicação de Assinaturas faz com a comissão

Roteiro repetível e registro da bateria de 15/09/2026. Irmão de
[`mercadopago-split.md`](mercadopago-split.md) e de
[`asaas-assinatura.md`](asaas-assinatura.md).

**Por que existe.** O [ADR-0001](../adr/0001-gateways-alem-do-mercado-pago.md)
afirma que `POST /preapproval` aceita `marketplace_fee` e **descarta em
silêncio**. A medição de 08/09/2026 é real, mas foi feita com a aplicação de
**Checkout Transparente** — e no Mercado Pago o modelo declarado da aplicação
muda o comportamento sem avisar. Em 15/09/2026 passou a existir uma aplicação do
tipo **Assinaturas**, e a pergunta voltou a ficar em aberto para o tipo certo.

Script: [`scripts/provar-assinatura-mercadopago.py`](scripts/provar-assinatura-mercadopago.py).

> **Duas assinaturas, e só uma precisa disto.** A mensalidade que a empresa paga
> à plataforma (B2B) **não tem split** — a LDG é a vendedora e fica com 100%.
> Quem precisa de split é a assinatura que a parceira vende ao aluno (B2C). Tudo
> neste arquivo é sobre a segunda.

---

## As contas, e como conferir cada uma

`GET /users/me` com cada token. Não se presume: em 08/09/2026 uma conta rotulada
"empresa" no cadastro era pessoa física pela API
([ADR-0010](../adr/0010-vendedor-pessoa-fisica-no-mercado-pago.md)).

```bash
python3 docs/data-validation/scripts/provar-assinatura-mercadopago.py contas
```

Resultado de 15/09/2026:

| Token | `user_id` | Site | Documento | Tipo |
|---|---|---|---|---|
| Preferências (`2401225442871147`) | `3675841384` | MLB | CNPJ | business |
| **Assinaturas** (`6990306155285574`) | `3675841384` | MLB | CNPJ | business |
| **Bricks** (`2598194068751669`) | `3675841384` | MLB | CNPJ | business |
| Bricks, credencial `TEST-` | `3675841384` | MLB | CNPJ | business |
| "Assinaturas, credenciais de teste" | **`3672982509`** | MLB | **CPF** | **pessoa física** |

**As três aplicações de produção pertencem à mesma conta CNPJ**, que é o arranjo
correto: a comissão volta para o dono da **aplicação**, não para quem criou a
cobrança.

> **A quinta linha é uma armadilha, e tem nome.** O que o painel entregou como
> "credenciais de teste" da aplicação de Assinaturas **não são credenciais de
> teste daquela aplicação** — são as credenciais próprias de um *usuário de
> teste* (`TESTUSER3126658525769167337`, `test_user_3126658525769167337@testuser.com`),
> dono da aplicação de teste `3559359816163002`. Usá-las é agir **como aquele
> usuário**, não como a plataforma.
>
> São três partes no split — comprador, vendedor e a aplicação. Misturá-las
> devolve "uma das partes é de teste" sem dizer qual. Por isso `contas` roda
> antes de qualquer cobrança.

Contas de teste criadas nesta rodada:

| Papel | `user_id` | E-mail |
|---|---|---|
| comerciante | `3672982509` | `test_user_3126658525769167337@testuser.com` |
| comprador | `3686169276` | `test_user_3028688276370264024@testuser.com` |

---

## M2 — o `preapproval` honra algum campo de split? **Não.**

Cinco candidatos, um por vez, `POST` seguido de `GET`:

```bash
export MP_SELLER_TOKEN=<token do comerciante>
export MP_PAYER_EMAIL=test_user_3028688276370264024@testuser.com
python3 docs/data-validation/scripts/provar-assinatura-mercadopago.py preapproval
```

| Candidato | Resposta | Eco no `GET` |
|---|---|---|
| `marketplace_fee` na raiz | **201** `4d75b97ce6e346d190d4fa3e73071a4c` | nenhum |
| `application_fee` na raiz | **201** `3b3b5a127af24f1a82669b386bf52843` | nenhum |
| `marketplace` na raiz | **201** `374fd711f55746a4947cc83d2a039887` | nenhum |
| `marketplace_fee` em `auto_recurring` | **201** `7e01e690f93747f0838774a7192b418f` | nenhum |
| `application_fee` em `auto_recurring` | **201** `f4d0840da5854fe5b8b76035396dcefb` | nenhum |

**Cinco aceites, zero ecos.** O `GET` completo de
`4d75b97ce6e346d190d4fa3e73071a4c` devolve o recurso inteiro, e não há **nenhum**
campo de taxa:

```json
{
  "id": "4d75b97ce6e346d190d4fa3e73071a4c",
  "payer_id": 3686169276,
  "collector_id": 3672982509,
  "application_id": 3559359816163002,
  "status": "pending",
  "auto_recurring": {
    "frequency": 1, "frequency_type": "days",
    "transaction_amount": 5.0, "currency_id": "BRL",
    "start_date": "2026-09-15T10:05:34.000-04:00",
    "has_billing_day": false, "free_trial": null
  },
  "summarized": { "quotas": null, "charged_amount": null, "…": null },
  "next_payment_date": "2026-09-15T10:05:34.000-04:00",
  "payment_method_id": null,
  "payment_method_id_secondary": null,
  "first_invoice_offset": null,
  "subscription_id": "4d75b97ce6e346d190d4fa3e73071a4c",
  "owner": null
}
```

O SDK oficial concorda: `Resources/PreApproval.php` e
`Resources/PreApproval/AutoRecurring.php` não declaram campo de taxa nenhum.
(O `GET` traz três campos que o SDK **não** modela — `payment_method_id_secondary`,
`subscription_id` e `owner` —, o que é a razão de medir em vez de só ler o SDK.)

**Conclusão: o ADR-0001 estava certo, e a causa não era o tipo da aplicação.**
O `preapproval` não tem onde guardar comissão. O sintoma continua sendo o pior
possível — `201` em tudo, sem erro que segure o engano na porta.

### O que esta medição ainda não fecha

O comerciante aqui é a conta de teste com a **aplicação dela**
(`application_id: 3559359816163002`), e não um vendedor que autorizou a
aplicação de Assinaturas da plataforma por OAuth. Falta o `preapproval` criado
com token de OAuth da aplicação `6990306155285574`. A evidência já é forte — o
recurso não tem o campo —, mas a última palavra é do **pagamento do ciclo**, em
`fee_details`, e não do `preapproval`.

---

## M7 — `/v1/advanced_payments`: existe, e está fechado para esta conta

Achado no SDK oficial e em nenhuma busca:
`Resources/AdvancedPayment.php` tem `disbursements[]`, e cada
`AdvancedPayment/Disbursement` carrega `collector_id`, `amount`,
`external_reference`, **`application_fee`**, `money_release_date` e `status`. É
split **1:N**, com comissão por recebedor.

`GET /v1/advanced_payments/search?limit=1`, leitura pura, com os três tokens:

| Token | Resposta |
|---|---|
| Bricks `TEST-` | **403** `PA_UNAUTHORIZED_RESULT_FROM_POLICIES` |
| Bricks produção | **403** `PA_UNAUTHORIZED_RESULT_FROM_POLICIES` |
| Preferências produção | **403** `PA_UNAUTHORIZED_RESULT_FROM_POLICIES` |

**Teste e produção, nas três aplicações.** Não é limitação de credencial: é
política de conta. O produto precisa ser liberado comercialmente pelo Mercado
Pago — mesma forma do bloqueio do Pagar.me.

Enquanto não for liberado, a questão fiscal do
[ADR-0003](../adr/0003-quem-cria-a-cobranca-emite-a-nota.md) **não precisa ser
respondida**: o caminho está fechado de qualquer jeito.

---

## M4 — cartão guardado no Mercado Pago

O que ficou provado nesta rodada:

**1. O cartão vive no gateway, e não no Moodle.** `POST /v1/customers` seguido de
`POST /v1/customers/{id}/cards`:

```
cliente: 3692881472-YJCg1wR4YL8WDe
cartao:  1789481274196  (final 3311)
```

**2. O Mercado Pago emite token de um cartão salvo SEM código de segurança.**
É a pergunta que decide o débito automático:

```bash
# sem security_code
POST /v1/card_tokens  {"card_id": "1789481274196"}
-> 6b2c3a7e93f374ed6f7179fab36a6513   status: active

# com security_code
POST /v1/card_tokens  {"card_id": "1789481274196", "security_code": "123"}
-> b7fa794c9556f384fc7264604f6991ce   status: active
```

Os dois funcionam. **A afirmação de que "o Transparente com cartão salvo exige
CVV a cada cobrança" não se sustenta na tokenização** — ao menos não neste
ponto do fluxo.

**3. Token de cartão é de uso ÚNICO.** Salvar o cartão consome o token; cobrar
com o mesmo token depois devolve `cc_rejected_other_reason` (pagamento
`1328177450`). Cada cobrança precisa do seu token.

### O que M4 ainda não fecha, e por quê

A cobrança com o token sem CVV **não foi aprovada nesta rodada**, e o motivo é
de ambiente, não do mecanismo: as tentativas alternaram `500 internal_error` e
`404 Card Token not found`. A combinação usada — token `TEST-` da plataforma
como comerciante, cliente criado como *productive customer* (o domínio
`@testuser.com` foi recusado com `Invalid domain user email for productive
customer`) — é justamente a mistura de ambientes que o Mercado Pago rejeita.

**Fechar M4 exige o token de vendedor por OAuth**, que precisa de navegador e do
túnel. Enquanto isso, o que está provado é o suficiente para não descartar o
Plano B: o cartão fica no gateway, e a tokenização não pede CVV.

---

## O que falta, e o que cada coisa exige

| Medição | Exige | Estado |
|---|---|---|
| M1 — dono de cada aplicação | nada | **fechada** |
| M2 — `preapproval` honra split | nada | **fechada** (não honra) |
| M7 — `advanced_payments` | nada | **fechada** (403 de política) |
| M4 — cartão salvo | parte com navegador | **parcial** |
| M3 — `fee_details` do ciclo | túnel + OAuth + navegador | pendente |
| M5 — escopo do OAuth por aplicação | túnel + OAuth | pendente |
| M6 — tópicos do webhook e `x-signature` | túnel | pendente |

O túnel é `cloudflared` com hostname fixo `mp.leodg.dev`, apontando para a porta
HTTP da worktree, com `$CFG->wwwroot` e `$CFG->sslproxy = 1` — o procedimento
está em [`mercadopago-split.md`](mercadopago-split.md). `localhost` não serve nem
para `redirect_uri` nem para `notification_url`.

---

## A consequência para o produto

**Assinatura B2C com split não sai pelo `preapproval`**, e agora isso está
medido para o tipo de aplicação certo. Restam dois caminhos, nesta ordem:

1. **Recorrência própria com cartão guardado no MP** — o aluno paga o ciclo 1, o
   Mercado Pago guarda o cartão, e cada ciclo seguinte é um `POST /v1/payments`
   com `application_fee`. Os dois pilares já têm evidência: o cartão fica no
   gateway, e a tokenização não pede CVV. Falta a cobrança aprovada.
2. **`advanced_payments`** — fechado por política, e dependeria de uma decisão
   fiscal ([ADR-0003](../adr/0003-quem-cria-a-cobranca-emite-a-nota.md)).

**Assinatura B2B não depende de nada disso.** Sem terceiro, não há split: o
`preapproval` serve à mensalidade da empresa hoje, com cobrança automática e
cartão guardado no Mercado Pago.

---

## Pedido ao suporte do Mercado Pago

Duas liberações, e **só a segunda é bloqueante**. A primeira é opcional — o
Plano B não depende dela, e adotá-la exigiria rever o
[ADR-0003](../adr/0003-quem-cria-a-cobranca-emite-a-nota.md).

Texto para abrir o chamado, com a evidência junto:

> **Conta:** `3675841384` (CNPJ) · **Aplicações:** `2401225442871147`
> (API de Preferências), `6990306155285574` (Assinaturas), `2598194068751669`
> (Checkout Bricks) — as três na mesma conta.
>
> Operamos um marketplace de cursos com split de pagamento. O split já funciona
> em venda avulsa pela API de Preferências: pagamento `178004552586`, R$ 5,00
> com `marketplace_fee` de R$ 1,25 creditado à conta da aplicação. Precisamos
> agora de **assinatura recorrente com split**, e temos duas perguntas:
>
> **1. Habilitação de `/v1/advanced_payments` (opcional).** Qualquer chamada,
> inclusive a leitura `GET /v1/advanced_payments/search?limit=1`, responde
> `403` com `PA_UNAUTHORIZED_RESULT_FROM_POLICIES` ("At least one policy
> returned UNAUTHORIZED"). Testado com credencial de produção **e** de teste,
> nas três aplicações acima. É liberação comercial? Quais os requisitos?
>
> **2. Cobrança recorrente com cartão salvo e `application_fee` (bloqueante).**
> Nosso modelo é: o aluno paga o primeiro ciclo, o cartão fica salvo no Mercado
> Pago (`/v1/customers/{id}/cards`), e cada ciclo seguinte é um
> `POST /v1/payments` com `application_fee`, criado com o `access_token` do
> vendedor obtido por OAuth da nossa aplicação. **A cobrança é iniciada por nós,
> sem o portador presente e sem novo CVV.**
>
> Confirmamos que `POST /v1/card_tokens` com apenas `{"card_id": "..."}` — sem
> `security_code` — devolve token com `status: active`. Perguntas:
>
> - essa cobrança sem CVV é suportada em **produção** para transações iniciadas
>   pelo estabelecimento, ou exige habilitação prévia na conta/aplicação?
> - o `application_fee` é honrado nesse formato, ou depende de a aplicação estar
>   declarada com algum modelo de integração específico?
> - há taxa de recusa/chargeback diferente para transação sem CVV?
>
> Não armazenamos dado de cartão: o PAN é tokenizado no Card Payment Brick, e
> guardamos apenas os identificadores devolvidos pela API.

**Por que a segunda pergunta é a que importa.** A tokenização sem CVV está
medida e funciona; o que não foi possível exercitar aqui é a **cobrança
aprovada** com esse token, porque a combinação de ambiente disponível
(credencial `TEST-` da plataforma como comerciante, cliente "produtivo")
é justamente a mistura que o Mercado Pago recusa. Se a resposta for "exige
habilitação", o Plano B depende dela; se for "é suportado", a medição se fecha
com o token de vendedor por OAuth.
