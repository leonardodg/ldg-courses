# payment/gateway/pagarme

[Voltar ao índice](README.md)

## 1. Idempotência sem lock e sem índice único no banco
- **Status:** corrigido
- **Arquivo:** `classes/payment_processor.php:481`
- **Achado:** check-then-act sem lock; `db/install.xml:41` declara o índice
  `chargeid` como `UNIQUE="false"`, então nada em nenhuma camada impede
  duplicata concorrente.
- **Cenário de falha:** `webhook.php` e o `reconcile.php` horário (ou o
  poller AJAX em `charge_status.php`) chamam `process_notification()` para o
  mesmo `chargeid` quase simultaneamente. Ambas as leituras veem
  `paymentid=NULL` antes de qualquer escrita commitar — uma cobrança real
  entregue e registrada duas vezes (matrícula duplicada, comissão duplicada,
  linha `core_payment` duplicada).
- **Correção:** mesmo padrão dos outros dois gateways — `SELECT ... FOR UPDATE`
  dentro de `start_delegated_transaction()`, travando por `chargeid` (ou pela
  linha mais recente da assinatura, no caso de ciclo ainda sem linha própria).
  Commit logo após reservar `paymentid`, antes de `record_sale()`/`deliver_order()`.

## 2. Charge órfã fora da rede de segurança de webhook/reconcile
- **Status:** corrigido
- **Arquivo:** `classes/payment_processor.php:267`
- **Achado:** `create_charge_for()` só lança exceção quando
  `charge_verdict()` reporta `status==='failed'`; uma resposta de order com
  array `charges` ausente/vazio deixa `chargeid=''` e `status='pending'` sem
  lançar exceção, e o sweep do `reconcile.php` exige `chargeid <> ''`, então
  essa linha nunca pode ser reconciliada.
- **Cenário de falha:** a resposta da order vem sem `charges[0]` (documentado
  nos próprios comentários do arquivo como acontecendo com recebedor
  inválido). A linha fica travada silenciosamente em `status='pending'`,
  `chargeid=''` para sempre — órfã fora da rede de segurança do webhook e da
  reconciliação, sem alerta a ninguém de que a venda nunca completou de fato
  no gateway.
- **Correção:** nova checagem `if (!$charge) { throw ... }` logo após o
  `update_record()` que preserva o rastro, antes de chamar `charge_verdict()`.
  Nova string `errorchargemissing` em `en`/`pt_br`/`es`.

## 3. `get_default_recipient()` engole exceções e permite link com split vazio
- **Status:** corrigido
- **Arquivo:** `link.php:94`
- **Achado:** engole todos os `Throwable` e retorna `[]`, então uma tentativa
  de vínculo pode "ter sucesso" com `sellerrecipient` gravado como `''` —
  depois disso `build_split()` omite o split silenciosamente em toda cobrança
  futura, sem nada logando ou avisando.
- **Cenário de falha:** uma falha transitória de API, ou uma conta de
  vendedor cujo recebedor padrão ainda não está totalmente provisionado no
  momento do vínculo, resulta num `sellerrecipient` vazio salvo como link
  "bem-sucedido". Toda cobrança seguinte nessa conta sai com o valor cheio
  para o vendedor e split zero — invisível em logs ou na UI do admin.
- **Correção:** o design de tolerar recebedor ausente no link é intencional
  (docblock de `get_default_recipient()`: conta recém-criada responde 412, e
  isso é pendência do vendedor, não erro do plugin), então a correção não
  bloqueia o vínculo — em vez disso, avisa: quando `sellerrecipient` vem
  vazio, o redirect final usa `NOTIFY_WARNING` com a nova string
  `linkdonenosplit`, deixando explícito que o split não vai funcionar até o
  vendedor terminar o cadastro no Pagar.me e o admin vincular de novo.

## 4. Guarda de dupla submissão sem lock de DB
- **Status:** corrigido
- **Arquivo:** `classes/external/submit_card.php:99`
- **Achado:** a guarda `if (!empty($record->chargeid))` é um simples
  ler-então-ramificar, sem lock de banco ao redor da chamada subsequente a
  `create_charge_for()`.
