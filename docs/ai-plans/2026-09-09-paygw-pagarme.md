> **Situação:** executado · **Início:** 2026-09-09 · **Fim:** 2026-09-14
> **Origem:** `~/.claude/plans/tranquil-conjuring-quiche.md` — o corpo do plano aprovado está preservado na seção "O plano aprovado, e onde ele foi desviado".
> **Resultado:** o plugin está completo e verde, e **o split foi provado — em homologação e em produção, com dinheiro real**. O que não existe é assinatura com split: o Pagar.me recusa o campo, e por isso o gateway recusa oferta recorrente na porta. A conclusão do ciclo é que **o Asaas continua sendo o único gateway que atende o modelo completo**.

# `paygw_pagarme` — o terceiro gateway, escrito antes da prova

## Contexto

O projeto tem dois gateways funcionando. O `paygw_mercadopago` está em produção
sem recorrência e sem cobertura na camada HTTP; o `paygw_asaas` é o molde
completo, com split provado em 27/08 e o ciclo da assinatura provado em 09/09.

O Pagar.me estava parado desde 2026-08-27, esperando CNPJ para abrir conta. A
conta de sandbox saiu, e a Fase 2 do plano original pôde finalmente rodar.

O método desta sessão foi imposto pelo que a anterior descobriu: **duas APIs
deste projeto aceitaram um campo de dinheiro e o descartaram em silêncio**, as
duas respondendo `2xx` — o `marketplace_fee` no `preapproval` do Mercado Pago e
o `creditCard` no `PUT /subscriptions` do Asaas. Daí a ordem: medir a API,
escrever o resultado, e só então codar.

A medição travou. O que aconteceu depois foi decisão do usuário, e está na
seção seguinte.

---

## Decisões

| Decisão | Escolha | Alternativa recusada |
|---|---|---|
| Ordem de trabalho | medir antes de codar | ir direto ao código, como nas sessões que produziram o ADR-0001 errado |
| Quando a medição travou | **implementar pela documentação**, marcando cada suposição | esperar a liberação; recusada pelo usuário para não ficar parado |
| Escopo | gateway **completo** — Pix, boleto, cartão, assinatura, estorno, troca de cartão | começar por cobrança avulsa; recusada, o usuário pediu paridade com os dois existentes |
| Pix | página própria com QR Code e polling | Link de Pagamento hospedado, que não faz split em Pix |
| Recebedor da plataforma | na **conta de pagamento**, cifrado junto com a chave | nas settings do site, como o `platformwalletid` do Asaas — impossível: um `recipient` é objeto interno a uma conta |
| Base da comissão | `gross` vira `flat` em centavos; `net` vira `percentage` | mandar sempre `percentage`; recusada porque não se sabe sobre o que ele incide |
| Worktree | nova, a partir de `origin/dev` | reaproveitar a `paygw-pagarme` de 27/08, anterior ao Asaas |
| Túnel | `pagarme.leodg.dev`, hostname próprio | seguir no `mp.leodg.dev`; o usuário pediu a troca depois de o reaproveitamento já estar funcionando |

---

## O que mudou

**Plugin novo** — `public/payment/gateway/pagarme/`, 33 arquivos:

| Arquivo | Papel |
|---|---|
| `classes/pagarme_client.php` | todo o HTTP; costura `make_curl()`; `build_split()`, `charge_verdict()` e `commission_from()` como estáticas puras |
| `classes/credentials.php` | chave do vendedor cifrada, por ambiente, mais os dois `rp_` |
| `classes/gateway.php` | os seis métodos do contrato — nenhum caindo no padrão |
| `classes/payment_processor.php` | cobrança, assinatura, webhook, estorno, adoção de ciclo |
| `classes/external/{create_charge,charge_status,submit_card}.php` | os três serviços AJAX |
| `classes/task/reconcile.php` | varredura horária de `pending` e `processing` |
| `pix.php` · `card.php` · `return.php` · `webhook.php` · `link.php` · `unlink.php` | as páginas |
| `amd/src/{gateways_modal,repository,pixpoll,cardform}.js` | AMD à mão, sem transpilador |
| `lang/{en,pt_br,es}` | 95 chaves, idênticas nos três, em ordem alfabética |
| `tests/` | 104 testes, 6 cenários behat, fixtures com as respostas documentadas |

