# Provar o split no Pagar.me

Como provar que o `paygw_pagarme` divide o dinheiro — e **ele divide**, provado
em 11/09/2026 depois de dois dias travado numa liberação de conta.

O irmão deste documento é [`asaas-sandbox.md`](asaas-sandbox.md), que prova o
mesmo no Asaas. A diferença que mais importa entre os dois está aqui: no Asaas
o `GET` da cobrança mostra o split; **aqui ele mente**, e quem olhar só para
ele vai concluir que o split falhou quando funcionou.

## O resultado, primeiro

```
cobranca ... ch_KME2JgJuJnT1XlX7 | paid | R$ 100,00
vendedor ... amount R$ 75,00 | taxa R$ 4,49 | liquido R$ 70,51
plataforma . amount R$ 25,00 | taxa R$ 0,00 | liquido R$ 25,00
```

Comissão de 25% sobre R$ 100,00 entregou **exatamente R$ 25,00**. A taxa de
R$ 4,49 saiu inteira do vendedor, porque é ele que carrega
`charge_processing_fee: true`. A soma fecha em R$ 100,00.

**O `percentage` do Pagar.me incide sobre o BRUTO** — o oposto do Asaas, onde
`percentualValue` incide sobre o líquido.

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

### A conta precisa estar liberada, e são liberações separadas

Split não é um interruptor de painel: é capacidade comercial, e vem em pedaços.
A conta `acc_Xv3ne2OsOXCJd4GB` tem **recebedores, cartão e boleto** desde
11/09/2026, e continua **sem Pix e sem split em assinatura**.

A primeira conta (`acc_EgeXMOdFOCrKvpNJ`) não tem nenhuma delas, e é por isso
que este documento começou como registro de bloqueio.

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

O túnel é um `cloudflared` **token-based** — a configuração vive no painel do
Cloudflare Zero Trust, não num `config.yml` desta máquina. Não adianta procurar
arquivo em `~/.cloudflared`: só existe `/etc/cloudflared/token`, e acrescentar
hostname é operação de painel.

`pagarme.leodg.dev` foi criado assim, apontando para `localhost:8453`. O
`mp.leodg.dev` continua existindo e serve a mesma porta — os caminhos nunca
colidem, porque cada gateway tem o próprio `webhook.php`.

Ao criar o hostname, **ligue o `No TLS Verify`**: a porta responde com o
certificado auto-assinado do devcontainer, e sem essa opção o `cloudflared`
recusa a origem e o hostname sobe respondendo `502`.

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

Desde 09/09/2026 há hostname próprio, `pagarme.leodg.dev`, criado no painel do
Cloudflare e apontando para a mesma porta:

```
https://pagarme.leodg.dev/payment/gateway/pagarme/webhook.php
```

Com o plugin instalado, ela responde `401` a quem chega sem credencial — que é
o resultado bom. Antes de o plugin existir ela respondia `404`.

---

## Caminho rápido: o script

```bash
set -a && . .devcontainer/secrets/pagarme-sandbox.env && set +a
python3 docs/data-validation/scripts/provar-split-pagarme.py
```

Sem dependência externa — só a biblioteca padrão do Python.

Ele cria dois recebedores novos, cobra R$ 100,00 no cartão com split de 25%,
espera o payable nascer e fecha a conta. Termina assim:

```
Comissao pedida .... R$ 25.00  (25.0% de R$ 100.00)
Comissao recebida .. R$ 25.00

BATE. O percentual do Pagar.me incide sobre o BRUTO.
```

Usa **cartão, não Pix**, porque o Pix ainda não processa nesta conta. Quando
liberar, troque — o Pix é o caminho principal do plugin, e a prova dele vale
mais que a do cartão.

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

### 3. Criar o recebedor — onde tudo parava até 11/09

Na conta liberada isto responde `200` e devolve um id `re_…`. O que segue é a
recusa da conta **não** liberada, guardada porque é como o bloqueio se
apresenta:

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

### Segunda conta, mesma resposta

Uma conta nova (`acc_Xv3ne2OsOXCJd4GB`), criada declaradamente **com permissão
de criar recebedor**, respondeu exatamente igual. Vale registrar o que foi
descartado antes de culpar a permissão:

| Hipótese testada | Resultado |
|---|---|
| A chave nova não estava em uso | Descartada — conferida no ambiente, `sk_test_1c0181ef…593a2d` |
| O payload estava no formato antigo | Descartada — o formato novo, com `register_information`, dá o mesmo `412` |
| A recusa é do tipo de documento | Descartada — `individual` e `company`, CPF e CNPJ, os quatro recusam |
| A conta tinha sujeira de teste anterior | Descartada — zero recipients, orders, charges e subscriptions |

As duas contas, lado a lado, com o mesmo corpo de requisição:

```
conta-1 (acc_EgeX...)   412  action_forbidden | This company it not allowed to create a recipient
conta-2 (acc_Xv3n...)   412  action_forbidden | This company it not allowed to create a recipient
```

