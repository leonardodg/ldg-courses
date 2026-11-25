> **Situação:** inacabado · **Início:** 2026-08-27
> **Origem:** `~/.claude/plans/vast-splashing-pascal.md` — copiado literalmente, sem edição do corpo.
> **Resultado:** Fase 0 e Fase 1 entregues (PRs #53 a #59). **A Fase 2, `paygw_pagarme`, rodou em 09/09/2026** — ver [2026-09-09-paygw-pagarme.md](2026-09-09-paygw-pagarme.md). Três coisas escritas aqui se mostraram falsas ao medir: o host `sdx-api.pagar.me` **não existe**, o prefixo do recebedor é `rp_` e não `re_`, e o `type` dele aceita `individual`/`company`, não `corporation`. Os dois itens "Em aberto" do fim são do `paygw_mercadopago`. O registro do ciclo é o [2026-08-27-gateways-asaas-e-pagarme.md](2026-08-27-gateways-asaas-e-pagarme.md).

# Dois gateways novos (Asaas e Pagar.me) e a refatoração de país no núcleo

## Contexto

O split — o coração do modelo de marketplace — **nunca foi exercitado**. Vendedor e
plataforma foram a mesma conta Mercado Pago, então `marketplace_fee` não transferiu
nada. O Mercado Pago é ruim para provar isso em sandbox e não tem recorrência com
split. O levantamento em `docs/gateway-pay/levantamente.txt` apontou Pagar.me e Asaas
como saídas.

O objetivo real desta entrega é **ver um split de PIX acontecendo em sandbox**, e sair
de um gateway único para uma arquitetura que aguente N gateways × N países.

### O que a investigação mudou no plano original

Três descobertas que valem antes de qualquer código:

**1. A ordem inverte. Asaas primeiro.** No Asaas o split funciona em PIX e a cobrança
devolve `invoiceUrl` — página hospedada, mesmo modelo de redirect que já temos. No
Pagar.me o split em PIX **só existe no `POST /orders` transparente**: o Link de
Pagamento hospedado não faz split em PIX nem em boleto, só em cartão. Pagar.me exige
que a gente renderize o QR Code numa página nossa antes de qualquer split rodar.

**2. A regra fiscal define a direção do split.** Quem vende o curso e emite a nota é o
vendedor; a plataforma só fica com uma porcentagem de serviço. A doc do Asaas confirma
que isso é atendível: *"o saldo líquido não destinado aos recebedores permanece na conta
que criou a cobrança"*. Logo — a cobrança nasce com a **chave do vendedor** e o `split[]`
manda a comissão para a **carteira da plataforma**. Mesma semântica do
`marketplace_fee` do Mercado Pago, sem OAuth.

**3. Isso põe o Pagar.me em xeque.** Recipients (`re_...`) do Pagar.me vivem dentro da
conta do marketplace, o que faria a plataforma ser a merchant of record — exatamente o
que a regra fiscal proíbe. Ver "Em aberto".

### Decisões tomadas com o usuário

| Decisão | Escolha |
|---|---|
| Ordem | Asaas primeiro, Pagar.me depois reaproveitando a camada comum |
| Chave de país | ISO-3166 alpha-2 na oferta (`BR`, `AR`), não o `siteid` do MP |
| Refatoração do núcleo | Fase 0, antes dos gateways |
| Credencial do vendedor | Ele cola a API key da própria conta (Asaas e Pagar.me); validamos na API antes de habilitar |
| Segurança da credencial | Cifrada com `\core\encryption` + capability `managepayment`; MP fica como está |
| Questão fiscal do Pagar.me | **Cada vendedor tem conta Pagar.me própria e chave própria.** A plataforma se cadastra como recebedor dentro da conta de cada vendedor |
| Documentação | Toda ela na worktree `docs-organize-and-improve` |

---

## Fase 0 — refatoração do núcleo

Branch `feature/gateways-nucleo`, worktree própria. Nada de gateway aqui: só tirar o
`local_marketplace` da dependência de um fornecedor. Existe um `git stash`
(`multipais: siteid na oferta + tabela de contas por pais`) com parte disto — aproveitar
a lógica de backfill do upgrade, **trocando `siteid` por país ISO**.

### 1. País na oferta e conta por país

- `local_marketplace_offer.country` char(2) NOT NULL default `BR`, índice `companyid-country`.
- Nova tabela `local_marketplace_account`: `companyid`, `country` char(2), `accountid`
  (FK unique → `payment_accounts.id`), auditoria. Índice único `companyid-country`.
- Novo `classes/country.php`: mapa país → moeda (`BR→BRL`, `AR→ARS`, …) e o nome
  localizado. É este mapa que substitui o `SITE_CURRENCY` do `mp_client` como fonte da
  moeda no núcleo.
- `company::get_payment_account(string $country)` passa a resolver pela tabela nova.
  A assinatura sem argumento some — a busca por `contextid` em
  `classes/company.php:159` devolve *a primeira* conta e é o que trava tudo hoje.
- `company::get_payment_currency()` (`classes/company.php:320`) sai. Moeda passa a vir
  do país da oferta, não do "primeiro gateway habilitado" — que com três gateways na
  mesma conta é indeterminístico.
- `payment/service_provider.php:52` (`get_payable`) resolve a conta por
  `offer->country`. O contrato do core continua respeitado: valor, moeda e conta
  seguem função pura do `itemid`.
- Upgrade adota as contas existentes: MP `siteid` → ISO (`MLB→BR`, `MLA→AR`, …), ou o
  `platformsite` quando o vínculo é anterior à detecção. Sem isso as contas ficam órfãs
  e a empresa aparece como "sem meio de pagamento".

### 2. Tabela de vendas neutra

`report.php:115` e `mysubscriptions.php:160` fazem `SELECT` direto em
`paygw_mercadopago`. Com três gateways o relatório mente por omissão.

- Nova tabela `local_marketplace_sale`: `paymentid` (FK → `{payments}`), `offerid`,
  `companyid`, `userid`, `gateway`, `amount`, `currency`, `feeamount`, `country`,
  `timecreated`.
- `api::record_sale(...)` — o gateway chama depois de `helper::save_payment()`. A
  dependência já aponta nessa direção (o gateway é quem lê
  `api::resolve_commission_percent`), então não há inversão nova.
- `report.php` e `mysubscriptions.php` leem só a tabela nova.
- Upgrade faz backfill das linhas `approved` de `paygw_mercadopago` para não perder o
  histórico. `report.php` já soma separado por moeda — isso continua valendo.

### 3. Comissão sem dono

- `defaultfeepercent` migra de `paygw_mercadopago` para as settings do
  `local_marketplace` (o plugin hoje não tem `settings.php` de verdade; nasce aqui).
  Upgrade copia o valor atual do MP.
- `api.php:135`: trocar `?: 25` por checagem de `false` — hoje um 0% configurado de
  propósito vira 25% silenciosamente.
- Novo `api::commission_for(string $component, int $itemid): float`, com o guarda de
  componente lá dentro. Cada gateway passa a fazer uma chamada em vez de repetir o
  bloco `class_exists` de `payment_processor.php:62-69`.
- `commission_test.php:48` deixa de configurar o namespace do MP.

### 4. Fim dos `mercadopago` fixos na UI

- `company.php:134` linka `gateway=mercadopago` fixo. Passa a listar os gateways
  habilitados da conta daquele país, e a oferecer os disponíveis para vincular.
- Painel da empresa ganha a seção "contas por país": uma linha por país, com os
  gateways de cada uma.
- `offer_form.php` ganha um select de país limitado aos países em que a empresa tem
  conta; a moeda continua não-editável, agora derivada do país
  (`offer_form.php:93-101` hoje cai no literal `'BRL'`).
- `paygw_mercadopago` ganha `get_supported_countries(): ['BR','AR','MX','CL','CO','PE','UY']`
  e a tradução ISO↔`siteid` fica confinada no `mp_client`.

### Testes da fase 0

`country_test` (mapa e moeda), `account_country_test` (resolução por país, conta
ausente, backfill), `sale_test` (registro e leitura), `commission_test` estendido
(namespace novo, 0% preservado). TDD: teste primeiro, em cada item.

Arquivos centrais: `db/install.xml`, `db/upgrade.php` (savepoint ≥ `2026082546`),
`classes/{company,offer,api,country}.php`, `classes/payment/service_provider.php`,
`classes/form/offer_form.php`, `report.php`, `mysubscriptions.php`, `company.php`,
`settings.php`.

---

## Fase 1 — `paygw_asaas`

Branch `feature/paygw-asaas`, saindo de `feature/gateways-nucleo`.

Estrutura espelhando `public/payment/gateway/mercadopago/`, que já segue o padrão do
MDLCode. **O MDLCode é extensão de VS Code, não tem CLI** — se você quiser rodar o
wizard, rode; o esqueleto abaixo é o mesmo que ele gera.

### Modelo

```
Plataforma (conta Asaas própria)  ──► recebe comissão via split[].walletId
Vendedor   (conta Asaas própria)  ──► CRIA a cobrança com a chave dele,
                                       fica com o líquido, emite a nota
```

### Arquivos

| Arquivo | Conteúdo |
|---|---|
| `version.php` | `paygw_asaas`, `requires 2026042000`, **`supported = [502, 502]`**, `MATURITY_ALPHA` |
| `classes/gateway.php` | `get_supported_currencies(): ['BRL']`, `get_supported_countries(): ['BR']`. Form: `apikey` (`configpasswordunmask`, write-only — depois de salvo mostra só os 4 últimos), `walletid` hidden (descoberto), `accountname` hidden. `validate_gateway_form()` chama a API com a chave antes de deixar habilitar |
| `classes/asaas_client.php` | Camada HTTP. Bases `https://api.asaas.com/v3` e `https://api-sandbox.asaas.com/v3`, header `access_token`. **Com costura injetável** (`protected function make_curl()`) — a ausência disso no `mp_client` é o motivo de `payment_processor` ter zero cobertura hoje |
| `classes/payment_processor.php` | `start_payment()`: decifra a chave do vendedor → `POST /customers` (ou reusa) → `POST /payments` com `billingType`, `value`, `dueDate`, `externalReference`, `split[{walletId: plataforma, percentualValue: comissão}]` → grava a linha `pending` **antes** da chamada → devolve `invoiceUrl`. `process_notification()` idempotente, `helper::save_payment` + `api::record_sale` + `helper::deliver_order` nessa ordem |
| `webhook.php` | Valida o header `asaas-access-token` contra um segredo de site — **melhoria real sobre o MP, que não valida nada**. Mesmo validando, re-consulta `GET /payments/{id}` antes de entregar: o payload nunca é a fonte da verdade. 200 em `ignored`/`processed`, 500 em erro para forçar retry |
| `return.php` | Não confirma nada. Espelha o do MP |
| `settings.php` | Seção `paymentgatewayasaas` (**não** `paygw_asaas` — é a armadilha do `Section error`). `platformwalletid`, `sandbox`, `webhooktoken`, `defaultbillingtype` (`UNDEFINED` deixa o comprador escolher; `PIX` força PIX) |
| `db/install.xml` | Tabela `paygw_asaas`: `asaaspaymentid`, `externalreference` unique, `component`, `paymentarea`, `itemid`, `userid`, `accountid`, `amount`, `currency`, `feeamount`, `billingtype`, `status`, `paymentid`, timestamps |
| `db/upgrade.php`, `db/services.php`, `db/tasks.php` | AJAX `create_charge`; task de reconciliação diária para cobranças `pending` (o webhook pode se perder) |
| `lang/{en,pt_br,es}` | Três idiomas completos, **em ordem alfabética estrita** |
| `amd/src` + `amd/build` | AMD escrito à mão. Não há transpilador: `build` é cópia do `src` com o nome do módulo dentro do `define()` |

### Credencial cifrada

`\core\encryption::encrypt()` na gravação, `decrypt()` no uso
(`public/lib/classes/encryption.php:169`). Se `key_exists()` for falso, o formulário
recusa a habilitação com mensagem apontando `admin/cli/generate_key.php` — a chave mora
em `moodledata`, fora do dump do banco. O `paygw_mercadopago` **não** entra nesta
mudança: está em produção e funcionando.

### Testes

`asaas_client_test` (transporte stubado pela costura, mapeamento de erro, montagem do
corpo com split), `payment_processor_test` (cálculo da comissão, idempotência,
entrega), `webhook_test` (token errado, evento ignorado, replay), `gateway_test`
(recusa habilitar sem chave válida). É aqui que ganhamos a cobertura que o MP nunca
teve.

---

## Fase 2 — `paygw_pagarme`

Branch `feature/paygw-pagarme`, saindo de `dev` já com a fase 0 merjeada.

Mesmo modelo do Asaas, agora que a questão fiscal está decidida: **cada vendedor tem
conta Pagar.me própria e chave própria**. A plataforma se cadastra como recebedor
dentro da conta de cada vendedor.

```
Vendedor (conta Pagar.me própria)  ──► CRIA a order com a chave dele,
                                        fica com o líquido, emite a nota
Plataforma (recipient re_... DENTRO da conta do vendedor)
                                   ──► recebe a comissão via split[]
```

Consequência no modelo de dados: o `recipient_id` da plataforma **é diferente em cada
vendedor**, porque é um objeto interno à conta dele. O config por conta de pagamento
guarda os dois: `apikey` (do vendedor, cifrada) e `platformrecipientid` (o `re_...` da
plataforma naquela conta). Onboarding: o vendedor cadastra a plataforma como recebedor
no painel Pagar.me dele e cola o `re_...`; validamos com `GET /recipients/{id}` usando a
chave dele antes de habilitar.

Diferenças técnicas que importam:

- `pagarme_client.php`: `https://api.pagar.me/core/v5` e `https://sdx-api.pagar.me/core/v5`,
  Basic auth com `sk_`.
- PIX via `POST /orders`, `payments[0].payment_method = 'pix'` + `split[]`. A resposta
  traz `last_transaction.qr_code` e `qr_code_url`.
- **Página própria `pix.php`**: renderiza o QR Code, copia-e-cola, e faz polling por
  AMD enquanto o webhook não chega. É o custo de o Link de Pagamento não fazer split em
  PIX.
- Split: `{recipient_id, amount, type: 'percentage', options: {liable, charge_processing_fee, charge_remainder_fee}}`.
  Em assinatura só aceita `percentage`. As regras precisam somar 100%, então são duas:
  o recebedor padrão do próprio vendedor e o da plataforma. `liable` e
  `charge_processing_fee` ficam com o vendedor — quem vende arca com a taxa, coerente
  com a regra fiscal.
- Ordem de dedução das taxas difere da do MP — `course_policy::commission_on()` e a
  string `reportnetnotice` precisam de semântica por gateway.
- A confirmar na implementação: como obter o recebedor padrão da conta do vendedor
  (`GET /recipients` com filtro, ou o `default_recipient` da conta).

---

## Ambiente

```bash
cf new gateways-nucleo --new-stack --seed fresh --branch feature/gateways-nucleo
cf new paygw-asaas     --new-stack --seed fresh --from feature/gateways-nucleo
cf new paygw-pagarme   --new-stack --seed fresh --from dev   # depois da fase 0
```

`--seed fresh` instala do zero, que é o que a VPS vê no upgrade. 33 GB livres, cada
stack ~900 MB — cabe. O stack principal hoje serve `docs-organize-and-improve`, não
`dev`: conferir com `cf ls` antes.

Webhook precisa de URL pública. Túnel (`cloudflared`/`ngrok`) apontando para a porta da
worktree, registrado em `POST /webhooks` do Asaas sandbox.

CI: acrescentar `public/payment/gateway/asaas` e `public/payment/gateway/pagarme` a
`.github/moodle-plugins.txt`. Cada plugin listado precisa de `version.php` ou o job
`validate` falha. O job passa de 5 para 7 instalações completas do Moodle — vai demorar
mais.

---

## Verificação

Nenhuma fase fecha sem estes quatro, com a saída lida por inteiro:

```bash
# 1. PHPUnit
docker exec -u 1000:33 -e COMPOSER_HOME=/tmp/composer <stack>-moodle-1 \
  php /var/www/html/public/admin/tool/phpunit/cli/init.php
docker exec -u 1000:33 -w /var/www/html <stack>-moodle-1 \
  php vendor/bin/phpunit --testsuite local_marketplace_testsuite
docker exec -u 1000:33 -w /var/www/html <stack>-moodle-1 \
  php vendor/bin/phpunit --testsuite paygw_asaas_testsuite

# 2. phpcs — ler o TOTAL, nunca cortar a saída
docker exec -u 1000:33 <stack>-moodle-1 sh -c \
  'cd /tmp/cs && ./vendor/bin/phpcs --standard=moodle --report=summary \
   /var/www/html/public/payment/gateway/asaas' | grep -E "A TOTAL OF"

# 3. Upgrade a partir de uma base com dados (a fase 0 mexe em schema)
cf cli admin/cli/upgrade.php --non-interactive

# 4. actionlint, se o workflow mudar
docker run --rm -v "$PWD:/repo" -w /repo rhysd/actionlint:latest .github/workflows/deploy.yml
```

**A prova que o projeto está esperando há meses** (fim da fase 1, manual, em sandbox):

1. Duas contas Asaas sandbox distintas — plataforma e vendedor.
2. Vendedor cola a API key dele; o formulário descobre o `walletId` e habilita.
3. Oferta BRL de R$ 100, comissão 25%.
4. Compra com PIX, pagamento confirmado manualmente no painel sandbox.
5. Conferir: extrato do vendedor com ~R$ 75 líquidos, extrato da plataforma com R$ 25,
   webhook `PAYMENT_SPLIT_DONE` recebido, entitlement criado, aluno matriculado.

O passo que pode não funcionar de primeira é o 5: a doc do Asaas não diz explicitamente
que uma conta pode fazer split para a carteira da conta que a apadrinhou. Testar isso é
a **primeira coisa** da fase 1 — meia hora de curl, antes de escrever plugin nenhum.

---

## Documentação — toda na worktree `docs-organize-and-improve`

Ela está 4 commits à frente de `dev` e é onde a árvore por assunto vive. Todo texto vai
para lá, em branch própria com PR.

**ADRs novos** (`architecture/README.md` manda decisão nova virar ADR; a pasta só tem
template hoje):

- `adr/0001-gateways-alem-do-mercado-pago.md` — supera a §5 de `decisoes-marketplace.md`
- `adr/0002-conta-de-pagamento-por-pais.md` — ISO na oferta, por que não `siteid`
- `adr/0003-quem-cria-a-cobranca-emite-a-nota.md` — a direção do split, a regra fiscal e
  o que ela exclui em cada gateway

**Documentos existentes que passam a mentir e precisam de revisão:**

| Arquivo | O que muda |
|---|---|
| `architecture/decisoes-marketplace.md` | §5 deixa de ser "a" decisão de pagamento; marcar como superada pelos ADRs |
| `architecture/estado-e-proximas-fases.md` | Multi-país sai de "fundação pronta" para implementado; split deixa de ser "sem prova" quando a fase 1 fechar |
| `data-model/marketplace.md` | Tabelas novas (`local_marketplace_account`, `local_marketplace_sale`), `offer.country`, tabelas dos dois gateways |
| `dev/guia-desenvolvedor.md` | Configuração dos três gateways, não só do app do MP |
| `data-validation/painel-de-testes.md` | Roteiro de sandbox do Asaas e do Pagar.me, incluindo a prova do split de PIX |
| `coding-standards/README.md` | Nada muda, mas conferir se o que fizemos bate |
| `gateway-pay/levantamente.txt` | Vira um documento de decisão em vez de pesquisa solta, com o resultado da avaliação |
| `README.md` | Índice com os arquivos novos |
| `ai-plans/2026-08-27-gateways-asaas-e-pagarme.md` | Registro obrigatório do plano, com as seis seções |

**`CLAUDE.md`** (nas duas worktrees, `dev` e `docs-organize-and-improve`): hoje diz
"cinco plugins" e "split pelo Mercado Pago". De passagem, ele lista
`availability_marketplace`, que **não existe** em `public/availability/condition/` nesta
worktree embora esteja na lista do CI — verificar e corrigir num lado ou no outro.

---

## Em aberto

- Cifrar também os tokens do `paygw_mercadopago`, hoje em texto puro no
  `payment_gateways.config`. Fica para depois: o plugin está em produção e funcionando.
- Recorrência com split. Asaas tem `POST /subscriptions` com split e Pix Automático;
  Pagar.me tem split em assinatura só por porcentagem. Os dois resolvem o que o Mercado
  Pago não resolve, mas isso é uma fase própria — nada aqui depende disso.