**Documentação:**

- `docs/data-validation/pagarme-sandbox.md` — **novo**, o roteiro e o texto do chamado ao suporte
- `docs/data-validation/scripts/provar-split-pagarme.py` — **novo**, stdlib, aborta no passo 0 de propósito
- `docs/data-validation/README.md`, `docs/README.md`, `docs/data-model/marketplace.md`, `docs/architecture/estado-e-proximas-fases.md` — todos com o Pagar.me
- `.github/moodle-plugins.txt` — o CI passa a validar dez plugins

**Ambiente:** worktree `paygw-pagarme` recriada de `origin/dev` (offset 1, porta
8453), a antiga removida, `config-local.php` com `wwwroot` e `sslproxy`.

---

## Descobertas

### O host de homologação não existe

O plano original mandava usar `https://sdx-api.pagar.me/core/v5`. Ele responde
`404 no Route matched with those values`. **Há um endereço só**, e o que separa
homologação de produção é o **prefixo da chave** (`sk_test_` × `sk_`).

Consequência no código: `BASE_URL` é constante, e o ambiente sai de uma função
sobre o prefixo — não de um mapa por ambiente como no `asaas_client`.

### O `200` do `POST /orders` não vale como sinal

Mandei uma order com `split[]` apontando para dois `recipient_id`
**inventados**. Voltou `HTTP 200`, corpo completo, cliente criado, item ativo.
Só o `GET` denunciou:

```
order.status ..... failed
charge.status .... failed
gateway .......... 404 Recipient not found
charge.splits .... null
```

É a terceira ocorrência do padrão que motivou a sessão, e a comparação importa:

| Gateway | O que fez com o campo de dinheiro |
|---|---|
| Mercado Pago, `preapproval` | aceitou, `201`, **descartou em silêncio** — o `GET` não denuncia |
| Asaas, `PUT /subscriptions` | aceitou, `2xx`, **não guardou** — o `GET` não denuncia |
| Pagar.me, `POST /orders` | aceitou, `200`, **e o `GET` denuncia** |

O Pagar.me é o menos perigoso dos três, desde que alguém leia de volta. Daí a
regra no `payment_processor`: nunca olhar o status HTTP; ler `charge.status`,
`gateway_response.code` e `charge.splits`, e tratar `splits` nulo como comissão
**zero**, jamais como "o gateway não informou".

### Split é contrato comercial, não interruptor

`POST /recipients` recusa com `412 action_forbidden — This company it not
allowed to create a recipient`. Testado em duas contas, nos dois formatos de
payload documentados, com CPF e CNPJ, `individual` e `company`: os oito casos
recusam igual.

A documentação explica: *"Esta funcionalidade está disponível apenas para
clientes PSP"*. A segunda conta foi criada declaradamente com a permissão e
respondeu idêntico — o que descarta configuração e aponta para análise
comercial pendente do lado deles.

### O adquirente é um bloqueio separado

Pix, cartão e boleto voltam `500 internal_error | Erro desconhecido no proxy`.
São **dois bloqueios independentes**: liberar recebedores sem liberar o
adquirente não destrava a medição, porque sem cobrança que processa não há o
que dividir.

Uma cobrança de cartão ficou em `processing` e **nunca saiu de lá**. Pendência
que não resolve é pior que recusa — foi o que fez a reconciliação passar a
varrer `processing` além de `pending`.

### Detalhes que só aparecem lendo

- `items[].code` é **obrigatório**, e a falta dele não vira erro `4xx`: vira
  cobrança `failed` dentro de um `200`.