A documentação explica: *"Esta funcionalidade está disponível apenas para
clientes PSP"*. Split não é recurso que se liga num interruptor de painel — é
contrato comercial.

**Achado lateral que vai importar em produção:** `GET /transfers` responde
`401 "IP de origem não autorizado a realizar essa operação."` Existe allowlist
de IP para algumas operações, e o IP da VPS vai precisar entrar nela — senão
transferência e consulta de extrato falham com um erro que não parece de
permissão.

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

---

## Resultado — 11/09/2026: o split funciona, e o `GET` não conta

Conta `acc_Xv3ne2OsOXCJd4GB`, com os recebedores liberados.

| Medição | Resposta |
|---|---|
| `POST /recipients` | **`200`** — destravou. Id sai como `re_…`, não `rp_…` |
| Split numa cobrança de cartão | **funciona**, provado pelo extrato |
| Base do percentual | **bruto**. 25% de R$ 100,00 = R$ 25,00 exatos |
| Quem paga a taxa | o vendedor, inteira, por `charge_processing_fee: true` |
| Regras somando 100% | aceitas |
| `charge.splits` no `GET` | **`null`, mesmo com o split tendo acontecido** |
| Estorno | reverte o split: nasce um payable **negativo**, `type: refund` |
| `amount` do split | precisa ser **inteiro**; `75.0` é recusado com `400` |
| Atraso do payable | **~16 segundos** depois do pagamento |
| Pix | **`400 action_forbidden`** — "Sem ambiente configurado para este tipo de transação" |
| Assinatura com split | **`400`** em todos os formatos testados |

### O `charge.splits` mente, e é a armadilha mais cara daqui

Esta é a quarta vez que uma API deste projeto responde `2xx` sobre um campo de
dinheiro sem contar a verdade, e é a **mais perigosa das quatro**:

| Gateway | O que fez |
|---|---|
| Mercado Pago, `preapproval` | aceitou `marketplace_fee` e **descartou** — errou para menos |
| Asaas, `PUT /subscriptions` | aceitou `creditCard` e **não guardou** — errou para menos |
| Pagar.me, recebedor inexistente | aceitou, e o `GET` **denunciou** — comportamento bom |
| Pagar.me, split válido | **fez o trabalho e não contou** — erra para mais |

As três primeiras fazem você achar que deu certo quando não deu. A quarta faz
você achar que deu errado quando deu — e a reação natural, "o split falhou,
vou desligar", custaria a comissão de todas as vendas.

**Onde ler, então:**

```bash
curl -s -u "$PAGARME_SECRET_KEY:" \
  "https://api.pagar.me/core/v5/payables?recipient_id=<rp>&size=100" \
  | python3 -m json.tool
```

O filtro `?charge_id=` **não funciona** — devolve lista vazia. O payable é
listado por recebedor e traz o `charge_id` dentro, então a separação é sua.

### Duas armadilhas de formato que custam tempo

**`amount` precisa ser inteiro.** Um `75.0` float volta `400 "The request is
invalid."` sem dizer qual campo. Foi o que derrubou a primeira rodada deste
script, e a mensagem não ajuda em nada.

**O payable demora.** Medido: ~16 segundos. Uma leitura única logo após a
cobrança mostra zero e parece falha de split. O plugin trata isso na
reconciliação, e o script faz polling.

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

**Em 11/09/2026 o primeiro pedido foi atendido** para a conta
`acc_Xv3ne2OsOXCJd4GB`: recebedores criam, cartão e boleto processam, e o split
foi provado. Sobraram dois pedidos menores, e o texto abaixo continua servindo
de modelo para eles:

> **Assunto:** Habilitar Pix e split em assinatura — conta `acc_Xv3ne2OsOXCJd4GB`
>
> Olá,
>
> Os recebedores foram liberados nesta conta e o split já funciona em cobrança
> de cartão — obrigado. Sobraram duas coisas:
>
> **1. Pix não processa.** `POST /core/v5/orders` com
> `payment_method: "pix"` devolve `200`, mas a cobrança nasce `failed`:
>
> ```
> gateway_response 400
> action_forbidden |  | Sem ambiente configurado para este tipo de transação.
> ```
>
> Exemplo: `ch_yBrRwbnT2khbaJgP`. Cartão e boleto, na mesma conta e no mesmo
> momento, processam normalmente.
>
> **2. Assinatura não aceita split.** `POST /core/v5/subscriptions` com o
> campo `split` responde `400 "The request is invalid."` em todos os formatos
> que testei. E `PATCH /core/v5/subscriptions/{id}/split` responde
> `412 "Can't update the split on subscription because doesn't has split."`,
> o que sugere que a assinatura precisa nascer com o split — mas a criação o
> recusa. Preciso saber se isso é uma habilitação de conta, como os
> recebedores eram, ou se o formato de criação é outro.
>
> Obrigado.