- **Cenário de falha:** `cardform.js` desabilita o botão no clique, mas só
  protege contra duplo clique na mesma aba. Um retry de rede (proxy
  reenviando, aba duplicada, requisição reenviada após resposta perdida) pode
  passar pela checagem de `chargeid` vazio duas vezes antes de qualquer
  `create_charge_for()` gravar de volta o `chargeid` — dois cobranças de
  cartão reais para uma única compra.
- **Correção:** `SELECT ... FOR UPDATE` dentro de `start_delegated_transaction()`
  ao redor de toda a checagem + `create_charge_for()`. Mesmo com uma chamada
  HTTP lenta no meio (aceitável aqui: ação disparada pelo usuário, não
  webhook em massa), a segunda submissão espera a primeira terminar e
  encontra `chargeid` já preenchido.

## 5. Guarda de isenção da conta da plataforma duplicada nos três gateways
- **Status:** corrigido (com nota)
- **Arquivo:** `link.php:106`
- **Achado:** a guarda de isenção da conta da plataforma no split está
  copiada verbatim em todos os três plugins de gateway (`pagarme/link.php`,
  `asaas/link.php`, e o equivalente do mercadopago) em vez de viver uma vez
  em `local_marketplace` ou trait compartilhada.
- **Cenário de falha:** uma mudança futura na regra de isenção da conta da
  plataforma é aplicada em um `link.php` mas as outras duas cópias são
  esquecidas, reintroduzindo silenciosamente o bug de rejeição
  mesmo-recebedor/mesma-carteira no fluxo de assinatura SaaS da plataforma em
  qualquer gateway não atualizado.
- **Correção:** não há neste projeto um plugin de gateway compartilhado onde
  a regra pudesse morar uma vez só (cada gateway é standalone por desenho, e
  mercadopago nem tem esse conceito de recebedor/carteira). Adicionado
  comentário cruzado em `pagarme/link.php` e `asaas/link.php` apontando
  explicitamente um para o outro. Duplicação de 1 linha, estável, aceita como
  trade-off.

## 6. Fallback de comissão 25% hardcoded triplicado
- **Status:** corrigido (com nota)
- **Arquivo:** `classes/payment_processor.php:119`
- **Achado:** o fallback hardcoded `$feepercent = 25.0` está duplicado
  verbatim em `paygw_asaas` e `paygw_mercadopago` — três cópias
  independentes do que `dev/CLAUDE.md` trata como um único padrão de fábrica
  do site.
- **Cenário de falha:** se a comissão padrão de fábrica da plataforma for
  revisada, atualizar o literal em um gateway e esquecer os outros dois
  produz taxa de comissão padrão inconsistente dependendo de qual gateway
  processa um componente que não é ciente do marketplace.
- **Correção:** o literal só é usado quando `local_marketplace` não está
  instalado (`class_exists()` falso), caso em que
  `local_marketplace\api::default_commission_percent()` nem existe para ser
  chamado — não há como ter uma única fonte de verdade sem introduzir uma
  nova dependência compartilhada. Em vez disso, o literal virou constante
  nomeada `DEFAULT_COMMISSION_PERCENT` em cada um dos três `payment_processor.php`,
  com docblock cruzando os três arquivos — mais legível que o número mágico
  e explícito sobre a duplicação, mesmo não eliminando-a.

## Verificação

```
phpcs --standard=moodle -p --report=summary public/payment/gateway/pagarme public/payment/gateway/asaas public/payment/gateway/mercadopago   # limpo
php vendor/bin/phpunit --testsuite paygw_pagarme_testsuite,paygw_asaas_testsuite,paygw_mercadopago_testsuite                                 # OK (315 tests, 678 assertions)
```

Sem mudança de schema neste plugin (mesmo motivo do asaas: `chargeid` nasce
vazio, não `NULL`, e um índice único colidiria entre cobranças pendentes).
