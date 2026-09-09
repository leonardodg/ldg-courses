# Medir o Pagar.me em homologação

Como provar que o `paygw_pagarme` funciona — e o registro de por que, em
09/09/2026, **não foi possível provar nada**. A conta de sandbox recusa criar
recebedor e nenhuma forma de pagamento processa, então o split, que é o coração
do modelo, continua sem prova neste gateway.

O irmão deste documento é [`asaas-sandbox.md`](asaas-sandbox.md), que prova o
split da cobrança avulsa e é o formato que este segue. A diferença é que lá o
roteiro termina com um número, e aqui termina com um pedido ao suporte.

> **Credenciais não vivem neste arquivo.** Ele está versionado e o repositório
> está no GitHub. As chaves ficam em
> `.devcontainer/secrets/pagarme-sandbox.env`, que o `.gitignore` cobre
> (`.gitignore:81`).

---

## O que você precisa antes de começar

### Uma conta, e por quê só uma

No Asaas o split exige **duas contas distintas** — com uma só, a API recusa com
*"Não é permitido split para sua própria carteira"*. No Pagar.me o desenho é
outro: o split acontece entre **recebedores** (`re_…`), que são objetos
*dentro* de uma conta. Uma conta de sandbox basta, porque é o mesmo desenho de
produção visto de dentro.

É o que o [ADR-0003](../adr/0003-quem-cria-a-cobranca-emite-a-nota.md) fixa: a
cobrança nasce na conta do **vendedor**, e a plataforma é um recebedor dentro
dela. A consequência incômoda e assumida é que o `recipient_id` da plataforma
**é diferente em cada vendedor**.

### A conta precisa estar liberada para recebedor e para adquirente

São duas liberações distintas, e **esta conta não tem nenhuma das duas**. É o
achado principal deste documento; a seção de resultado traz as respostas cruas.

---

## Onde ficam as credenciais

`.devcontainer/secrets/pagarme-sandbox.env`:

```bash
PAGARME_BASE_URL='https://api.pagar.me/core/v5'
PAGARME_SECRET_KEY='sk_test_...'
PAGARME_PUBLIC_KEY='pk_test_...'
PAGARME_WEBHOOK_USER='...'
PAGARME_WEBHOOK_PASSWORD='...'
```

**Não existe host de homologação.** O ambiente vem do **prefixo da chave**:
`sk_test_` é sandbox, `sk_` é produção, e as duas batem no mesmo endereço. O
plano de 2026-08-27 mandava usar `https://sdx-api.pagar.me/core/v5`; esse host
não existe:

```
GET https://sdx-api.pagar.me/core/v5/recipients
  -> HTTP 404 {"message":"no Route matched with those values"}
```

Isso tem consequência de código: `BASE_URL` é constante, e quem decide o
ambiente é uma função sobre o prefixo da chave — não um mapa de URLs como no
`asaas_client`.

A autenticação é **Basic**, com a chave secreta como usuário e senha vazia. Não
é `Bearer`, e não é header próprio como o `access_token` do Asaas.

```bash
curl -u "$PAGARME_SECRET_KEY:" https://api.pagar.me/core/v5/orders
```

---

## O endereço precisa ser público

O Pagar.me alcança o webhook de fora, então `https://localhost:8453` não serve.

O túnel é o **mesmo do Mercado Pago**, `mp.leodg.dev`, reaproveitado de
propósito — os caminhos não colidem, porque cada gateway tem o próprio
`webhook.php`. É um `cloudflared` token-based (`/etc/cloudflared/token`, não
`~/.cloudflared/config.yml`), e ele já entrega em `localhost:8453`.

**O `wwwroot` precisa acompanhar, e a falta disso é sutil.** Sem ele o Moodle
recebe a requisição com `Host: mp.leodg.dev`, compara com o `wwwroot` interno e
responde `303` para `https://localhost:8453` — endereço que o Pagar.me não
alcança. O webhook nunca chega, e o painel registra `failed` sem dizer por quê.