<details>
<summary>O chamado original, de 09/09, que destravou os recebedores</summary>

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

</details>

Se pedirem o CNPJ da plataforma, é o mesmo caso do Asaas e do Mercado Pago: o
CNPJ é exigido de quem opera o marketplace, não de quem vende — ver
[ADR-0010](../adr/0010-vendedor-pessoa-fisica-no-mercado-pago.md).

---

## O que continua sem prova

### Pix — e é o caminho principal do plugin

```
400 action_forbidden | Sem ambiente configurado para este tipo de transação.
```

Cartão e boleto processam nesta conta; só o Pix não. A mensagem é mais
específica que o `500 Erro desconhecido no proxy` de 09/09, e nomeia a causa:
falta habilitar o **tipo de transação** Pix.

Pesa mais do que parece. O Pix é a forma padrão do plugin, é a única com página
própria (`pix.php`, com QR Code e polling), e foi por causa dele que o desenho
recusou o Link de Pagamento hospedado. Enquanto não liberar, o caminho
principal segue sem prova — e o que está provado é o caminho do cartão.

### Assinatura com split — e isto muda o escopo

`POST /subscriptions` com `split` responde `400 "The request is invalid."` em
**quatro formatos**: v5 padrão, estilo v4 (`percentage`/`liable` soltos), sem
`options`, e com `charge_remainder` no singular. Dentro de `items[]` o `200`
volta com o split **descartado** — nenhum payable nasce.

Mas o `PATCH /subscriptions/{id}/split` responde:

> `412 Can't update the split on subscription because doesn't has split.`

Ou seja: a capacidade **existe** e a assinatura precisa nascer com ela. O mais
provável é ser outra habilitação de conta, como os recebedores foram.

Há um segundo impedimento, independente: o filtro `?subscription_id=` de
`GET /charges` é **ignorado** — um id inventado devolve a conta inteira — e as
cobranças não carregam vínculo com a assinatura. Sem isso não há como listar as
cobranças de uma assinatura, o que derruba a fatura em aberto e a escolha do
vencimento mais próximo.

**Por isso o plugin recusa oferta recorrente na porta.** Cobrar sem split
renderia comissão zero em silêncio, que é pior que recusar.

### Ainda não medido

- **Estorno parcial** reduz a comissão? O total reverte (payable negativo);
  o parcial não foi testado.
- **Boleto estorna?** Está fora de `REFUNDABLE_METHODS` por analogia com o
  Asaas, não por medição.
- **Valor mínimo** em que a taxa não come a comissão.
- **O ciclo da assinatura inteiro** — bloqueado pelo acima.

## O que está provado

O host único e o ambiente pelo prefixo da chave; a autenticação Basic; a
obrigatoriedade do `items[].code`; `type` aceitando `individual`/`company`; o
prefixo `re_` do recebedor; a tokenização com `pk_test_`; o webhook disparando;
o protocolo de leitura que separa um `200` de uma cobrança viva; **o split, com
o extrato dos dois lados**; a base bruta do percentual; quem paga a taxa; e o
estorno revertendo o split.

## O plugin, escrito antes da prova

Em 09/09/2026 o usuário decidiu não esperar a liberação e mandou implementar
pela documentação, corrigindo depois com os testes reais. O `paygw_pagarme`
existe e está completo — Pix com página própria, boleto, cartão tokenizado,
assinatura com ciclos, estorno, cancelamento e fatura em aberto.

**Isso muda o que este roteiro serve para fazer.** Ele deixa de ser "o que
medir antes de codar" e passa a ser **a lista de verificação que vai derrubar
as suposições**. Cada item da seção anterior está marcado no código com
`NAO MEDIDO`, e a primeira rodada com a conta liberada deve conferir, nesta
ordem:

1. `pagarme_client::build_split()` — se o `percentage` incidir sobre o bruto e
   não sobre o líquido, a base `net` entrega comissão maior que a pedida.
2. Se uma regra só bastar, as duas regras somando 100% viram complicação
   desnecessária — mas se forem exigidas, o vendedor precisa mesmo do `rp_`
   dele, e o `link.php` já o descobre pelo `GET /recipients/default`.
3. `payment_processor::PAID_STATUSES` — confirmar que `overpaid` existe mesmo e
   que `underpaid` não deve liberar acesso.
4. `REFUNDABLE_METHODS` — hoje boleto está de fora por analogia com o Asaas.
   Medir se o Pagar.me estorna boleto muda a lista.
5. Se estornar um ciclo **não** cancelar a assinatura, o `refund()` já cancela
   antes de estornar, e a ordem está certa. Se cancelar sozinho, o cancelamento
   vira redundante — e a redundância é inofensiva, mas merece comentário.
6. Os nomes de evento em `is_relevant_event()` foram tirados da documentação;
   os medidos nesta conta foram só `order.created`, `order_item.created` e
   `order.payment_failed`, porque nada foi pago.