- `type` do recebedor aceita `individual` ou `company`. `corporation`, que o
  plano original sugeria, é recusado — e a mensagem só aparece depois de o
  `holder_type` já estar certo, então dá para gastar duas rodadas achando que o
  erro é outro.
- O `recipient_id` tem prefixo `rp_`, não `re_` como dizia o plano.
- O webhook autentica por **HTTP Basic**, não por header próprio.
- Nas respostas documentadas o `payment_method` volta como `"Pix"`, com
  maiúscula, onde a requisição manda `pix`. E o status da cobrança (`pending`)
  não é o da transação (`waiting_payment`) — ler o campo errado faria o Pix
  parecer recusado.
- `GET /transfers` responde `401 "IP de origem não autorizado"`. Há allowlist
  de IP, e o da VPS vai precisar entrar nela **antes** do primeiro repasse.

### O `wwwroot` era a causa das entregas falhadas

O painel registrava dez entregas de webhook como `failed`, e a causa não era o
endpoint: o Moodle recebia `Host: mp.leodg.dev`, comparava com o `wwwroot`
interno e respondia `303` para `localhost:8453`. A requisição nem entrava no
código. O teste que prova o conserto é o webhook do Asaas passar de `303` para
**`401`** — chegar e ser recusado é o resultado bom.

### Meus erros nesta sessão

**Reportei que o túnel não estava configurado** porque olhei `~/.cloudflared/`.
Ele é token-based, em `/etc/cloudflared/token`, e estava no ar o tempo todo. O
usuário estava certo ao chamá-lo de "já funcionando".

**Escrevi o behat supondo a navegação** em vez de ler o `settings.feature` do
Mercado Pago, que era o modelo pronto. Cinco cenários falharam por três coisas
que estavam escritas ali: a seção se alcança por URL direta, o gateway precisa
estar em `paygw_plugins_sortorder`, e o clique é no gateway dentro da linha da
conta.

**Escrevi um cenário de vínculo que falhava** por um motivo que eu não tinha
previsto: o site do behat nasce sem chave de cifragem, então o `link.php`
mostra a guarda e nunca chega ao formulário. O comportamento estava certo; o
cenário é que estava errado. Virou cenário próprio.

**Entendi "corrigir depois" como "não corrigir agora"** e parei com bugs
conhecidos no código. O usuário esclareceu que aquilo valia só para o que
depende da integração — e a varredura seguinte achou quatro bugs de lógica e um
dado fabricado.

---

## O que a liberação da conta mudou — 11 a 14/09

O plano foi escrito supondo que a conta ficaria bloqueada. Ela destravou em
etapas, e cada etapa derrubou algo do código.

### 11/09 — recebedores liberados, e o split provado

A conta `acc_Xv3ne2OsOXCJd4GB` passou a criar recebedores. Com dois distintos,
uma cobrança de cartão de R$ 100,00 com split de 25% foi paga e o extrato se
moveu: R$ 25,00 exatos para a plataforma, R$ 70,51 para o vendedor depois dos
R$ 4,49 de taxa.

**Duas suposições caíram, e as duas estavam no código:**

**O `charge.splits` volta `null` mesmo quando o split acontece.** O
`commission_from()` lia dali e gravaria comissão zero em toda venda. A verdade
está em `GET /payables?recipient_id=` — virou o
[ADR-0011](../adr/0011-o-extrato-e-a-fonte-da-comissao.md).

**O `percentage` incide sobre o bruto**, não sobre o líquido — o oposto do
Asaas. Isso tornou a base `net` inatendível, o ramo saiu do `build_split()` e o
`applied_base()` passou a devolver sempre `gross`.

### 11/09 — assinatura com split: não existe

`POST /subscriptions` com `split` recusa em **quatro formatos**. Dentro de
`items[]` o `200` volta com o campo descartado e nenhum payable nasce. E o
`PATCH …/split` responde `412 "Can't update the split on subscription because
doesn't has split"` — a capacidade existe, mas a assinatura teria que nascer
com ela.

