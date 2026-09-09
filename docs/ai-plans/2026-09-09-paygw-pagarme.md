> **Situação:** inacabado · **Início:** 2026-09-09
> **Origem:** `~/.claude/plans/tranquil-conjuring-quiche.md` — o corpo do plano aprovado está preservado na seção "O plano aprovado, e onde ele foi desviado".
> **Resultado:** o plugin está completo e verde, mas **o split nunca foi exercitado**: a conta de homologação do Pagar.me recusa criar recebedor e não processa nenhuma forma de pagamento. Falta a liberação comercial, pedida ao suporte.

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

## Verificação

| Prova | Resultado |
|---|---|
| PHPUnit `paygw_pagarme_testsuite` | **104 testes, 188 asserções, OK** |
| PHPUnit `local_marketplace_testsuite` | **129 testes, 392 asserções, OK** |
| phpcs `--standard=moodle` | **saída vazia** em 31 arquivos |
| Behat `--profile=chrome` | **6 cenários, 45 passos**, 3 deles `@javascript` |
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

O chamado ao suporte está escrito, com a evidência e os `charge_id`, em
`../data-validation/pagarme-sandbox.md`. São **dois pedidos**, e atender só um
não destrava:

1. habilitar criação de recebedores (split / PSP);
2. habilitar o processamento em ambiente de teste.

### Para a próxima sessão, quando a conta abrir

O roteiro traz a lista em ordem. O resumo:

1. Rodar `provar-split-pagarme.py` inteiro — ele responde sete perguntas de uma
   vez e aborta se os recebedores forem o mesmo.
2. Trocar as fixtures de `tests/fixtures/documented_responses.php` pelas
   respostas **medidas**. Todo teste que mudar de resultado aponta onde a
   documentação mentia.
3. Conferir, nesta ordem, o que está marcado `NAO MEDIDO` no código:
   - sobre o que o `percentage` incide — bruto ou líquido;
   - se as regras de split precisam somar 100%;
   - se o split vale em **cada ciclo** da assinatura;
   - se o estorno reverte o split, e para quais formas;
   - se estornar um ciclo cancela a assinatura;
   - se `overpaid` existe mesmo, e se boleto estorna.
4. Prova de ponta a ponta pelo Moodle, com o túnel: compra pela vitrine,
   webhook chegando sozinho, `local_marketplace_sale`, direito e matrícula;
   reenvio respondendo `ignored`; reconciliação achando a pendente órfã;
   estorno revogando acesso.
5. O ciclo da assinatura, com o roteiro do Asaas como espelho: cobrança de
   vencimento mais próximo, ciclo 2 adotado pelo webhook, renovação que **soma**,
   corte por diferença, e volta ao pagar a atrasada.

### Decidido e não feito

- **ADR do recebedor por vendedor.** O próximo número é `0011`. Vale a pena
  porque o `rp_` diferente em cada vendedor é consequência de modelagem que
  ninguém adivinha lendo o código. Fica para quando o desenho estiver provado —
  ADR sobre suposição envelhece mal.
- **Allowlist de IP da VPS** no painel do Pagar.me, antes do primeiro repasse.
- **Roteiro `pagarme-assinatura.md`**, irmão do `asaas-assinatura.md`. Não
  nasceu porque não há ciclo para descrever ainda.

### Fora de escopo, e continua

- Cifrar os tokens do `paygw_mercadopago`, hoje em texto puro. O plugin está em
  produção e funcionando.
- Vendedor pessoa jurídica no Mercado Pago.
- Pix e boleto liquidando de verdade — nenhum sandbox do projeto liquida.

---

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