Foi o que aconteceu: as dez primeiras entregas desta conta constam como
`failed`, e a causa era o redirecionamento, não o endpoint.

Em `config-local.php` da worktree (gitignored, `.gitignore:25`):

```php
$CFG->wwwroot = 'https://mp.leodg.dev';
$CFG->sslproxy = true;
```

O `sslproxy` é obrigatório: o TLS termina no `cloudflared`, o PHP vê a
requisição como `http`, e sem ele o Moodle monta URL `http` em página `https`.

Como conferir que ficou certo — **o 401 é o resultado bom**:

```
/                                    HTTP 200   (antes: 303)
/payment/gateway/asaas/webhook.php   HTTP 401   (antes: 303)
```

O `401` quer dizer que a requisição chegou ao código e foi recusada por falta
de credencial. O `303` queria dizer que ela nem entrou.

### A URL para cadastrar no painel

```
https://mp.leodg.dev/payment/gateway/pagarme/webhook.php
```

Enquanto o plugin não existir, ela responde `404` — e o painel vai acumular
entrega falhada. Cadastre junto com a primeira versão do plugin, não antes.

---

## Caminho rápido: o script

```bash
set -a && . .devcontainer/secrets/pagarme-sandbox.env && set +a
python3 docs/data-validation/scripts/provar-split-pagarme.py
```

Sem dependência externa — só a biblioteca padrão do Python.

Hoje ele para no passo 0, e **é para parar mesmo**: contornar a recusa seria
medir outra coisa.

---

## Caminho manual, com `curl`

### 1. Conferir que a chave responde

```bash
curl -s -u "$PAGARME_SECRET_KEY:" \
  https://api.pagar.me/core/v5/recipients | python3 -m json.tool
```

`200` com `{"data": []}` — a chave é válida e a conta está vazia.

### 2. Procurar o recebedor padrão

```bash
curl -s -u "$PAGARME_SECRET_KEY:" \
  https://api.pagar.me/core/v5/recipients/default | python3 -m json.tool
```

```
HTTP 412
{"message": "There is no default recipient registered for this account."}
```

O plano original deixou *"como obter o recebedor padrão da conta do vendedor"*
como **a confirmar**. Está confirmado: não há. Se as regras de split precisam
somar 100%, a parte do vendedor não tem para onde ir enquanto ninguém criar o
recebedor dele.

### 3. Criar o recebedor — onde tudo para

```bash
curl -s -u "$PAGARME_SECRET_KEY:" -H 'Content-Type: application/json' \
  -d '{"name":"Plataforma","email":"p@exemplo.test","document":"<CNPJ>",
       "type":"company","code":"plat-1",
       "default_bank_account":{"holder_name":"Plataforma","holder_type":"company",
         "holder_document":"<CNPJ>","bank":"033","branch_number":"1234",
         "branch_check_digit":"0","account_number":"12345",
         "account_check_digit":"6","type":"checking"},
       "transfer_settings":{"transfer_enabled":true,"transfer_interval":"Daily",
         "transfer_day":0}}' \
  https://api.pagar.me/core/v5/recipients | python3 -m json.tool
```

```
HTTP 412
{"message": "The recipient could not be created : action_forbidden |  |
             This company it not allowed to create a recipient"}
```

Medido com `type: individual` e com `type: company`, documento CPF e CNPJ. A
recusa é a mesma nos quatro casos: **é da conta, não do documento**.

O `type` aceita `individual` ou `company`. `corporation`, que o plano original
sugeria, volta `422` — e a mensagem sobre o `type` só aparece depois de o
`holder_type` do `default_bank_account` já estar certo, então dá para gastar
duas rodadas achando que o erro é outro.

### 4. A cobrança, sem split