Segundo impedimento, independente: o filtro `?subscription_id=` de
`GET /charges` é **ignorado**, e as cobranças não carregam vínculo com a
assinatura. Sem isso não há como listar as cobranças de uma assinatura.

**Consequência:** `supports_recurring()` devolve `false` e o `start_payment`
recusa na porta. Cobrar recorrência sem split renderia comissão zero em
silêncio.

### 11/09 — a prova de ponta a ponta achou três bugs

Nenhum apareceria com dublê, porque todos dependem de a cobrança nascer paga ou
de o fluxo real ter etapas.

1. **A guarda de replay impedia a entrega no cartão.** O cartão liquida na
   criação, então a linha já nascia `paid` — e o webhook concluía "já estava
   pago" e ia embora sem entregar. O aluno pagava e não recebia nada.
2. **A recusa da recorrência estava no lugar errado**, depois do ponto em que o
   cartão desvia para a página de tokenização. A oferta recorrente passava e
   deixava linha órfã.
3. **Telefone é obrigatório**, e eu tinha acabado de fazer o campo ser omitido.

E uma lacuna de desenho: **cartão tokenizado exige endereço de cobrança**, que
não vai no token e não podia sair do perfil do Moodle, que não tem CEP. A página
do cartão passou a coletá-lo.

### 14/09 — produção, com Pix e dinheiro real

Pix de R$ 5,00 pago de verdade, dividido 99/1 para o dinheiro voltar a quem
pagou o teste:

```
DG Tecnologia  bruto R$ 4,95 | taxa R$ 0,05 | liquido R$ 4,90
IVANA          bruto R$ 0,05 | taxa R$ 0,00 | liquido R$ 0,05
```

Confirmou fora do sandbox que o percentual incide sobre o bruto, que a
responsabilidade da taxa funciona como declarada — foi invertida nesta rodada e
a plataforma pagou —, e que o `charge.splits` volta `null` com dinheiro real.

### 14/09 — e o Asaas foi provado com Pix, o que fechou a decisão

Com duas contas de produção distintas, um Pix de R$ 5,00 liquidou com
`split status DONE`. **O Pix do Asaas nunca tinha sido visto liquidar**, nem em
homologação, onde o sandbox não liquida e a baixa manual cancela o split.

O extrato revelou o que a cobrança escondia:

```
+5,00  PAYMENT_RECEIVED
-1,99  PAYMENT_FEE (Pix)
-1,25  INTERNAL_TRANSFER_DEBIT (a comissao)
-0,99  PAYMENT_MESSAGING_NOTIFICATION_FEE
```

A **taxa de mensageria não entra no `netValue`**: ele dizia R$ 3,01 e o vendedor
ficou com R$ 0,77. Eu estimei a taxa errada antes de ler o extrato. Virou o
PR #101, que desliga a notificação na criação do cliente.

### O que isso decidiu sobre o produto

Com tudo medido, o quadro é:

| | Pix real | Split real | Split em assinatura |
|---|---|---|---|
| **Asaas** | sim | sim, `DONE` | **sim** |
| Mercado Pago | sim | sim | impossível |
| Pagar.me | sim | sim | recusado |

**O Asaas é o gateway do produto**, porque assinatura é o coração do modelo. O
Pagar.me entra como alternativa para venda avulsa — completo, verde, e com
recorrência recusada explicitamente.

Isso não torna o ciclo um desperdício: o ADR-0011, a guarda de replay e o
telefone obrigatório são lições que valem para o projeto inteiro.

## Verificação

