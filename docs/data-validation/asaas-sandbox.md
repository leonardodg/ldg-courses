# Testar o Asaas em homologação

Como provar que o `paygw_asaas` funciona — incluindo **o split**, que é o coração
do modelo e nunca foi visto funcionar neste projeto.

Serve para os dois caminhos: o script que faz tudo de uma vez, e o passo a passo
manual com `curl` para quando algo der errado e você precisar ver onde.

> **Credenciais não vivem neste arquivo.** Ele está versionado e o repositório
> está no GitHub. As chaves ficam em `.devcontainer/secrets/asaas-sandbox.env`,
> que o `.gitignore` cobre (`.gitignore:70`). O modelo do arquivo está
> [abaixo](#onde-ficam-as-credenciais).

---

## O que você precisa antes de começar

### Duas contas, e por quê

O split só pode ser provado com **duas contas distintas**. Com uma só, o Asaas
recusa:

> Não é permitido split para sua própria carteira.

Que é exatamente o erro do Mercado Pago em outra roupagem — lá vendedor e
marketplace eram a mesma conta, o `marketplace_fee` não transferiu nada, e
ninguém percebeu porque não houve erro.

| Papel | Conta | Como obter |
|---|---|---|
| **Plataforma** | recebe a comissão pelo split | conta sandbox **pessoa jurídica** |
| **Vendedor** | emite a cobrança, fica com o líquido, emite a nota | subconta via API, **também CNPJ** |

**As duas precisam ser CNPJ**, e por motivos diferentes. A plataforma, porque
conta PF não cria subconta. O vendedor, porque **subconta pessoa física não
libera Pix**:

> A sua conta ainda não está totalmente aprovada para utilizar o Pix.

E isso apesar de `GET /myAccount/status` responder `APPROVED` em tudo —
`commercialInfo`, `bankAccountInfo`, `documentation`, `general`. Ela também não
deixa nem criar chave Pix. Com CNPJ o Pix libera na criação, e de quebra o campo
`site` é persistido (na subconta PF ele volta nulo, silenciosamente).

### A conta da plataforma precisa ser CNPJ

Conta pessoa física **não cria subconta**:

```
POST /accounts → 403
"Contas de pessoa física (CPF) não podem criar subcontas no Asaas.
 Apenas contas de pessoa jurídica (CNPJ) podem acessar essa funcionalidade."
```

Para homologação, crie a conta em [sandbox.asaas.com](https://sandbox.asaas.com/)
com um CNPJ gerado aleatoriamente — é artifício de ambiente de teste, sancionado
pelo próprio suporte do Asaas.

**Isto é limitação só do teste.** Em produção não há subconta no caminho: o
vendedor tem conta Asaas própria e independente, e apenas cola a chave dele no
Moodle. É o que atende a regra fiscal do projeto — a plataforma não emite nota
por outra empresa.

> Em produção, uma conta independente pode precisar de **liberação do gerente de
> contas do Asaas** para fazer split para uma carteira externa. Confirme isso
> antes de prometer prazo a um vendedor.

### Cadastre o domínio da plataforma na conta do vendedor

Em **Minha Conta → Informações**, campo do site: `https://courses.leodg.dev`.

Parece errado e não é. O Asaas exige que a URL de retorno use **o mesmo domínio
cadastrado na conta que emite a cobrança**:

> É necessário enviar uma URL que use o mesmo domínio cadastrado nas suas Minha
> Conta na aba Informações.

Como a cobrança nasce na conta do **vendedor** e o retorno aponta para a
**plataforma**, é o domínio da plataforma que precisa estar lá — não o site
próprio do vendedor, que é o que qualquer um cadastraria por instinto.

Sem isso o Asaas recusa **a cobrança inteira**, não só o retorno. Quem não puder
cadastrar o domínio desliga *Trazer o aluno de volta* nas configurações do
gateway e continua vendendo; o aluno é que volta pela fatura.

---

## Onde ficam as credenciais

Crie `.devcontainer/secrets/asaas-sandbox.env` na worktree (o diretório inteiro
está no `.gitignore`):

```bash
# Chaves de HOMOLOGACAO (prefixo aact_hmlg_). Nao servem em producao, e a base
# tambem muda. Painel: https://sandbox.asaas.com/
ASAAS_ENV=sandbox
ASAAS_BASE_URL=https://api-sandbox.asaas.com/v3

# Conta da PLATAFORMA - pessoa juridica, recebe a comissao via split.
ASAAS_PLATFORM_API_KEY='$aact_hmlg_...'
ASAAS_PLATFORM_WALLET_ID=

# Conta do VENDEDOR - subconta CNPJ criada pelo script, cria a cobranca e fica
# com o liquido. A apiKey so e devolvida UMA vez, na criacao.
ASAAS_SELLER_PJ_API_KEY=
ASAAS_SELLER_PJ_WALLET_ID=

# O mesmo segredo dos dois lados: no webhook do Asaas e no Moodle.
ASAAS_WEBHOOK_TOKEN=
```

Carregar num shell:

```bash
set -a && . .devcontainer/secrets/asaas-sandbox.env && set +a
```

---

## Caminho rápido: o script

Faz a prova inteira sem passar pelo Moodle — cria a subconta, o aluno, a
cobrança com split, confirma o pagamento e mostra os dois saldos.

```bash
set -a && . .devcontainer/secrets/asaas-sandbox.env && set +a
export MOODLE_SITE=https://courses.leodg.dev
python3 docs/data-validation/scripts/provar-split-asaas.py
```

Sem dependência externa — só a biblioteca padrão do Python.

Ele imprime a **chave do vendedor** no fim. O Asaas só devolve a `apiKey` na
criação da subconta — passando `ASAAS_SECRETS_FILE` apontando para o arquivo de
secrets, o script a anexa lá sozinho:

```bash
export ASAAS_SECRETS_FILE=.devcontainer/secrets/asaas-sandbox.env
```

É essa chave que você cola no Moodle para vincular a conta do vendedor.

Passando `ASAAS_WEBHOOK_TOKEN`, a subconta já nasce com o webhook cadastrado e
ativo — `POST /accounts` aceita `site` e `webhooks` no mesmo corpo.

---

## Caminho manual, com `curl`

Para quando você precisa ver exatamente onde parou.

### 1. Conferir a conta da plataforma

```bash
curl -s -H "access_token: $ASAAS_PLATFORM_API_KEY" \
  "$ASAAS_BASE_URL/myAccount" | python3 -m json.tool | head -20
```

Confira `"personType": "JURIDICA"`. Se vier `FISICA`, a criação de subconta vai
falhar com 403.

### 2. Descobrir a carteira da plataforma

```bash
curl -s -H "access_token: $ASAAS_PLATFORM_API_KEY" "$ASAAS_BASE_URL/wallets?limit=1"
```

O `data[0].id` é o `walletId` que recebe a comissão. É ele que vai na
configuração do Moodle.

### 3. Criar a subconta do vendedor

```bash
curl -s -X POST "$ASAAS_BASE_URL/accounts" \
  -H "access_token: $ASAAS_PLATFORM_API_KEY" -H "Content-Type: application/json" \
  -d '{
    "name":"Vendedor Teste",
    "email":"vendedor.teste@example.com",
    "cpfCnpj":"<CNPJ sintetico com digito valido>",
    "companyType":"MEI",
    "mobilePhone":"48999998888",
    "incomeValue":5000,
    "address":"Rua Teste","addressNumber":"100",
    "province":"Centro","postalCode":"88058400",
    "site":"https://courses.leodg.dev"
  }'
```

A resposta traz `walletId` e `apiKey`. **A `apiKey` vem uma vez só** — grave no
arquivo de secrets na hora.

### 4. Criar o aluno, com CPF

Use a chave **do vendedor** daqui em diante.

```bash
curl -s -X POST "$ASAAS_BASE_URL/customers" \
  -H "access_token: $ASAAS_SELLER_API_KEY" -H "Content-Type: application/json" \
  -d '{"name":"Aluno Teste","email":"aluno@example.com","cpfCnpj":"<CPF sintetico>"}'
```

O CPF é obrigatório. O Asaas cria o cliente sem ele, mas **recusa emitir
cobrança** de cliente sem documento — o erro só apareceria no passo seguinte.

### 5. A cobrança com split

```bash
curl -s -X POST "$ASAAS_BASE_URL/payments" \
  -H "access_token: $ASAAS_SELLER_API_KEY" -H "Content-Type: application/json" \
  -d "{
    \"customer\":\"<cus_...>\",
    \"billingType\":\"PIX\",
    \"value\":100.00,
    \"dueDate\":\"$(date -d '+3 days' +%Y-%m-%d)\",
    \"externalReference\":\"prova-split-1\",
    \"callback\":{\"successUrl\":\"https://courses.leodg.dev/payment/gateway/asaas/return.php?ref=prova-split-1\",\"autoRedirect\":true},
    \"split\":[{\"walletId\":\"$ASAAS_PLATFORM_WALLET_ID\",\"percentualValue\":25}]
  }"
```

Repare em `netValue`: o Asaas tira a própria taxa **antes** de dividir, então um
`percentualValue` de 25% incide sobre o líquido e não sobre os R$ 100.

> **A base de cálculo virou configuração.** Esta seção foi escrita quando o
> código só sabia mandar `percentualValue`, e é por isso que os números abaixo
> são 25% de R$ 97,52. Hoje `asaas_client::build_split()` manda `fixedValue`
> quando a base é o bruto e `percentualValue` quando é o líquido.
> Ver `docs/adr/0007-comissao-sobre-o-bruto.md` e a
> [prova das duas bases](#prova-das-duas-bases-2026-09-01).

Não recalcule a comissão no relatório — use o que o gateway devolveu.

### 6. Ver o QR Code

```bash
curl -s -H "access_token: $ASAAS_SELLER_API_KEY" \
  "$ASAAS_BASE_URL/payments/<pay_...>/pixQrCode"
```

No `payload` aparece o **nome do recebedor**. É a prova visível de quem o banco
do comprador enxerga como vendedor — e quem, portanto, emite a nota.

### 7. Pagar de verdade — e por que não com baixa manual

**`receiveInCash` não prova split.** A baixa manual diz que o dinheiro foi
recebido *fora* do Asaas, e dinheiro que não passou por ele não tem como ser
dividido. O split fica assim:

```
situacao ...... CANCELLED
valor ......... 24.38
```

Valor atribuído, split cancelado, saldos em zero. É o tipo de "prova" que passa
despercebida — foi exatamente assim que o `marketplace_fee` do Mercado Pago
pareceu funcionar sem transferir nada.

O sandbox também não liquida um Pix de verdade. O que funciona é **cartão
fictício**, que processa na hora:

```bash
curl -s -X POST "$ASAAS_BASE_URL/payments" \
  -H "access_token: $ASAAS_SELLER_API_KEY" -H "Content-Type: application/json" \
  -d "{
    \"customer\":\"<cus_...>\",
    \"billingType\":\"CREDIT_CARD\",
    \"value\":100.00,
    \"dueDate\":\"$(date +%Y-%m-%d)\",
    \"split\":[{\"walletId\":\"$ASAAS_PLATFORM_WALLET_ID\",\"percentualValue\":25}],
    \"creditCard\":{\"holderName\":\"Aluno Teste\",\"number\":\"5162306219378829\",\"expiryMonth\":\"05\",\"expiryYear\":\"2029\",\"ccv\":\"318\"},
    \"creditCardHolderInfo\":{\"name\":\"Aluno Teste\",\"email\":\"aluno@example.com\",\"cpfCnpj\":\"<CPF>\",\"postalCode\":\"88058400\",\"addressNumber\":\"100\",\"phone\":\"48999998888\"},
    \"remoteIp\":\"189.1.1.1\"
  }"
```

Status vira `CONFIRMED` na resposta.

### 8. Conferir o split

```bash
curl -s -H "access_token: $ASAAS_SELLER_API_KEY" \
  "$ASAAS_BASE_URL/payments/<pay_...>" | python3 -c \
  "import json,sys; print(json.dumps(json.load(sys.stdin).get('split'), indent=2))"
```

E os dois saldos:

```bash
for K in "$ASAAS_SELLER_API_KEY" "$ASAAS_PLATFORM_API_KEY"; do
  curl -s -H "access_token: $K" "$ASAAS_BASE_URL/finance/balance"; echo
done
```

### Prova das duas bases (2026-09-01)

Segunda execução do roteiro, agora com a base de cálculo configurável. Mesma
cobrança de R$ 100,00 a 25%, mesmo par de contas, **cartão fictício** nas duas —
o que iguala o `netValue` em R$ 97,52 e torna os números comparáveis.

O split foi montado por `asaas_client::build_split()`, e não à mão: o que
precisa ser provado é o código que vai para produção.

| Base | Campo enviado | Cobrança | Split | **A plataforma recebe** |
|---|---|---|---|---|
| bruto | `fixedValue: 25.00` | `CONFIRMED` | `AWAITING_CREDIT` | **R$ 25,00** |
| líquido | `percentualValue: 25` | `CONFIRMED` | `AWAITING_CREDIT` | **R$ 24,38** |

**R$ 0,62 de diferença por venda de R$ 100,00** — e é exatamente a fatia da taxa
do Asaas que a plataforma deixava de cobrar. O R$ 24,38 reproduz o número de
2026-08-27 na casa do centavo, o que confirma que a base líquida continua se
comportando como antes.

A conferência que importa foi feita **do lado que recebe**, e não do lado que
cobra:

```bash
curl -s -H "access_token: $ASAAS_PLATFORM_API_KEY" \
     -H "User-Agent: Moodle paygw_asaas" \
     "$ASAAS_BASE_URL/payments/splits/received?limit=10"
```

```
AWAITING_CREDIT  R$ 25.00   <- base bruta
AWAITING_CREDIT  R$ 24.38   <- base liquida
```

**O saldo das duas contas continua em R$ 0,00, e isso está certo.** Cartão
liquida em D+30 no sandbox, e `AWAITING_CREDIT` quer dizer literalmente
"esperando ser creditado". O que está provado é que o valor foi **atribuído à
outra conta**, com o número certo. Ver o split chegar a `DONE` com o saldo se
movendo continua pendente, e depende só de tempo.

> **Armadilha do `User-Agent`.** Sem o cabeçalho, a API responde
> `user_agent_not_informed` e recusa a requisição inteira. O `asaas_client` já
> manda (`User-Agent: Moodle paygw_asaas`); quem chamar por `curl` à mão precisa
> lembrar, ou vai depurar um 400 que não tem nada a ver com o corpo.

As situações do split andam assim:

```
PENDING → AWAITING_CREDIT → DONE
```

`CANCELLED` significa que a cobrança não passou pelo Asaas. `AWAITING_CREDIT`
é o esperado logo após o cartão: atribuído e na fila, aguardando a liquidação
do cartão para o saldo se mover. Os saldos ficam em zero até lá — isso é
normal, não é falha.

**Executado em 2026-08-27, e este é o resultado:**

```
cobranca ... pay_w1ijat3r74sx4nlp | CONFIRMED
bruto ...... 100.0 | liquido 97.52

SPLIT
  carteira ........ <carteira da plataforma>
  e a plataforma? . SIM
  situacao ........ AWAITING_CREDIT
  percentual ...... 25.0 %
  valor ........... 24.38
```

R$ 97,52 × 25% = **R$ 24,38** — o resultado de `percentualValue`, que era o que
o código mandava no dia desta prova. Hoje ele manda `fixedValue` calculado sobre
o bruto, e a mesma cobrança atribuiria **R$ 25,00**.

O que não mudou, e é o motivo de o relatório guardar o valor devolvido pelo
gateway em vez de recalcular: estorno parcial e split recusado alteram o número
depois da criação.

**É a primeira vez neste projeto que um split é visto sendo atribuído entre duas
contas distintas.**

---

## Configurar o webhook no painel

*Integrações → Webhooks → Adicionar Webhook.*

| Campo | Valor |
|---|---|
| Este Webhook ficará ativo? | **ligado** |
| Nome | `Moodle CoursesFree` |
| URL | `https://courses.leodg.dev/payment/gateway/asaas/webhook.php` |
| E-mail | o seu — é assim que você descobre uma fila travada |
| Versão da API | **3** |
| Token de autenticação | *Gerar Token*, e **copiar** |
| Tipo de envio | **Sequencial** |
| Fila de sincronização | **ligada** |

**Eventos — apenas dois:**

- `PAYMENT_RECEIVED`
- `PAYMENT_CONFIRMED`

Não existe `PAYMENT_RECEIVED_IN_CASH`. Existe o **status** `RECEIVED_IN_CASH`,
mas o **evento** correspondente não — a baixa manual chega como
`PAYMENT_RECEIVED`. A API é explícita: *"O evento [PAYMENT_RECEIVED_IN_CASH] é
inválido."*

O plugin responde `200 ignored` a tudo que não sejam esses dois, então marcar
mais é ruído. `PAYMENT_SPLIT_DONE` é útil para você conferir o split nos *Logs
de Webhooks*, mas o plugin não o trata.

**Token vazio derruba tudo.** Sem ele o Asaas não manda o header
`asaas-access-token`, e o `webhook.php` responde `401` a toda entrega. Com envio
sequencial, a fila trava no primeiro evento.

> Não confunda com **Integrações → Mecanismos de Segurança**, que valida dinheiro
> **saindo** da conta: transferência, Pix QR Code, pagar contas, recarga.
> Apontar aquele para o Moodle deixaria os saques do próprio vendedor reféns de
> o site estar no ar — três falhas e a operação é cancelada. Não encoste nisso.

---

## Configurar o Moodle

**Administração → Meios de pagamento → Asaas:**

| Campo | Valor |
|---|---|
| Ambiente | **Homologação** |
| Wallet ID da plataforma (Homologação) | o `walletId` do passo 2 |
| Token do webhook (Homologação) | o mesmo token do painel |
| Forma de pagamento | *Deixar o aluno escolher*, ou *Somente Pix* |
| Campo de perfil com o documento | nome curto de um campo com o CPF — **obrigatório** |

Homologação e produção têm campos separados de propósito: dá para deixar os dois
vinculados ao mesmo tempo e alternar num select, sem redigitar nada, e uma chave
de homologação não tem como ser usada em produção por engano.

**Antes de vincular qualquer conta**, a instalação precisa de chave de cifragem —
a chave do vendedor é gravada cifrada e o vínculo se recusa a salvar sem ela:

```bash
docker compose exec -T moodle php /var/www/html/admin/cli/generate_key.php < /dev/null
```

A chave mora em `moodledata/secret/`. **Inclua esse diretório no backup junto com
o banco**: se ela sumir, as credenciais de todos os vendedores ficam ilegíveis e
todo mundo precisa revincular. O `rsync --delete` do deploy não a toca — ele mira
`${VPS_PATH}/repo`, e o `moodledata` é irmão, não filho.

**Vincular a conta do vendedor:** painel da empresa → botão *Asaas* do país →
*Vincular conta* → colar a chave do vendedor. O formulário confere a chave contra
a API, descobre o `walletId` sozinho e cifra antes de gravar. A chave nunca mais
aparece na tela — só os seis últimos dígitos.

---

## Teste de ponta a ponta pelo Moodle

1. Empresa com conta de pagamento no país `BR` e o gateway Asaas vinculado.
2. Oferta publicada, em BRL, com preço.
3. Comissão da empresa em 25%.
4. Aluno com o CPF preenchido no perfil.
5. Comprar a oferta → escolher Asaas → cai na fatura do Asaas.
6. Confirmar a cobrança pelo painel (ou `receiveInCash`).
7. Conferir, nesta ordem:
   - **Logs de Webhooks** no Asaas: entrega com `200`
   - `{payments}` do core com uma linha `gateway = asaas`
   - `local_marketplace_sale` com a comissão e o id da transação
   - direito de acesso criado
   - **aluno matriculado no curso**
   - relatório da empresa mostrando a venda como `asaas`
8. Reenviar o mesmo webhook: tem que responder `ignored`, sem duplicar nada.

Pelo CLI:

```bash
docker compose exec -T moodle \
  php /var/www/html/public/local/marketplace/cli/status.php < /dev/null
```

---

## Armadilhas já descobertas

Todas encontradas contra a API real, e todas já tratadas no plugin.

| Sintoma | Causa |
|---|---|
| `403` ao criar subconta | Conta da plataforma é pessoa física. Só CNPJ cria subconta |
| *"Sua conta precisa estar aprovada"* ao usar Pix | A **subconta** é pessoa física. Crie com CNPJ — e isso apesar de `/myAccount/status` dizer `APPROVED` em tudo |
| Split sai `CANCELLED` com o valor certo | O pagamento foi baixa manual (`receiveInCash`). Dinheiro que não passou pelo Asaas não tem como ser dividido — e a plataforma **não recebe nada**. O relatório mostra R$ 0,00, que é a verdade. Ver [ADR-0003](../adr/0003-quem-cria-a-cobranca-emite-a-nota.md) |
| `site` volta nulo depois de criar a subconta | Subconta pessoa física não persiste o campo. Com CNPJ ele é gravado |
| *"Não é permitido split para sua própria carteira"* | Vendedor e plataforma são a mesma conta. O vínculo barra isso antes |
| *"É necessário preencher o CPF ou CNPJ do cliente"* | O Asaas cria cliente sem documento mas recusa a cobrança dele |
| *"É necessário enviar uma URL que use o mesmo domínio…"* | O domínio da plataforma não está cadastrado na conta do vendedor |
| *"O evento [PAYMENT_RECEIVED_IN_CASH] é inválido"* | Nome de evento não é valor de status. Use `PAYMENT_RECEIVED` |
| Webhook responde `401` em toda entrega | Token vazio de um dos lados, ou divergente |
| Comissão do relatório diverge do extrato | Não recalcule: guarde o valor devolvido pelo gateway, que muda com estorno e split recusado |
| Comissão menor que o combinado | Split indo como `percentualValue`, que o Asaas aplica ao `netValue`. Tem que ser `fixedValue` |
| Vínculo recusa salvar | Instalação sem chave de cifragem. Rode `generate_key.php` |

---

## Provar em produção: o que a conta precisa ter

Levantado em 14/09/2026, quando a prova de Pix com dinheiro real foi montada.
Nada aqui é opinião — é o estado que a API devolveu.

**Duas contas Asaas distintas, e de verdade.** O split para a própria carteira é
recusado, e é o mesmo erro que o Mercado Pago cometeu em silêncio na primeira
tentativa deste projeto. Com uma conta só não há prova possível.

**A segunda conta não pode ser subconta, se a primeira for pessoa física.** O
Asaas recusa com *"Contas de pessoa física (CPF) não podem criar subcontas"*. O
truque que funcionou em homologação não serve em produção quando a conta é PF —
precisa ser uma conta independente, tipicamente a CNPJ da plataforma.

**Confira o `bankAccountInfo` antes de cobrar.** `GET /myAccount/status`
devolve quatro campos, e os quatro importam:

```json
{
  "commercialInfo": "APPROVED",
  "bankAccountInfo": "PENDING",
  "documentation": "APPROVED",
  "general": "APPROVED"
}
```

Com `bankAccountInfo` pendente o dinheiro entra e não sai. A chave Pix pode
estar `ACTIVE` e o recebimento funcionar — o que trava é o saque.

**Confira a chave Pix:** `GET /pix/addressKeys`. Uma chave `EVP` com status
`ACTIVE` basta para receber.

O webhook de produção usa o mesmo caminho do de homologação, com o header
`asaas-access-token`, e os eventos continuam sendo só `PAYMENT_RECEIVED` e
`PAYMENT_CONFIRMED`.

## O que continua sem prova

- **A liquidação do split.** Ele foi visto sendo *atribuído* — `AWAITING_CREDIT`,
  R$ 24,38 na carteira da plataforma. Falta vê-lo chegar a `DONE` e o saldo se
  mover, o que depende da liquidação do cartão.
- **Pix pago de verdade.** A cobrança Pix com split é criada sem problema, mas o
  sandbox não liquida Pix — a prova saiu por cartão fictício.
- **O caminho pelo Moodle com split**, de ponta a ponta. A cadeia até a matrícula
  já foi provada, mas com comissão zero.
- **Estorno.** `PAYMENT_REFUNDED` e `PAYMENT_RECEIVED_IN_CASH_UNDONE` não revogam
  acesso: revogação é decisão de negócio, tomada em `entitlement::revoke()`, e
  nunca por automação. Se isso mudar, é decisão explícita.