```bash
curl -s -u "$PAGARME_SECRET_KEY:" -H 'Content-Type: application/json' \
  -d '{"items":[{"amount":10000,"description":"Curso","quantity":1,"code":"c1"}],
       "customer":{...},
       "payments":[{"payment_method":"pix","pix":{"expires_in":3600}}]}' \
  https://api.pagar.me/core/v5/orders | python3 -m json.tool
```

`HTTP 200`. E dentro do `200`:

```
order.status ..... failed
charge.status .... failed
gateway .......... 500 internal_error |  | Erro desconhecido no proxy
```

Nenhum `qr_code`. **O 200 é do protocolo, não do dinheiro.**

---

## Resultado — 09/09/2026

Conta `acc_EgeXMOdFOCrKvpNJ`, chave `sk_test_…`, oferta de R$ 100,00 e comissão
de 25%. A conta estava limpa: `orders`, `charges`, `customers`, `plans` e
`subscriptions` voltaram todos `{"data": []}` antes da primeira chamada, então
tudo abaixo nasceu aqui.

| Medição | O que respondeu |
|---|---|
| Host de homologação | **não existe** — `sdx-api.pagar.me` dá `404 no Route matched with those values` |
| Chave `sk_test_` no host de produção | `200` — o ambiente vem do prefixo da chave |
| Autenticação | Basic, chave como usuário e senha vazia |
| Recebedor padrão | `412 There is no default recipient registered for this account.` |
| `POST /recipients` | `412 action_forbidden — This company it not allowed to create a recipient`, nos quatro tipos testados |
| `POST /orders` Pix, cartão, boleto | `200` no HTTP, `failed` na cobrança, `500 internal_error \| Erro desconhecido no proxy` no adquirente |
| `POST /orders` com `split[]` e recebedor inexistente | `200` no HTTP, e no `GET`: `404 Recipient not found`, `splits: null` |
| `POST /subscriptions` com cartão | `412 Could not create credit card. The card verification failed.` |
| `POST /tokens` com `pk_test_` | `200`, token emitido — **a tokenização funciona** |
| `GET /hooks` | `200`, cinco entregas registradas — **o webhook dispara** |

As cobranças, lidas de volta:

```
ch_pLMZz9SGNi04DKQR   boleto        failed       gw=500  internal_error |  | Erro desconhecido no proxy
ch_RLOboaQCZAF1o5eZ   credit_card   processing   gw=500  internal_error |  | Erro desconhecido no proxy
ch_N6XEkm8ivuxnRPY8   pix           failed       gw=500  internal_error |  | Erro desconhecido no proxy
ch_ENJxvkweHZUyvpe3   boleto        failed       gw=412  The item Code is required.
ch_aDVO12oC8hAJpQEA   credit_card   failed       gw=412  The item Code is required.
ch_K8nGxpnHATW5GXJ6   pix           failed       gw=500  internal_error |  | Erro desconhecido no proxy
```

A do cartão ficou em `processing` e **nunca saiu de lá** — relida vinte segundos
depois, mesmo estado, mesmo erro. Pendência que não resolve é pior que recusa:
o plugin ficaria reconciliando para sempre uma cobrança que nunca vai concluir.

São **dois bloqueios independentes**, e é importante não confundi-los:

1. **Recebedor** — sem ele não existe split, e o split é o motivo de o Pagar.me
   ter sido escolhido.
2. **Adquirente** — sem ele não existe cobrança nenhuma, com ou sem split.

Liberar só o primeiro não destrava a prova.

---

## O que este roteiro encontrou

**O `200` do `POST /orders` não significa nada.** Uma order com `split[]`
apontando para dois `recipient_id` **inventados** — `re_inexistente0000000000` e
`re_inexistente1111111111`, que nunca existiram — foi aceita com `HTTP 200` e um
corpo completo, com `id`, `code`, cliente criado e item ativo.

A verdade só apareceu no `GET`:

```
order.status ..... failed
charge.status .... failed
gateway .......... 404 Recipient not found
charge.splits .... null
order.splits ..... null
```

Isto é a terceira ocorrência do padrão que motivou esta sessão, e vale separar
do que aconteceu nos outros dois:

| Gateway | O que fez com o campo de dinheiro |
|---|---|
| Mercado Pago, `preapproval` | aceitou `marketplace_fee`, devolveu `201`, **descartou em silêncio**. O `GET` não denuncia |
| Asaas, `PUT /subscriptions` | aceitou `creditCard`, devolveu `2xx`, **não guardou**. O `GET` não denuncia |
| Pagar.me, `POST /orders` | aceitou `split[]`, devolveu `200`, **e o `GET` denuncia** — `splits: null` e `404 Recipient not found` |

O Pagar.me é o menos perigoso dos três: ele erra alto, desde que alguém leia de
volta. Mas o `200` do `POST` continua sem valor como sinal, e um plugin que
tratasse `2xx` como sucesso registraria venda de cobrança que nunca existiu.

**Consequência de código, e ela vale mesmo sem o split:** o `payment_processor`
não pode olhar o status HTTP. Tem que ler, na ordem, `charge.status`,
`charge.last_transaction.gateway_response.code` e `charge.splits` — e tratar
`splits: null` como comissão que não aconteceu, nunca como "o gateway não
informou".

**O `code` do item é obrigatório, e a falta dele também não vira erro de
requisição.** Volta `200` com a cobrança `failed` e `The item Code is required`
enterrado no `gateway_response`. Mesma armadilha, custo menor.

---

## Armadilhas já descobertas

| Sintoma | Causa |
|---|---|
| `404 no Route matched with those values` | `sdx-api.pagar.me` não existe. Host único; o ambiente é o prefixo da chave |
| `200` e nenhum QR Code | O `200` é do protocolo. Ler `charge.status` e `gateway_response` |
| *"The item Code is required."* | `items[].code` é obrigatório, e a ausência vira cobrança `failed`, não erro `4xx` |
| *"The type field is invalid. Possible values are 'individual' or 'company'."* | `corporation` não é valor válido, apesar do que dizia o plano original |
| *"The holder_type field is invalid…"* | O `holder_type` do `default_bank_account` usa `individual`/`company`, e mascara o erro do `type` até ser corrigido |
| *"This company it not allowed to create a recipient"* | Conta não liberada para split. Não é o documento — testado nos quatro tipos |
| *"Erro desconhecido no proxy"* | Conta sem adquirente no ambiente de teste. Atinge Pix, cartão e boleto |
| Cobrança de cartão presa em `processing` | Mesmo bloqueio do adquirente. Não expira sozinha |

---

## O que precisa ser pedido ao Pagar.me

O bloqueio é comercial, não técnico — nenhuma mudança de código o resolve. São
**dois pedidos distintos**, e é importante que o chamado os separe: atender
apenas um não destrava a integração, e um suporte que leia "não consigo criar
recebedor" costuma resolver só isso.

Texto pronto para colar no chamado:

> **Assunto:** Habilitar split (recebedores) e processamento em ambiente de
> teste — conta `acc_EgeXMOdFOCrKvpNJ`
>
> Olá,
>
> Estou integrando a API v5 do Pagar.me a uma plataforma de cursos que opera
> como marketplace, com split de pagamento entre o vendedor do curso e a
> plataforma. Estou usando a chave de teste (`sk_test_…`) da conta
> `acc_EgeXMOdFOCrKvpNJ` e esbarrei em dois bloqueios que parecem ser de
> habilitação da conta, não de uso da API. Seguem os dois, com as respostas que
> recebi.
>
> **1. A conta não pode criar recebedores.**
>
> `POST /core/v5/recipients` responde:
>
> ```
> HTTP 412
> {"message": "The recipient could not be created : action_forbidden |  |
>              This company it not allowed to create a recipient"}
> ```
>
> Testei com `type: "individual"` e `type: "company"`, com CPF e com CNPJ, e a
> resposta é a mesma nos quatro casos. `GET /core/v5/recipients/default` também
> responde `412 There is no default recipient registered for this account.`
>
> Como o split acontece entre recebedores, sem essa habilitação não consigo
> montar nem testar a divisão de valores, que é a razão de a integração existir.
>
> **2. Nenhuma forma de pagamento processa no ambiente de teste.**
>
> `POST /core/v5/orders` responde `HTTP 200`, mas a cobrança nasce `failed` e o
> `gateway_response` traz erro 500. Acontece igual em Pix, cartão e boleto:
>
> ```
> ch_N6XEkm8ivuxnRPY8   pix           failed       500  internal_error |  | Erro desconhecido no proxy
> ch_RLOboaQCZAF1o5eZ   credit_card   processing   500  internal_error |  | Erro desconhecido no proxy
> ch_pLMZz9SGNi04DKQR   boleto        failed       500  internal_error |  | Erro desconhecido no proxy
> ```
>
> Todas em 09/09/2026, por volta das 15:20 UTC. A do cartão
> (`ch_RLOboaQCZAF1o5eZ`) ficou presa em `processing` e permanecia assim quando
> reconsultei.
>
> O corpo das requisições está sendo aceito — o cliente é criado, o item fica
> `active` e a order recebe id. A tokenização de cartão com a chave pública
> (`POST /core/v5/tokens` com `pk_test_…`) funciona e devolve `200`. O erro
> aparece só no processamento.
>
> **O que preciso:**
>
> 1. Habilitar a criação de recebedores (split / marketplace) para esta conta.
> 2. Habilitar o processamento de Pix, cartão e boleto no ambiente de teste.
>
> Se alguma das duas exigir análise comercial, documentação ou contrato
> específico, me diga o que enviar que eu providencio.
>
> Obrigado.

Se pedirem o CNPJ da plataforma, é o mesmo caso do Asaas e do Mercado Pago: o
CNPJ é exigido de quem opera o marketplace, não de quem vende — ver
[ADR-0010](../adr/0010-vendedor-pessoa-fisica-no-mercado-pago.md).

---

## O que continua sem prova

Tudo que dependia da cobrança processar. Nenhum destes foi medido, e **nenhum
deve ser presumido a partir da documentação** — foi presumir da documentação que
produziu o ADR-0001 errado sobre o `preapproval`:

- **O split chega na cobrança?** Não se sabe. O único `split[]` que a conta
  aceitou foi um com recebedores inexistentes, e ele voltou `null`.
- **`type: percentage` incide sobre o bruto ou sobre o líquido?** No Asaas é o
  líquido, e isso obrigou a comissão sobre o bruto a virar valor fixo. Aqui, sem
  medição.
- **As regras precisam somar 100%?** O plano original afirma que sim. Não
  confirmado.
- **Quem paga a taxa**, e o que `liable`, `charge_processing_fee` e
  `charge_remainder_fee` fazem com o valor que chega em cada extrato.
- **Há assinatura com split, e ele vale em cada ciclo** ou só na primeira
  cobrança.
- **Quantas cobranças a assinatura gera de uma vez, e em que ordem a lista
  volta.** No Asaas vêm quatro ou cinco, da mais distante para a mais próxima, e
  isso virou bug real.
- **O estorno reverte o split**, e para quais formas de pagamento.
- **Estornar um ciclo cancela a assinatura?**
- **Pix serve para assinatura?**
- **Qual é o valor mínimo** em que a taxa não come a comissão.

O que **está** provado, e vale guardar: o host, o formato da autenticação, a
obrigatoriedade do `items[].code`, os valores aceitos em `type`, o fato de a
tokenização com `pk_test_` funcionar, o de o webhook disparar, e o protocolo de
leitura que separa um `200` de uma cobrança viva.