| Prova | Resultado |
|---|---|
| PHPUnit `paygw_pagarme_testsuite` | **116 testes, 207 asserções, OK** |
| PHPUnit `local_marketplace_testsuite` | **138 testes, 500 asserções, OK** |
| PHPUnit `paygw_asaas_testsuite` | **69 testes, 157 asserções, OK** |
| phpcs `--standard=moodle` | **saída vazia** em 31 arquivos |
| Behat `--profile=chrome` | **6 cenários, 45 passos**, 3 deles `@javascript` |
| Split, homologação | R$ 100,00 → R$ 25,00 exatos no extrato da plataforma |
| Split, **produção, dinheiro real** | R$ 5,00 → R$ 4,90 e R$ 0,05, taxa saindo de quem foi declarado |
| Upgrade | limpo, plugin instalado |
| AMD | os 8 arquivos parseiam, zero ES6 |
| Webhook pelo túnel | `401` sem credencial, `401` com credencial errada |

**Bugs achados pelos testes, não pela leitura:**

1. No ramo `net` do `build_split` faltava a guarda do resto do vendedor:
   comissão de 100% gerava regra de valor zero, que a API recusa. O ramo
   `gross` já guardava.
2. `process_notification('')` procurava linha por `chargeid` vazio — e o fluxo
   de cartão cria a linha antes de existir cobrança, então acharia a linha de
   **outro aluno**. O `charge_status`, chamado pela página de polling, passava
   exatamente isso.
3. A página do Pix recebia a URL da página em vez da **imagem** do QR Code.
4. A reconciliação não alcançava `processing`.
5. `build_customer` mandava o telefone `999999999` fixo para todo comprador.

**O que continua sem prova, e não por falta de tentativa:** o split. Nenhuma
cobrança processou, então nada do que envolve dinheiro foi exercitado.

---

## Em aberto

### Bloqueado no Pagar.me — não é trabalho de código

Duas habilitações de conta, e o texto do chamado está pronto em
[`../data-validation/pagarme-sandbox.md`](../data-validation/pagarme-sandbox.md):

1. **Pix em homologação.** Cartão e boleto processam; só o Pix responde
   `400 action_forbidden — "Sem ambiente configurado para este tipo de
   transação"`. Em **produção** o Pix funciona, o que prova que não é limitação
   da API nem do código.
2. **Split em assinatura.** Se liberar, o gateway ganha recorrência sem
   trabalho novo — a estrutura já está escrita e só o
   `supports_recurring()` precisa mudar.

### Decidido e não feito

- **Allowlist de IP da VPS** no painel do Pagar.me, antes do primeiro repasse.
  `GET /transfers` responde `401 "IP de origem não autorizado"`.
- **Roteiro `pagarme-assinatura.md`** não nasce: não há ciclo para descrever
  enquanto o gateway não aceitar split em assinatura. Escrever roteiro de algo
  que não existe é pior que não ter.
- **A reconciliação varre cobrança que nunca será paga.** Pix pendente não
  cancela (`412 "cannot be canceled because is pending"`) e o status não vira
  "expirado" — três dias depois, cobranças de teste seguiam `pending`. A tarefa
  as consulta por até 30 dias. É desperdício de chamada, não dano.

### Fora de escopo, e continua

- Cifrar os tokens do `paygw_mercadopago`, hoje em texto puro. O plugin está em
  produção e funcionando.
- Vendedor pessoa jurídica no Mercado Pago.
- **Débito automático de ponta a ponta no Asaas**: ninguém viu o segundo mês
  ser cobrado sozinho.

## O plano aprovado, e onde ele foi desviado

O plano original desta sessão tinha um **portão** explícito: *"A Fase 2 só
começa depois de o roteiro ter, escrito, o `GET` que mostra o split com valor e
os dois extratos batendo."*

O portão não passou, e a Fase 2 rodou assim mesmo — por decisão do usuário,
tomada depois de eu apresentar as alternativas. O registro fica porque a
consequência é real: **o gateway inteiro é uma hipótese testável, não um
resultado**. Cada suposição está marcada no código, listada no README do plugin
e ordenada no roteiro.

Se a medição desmentir a base do percentual, muda `build_split()`. Se desmentir
o estorno, muda `refund_blocker()`. O código foi escrito para que essas duas
mudanças sejam locais — e é por isso que a montagem do corpo e o cálculo da
comissão são estáticas puras, testáveis sem rede.
