# Provar o split do Mercado Pago com duas contas

> **Correção, 08/09/2026 — o plano parte de um fato errado.** A conta
> `1233186727`, aqui chamada de "Vendedor B / empresa nova / CNPJ", é **pessoa
> física**: `/users/me` devolve `identification.type` vazio e sem a tag
> `business`. O tipo veio do rótulo do cadastro, e nunca foi verificado.
>
> Consequência: a Rodada 1, desenhada como controle, respondeu sozinha a
> pergunta reservada para a Rodada 2 — **o vendedor não precisa de CNPJ** —, e a
> Rodada 2 foi cancelada por redundância. O resultado está em
> `docs/data-validation/mercadopago-split.md` e a decisão em `docs/adr/0010`.
>
> O texto abaixo fica como estava, porque é o registro do que se planejou.

## Contexto

O `paygw_mercadopago` **já implementa o split**: a preferência é criada com o
token do vendedor obtido por OAuth, e o `marketplace_fee` devolve a comissão para
o dono da aplicação. O que nunca aconteceu foi a **prova**. O README do plugin
registra o motivo com todas as letras:

> O split no Mercado Pago continua sem prova. Diferente do Asaas, nunca foi visto
> transferindo entre duas contas distintas neste projeto. […] desconfie de sucesso
> sem erro: foi exatamente assim que o `marketplace_fee` pareceu funcionar sem
> transferir nada, quando vendedor e marketplace eram a mesma conta.

Agora existem **três** contas, e com elas dá para responder uma pergunta aberta
do projeto — *o vendedor precisa ser CNPJ?* — em vez de supor.

Junto com a prova vem o débito técnico que impede testá-la sem rede. O próprio
README aponta:

> A cobertura é menor que a do Asaas por um motivo estrutural: o `mp_client`
> instancia curl inline […] O `asaas_client` tem a costura `make_curl()` e por
> isso é testável sem rede — vale replicar aqui quando este arquivo for mexido.

Resultado pretendido: o `marketplace_fee` coberto por PHPUnit, a configuração
coberta por Behat, o split visto saindo de uma conta e chegando na outra em dois
ambientes, e um roteiro repetível como o `docs/data-validation/asaas-sandbox.md`.

Este é o primeiro dos meios de pagamento; o Pagar.me continua parado esperando
CNPJ (`docs/ai-plans/2026-08-27-plano-original-asaas-e-pagarme.md`).

## As três contas

| Papel | Conta | Documento | `user_id` | Aplicação |
|---|---|---|---|---|
| **Plataforma** (marketplace) | DG Tecnologia | CNPJ | `3675841384` | `2401225442871147` — **a única que o plugin usa** |
| **Vendedor B** | empresa nova | CNPJ | `1233186727` | `8050532664984590` — existe, mas não entra em lugar nenhum |
| **Vendedor A** | conta antiga | CPF | — | nenhuma, e não precisa |

**O vendedor não precisa de aplicação.** Ele só autoriza a aplicação da
plataforma por OAuth; o `access_token` dele nasce dessa autorização
(`oauth_callback.php`), não de credenciais coladas à mão. A aplicação criada na
conta vendedora não faz mal, mas não é usada — e cria uma armadilha concreta: o
**redirect URI e o webhook têm de ser cadastrados na aplicação da plataforma**
(`2401225442871147`). No app errado, o OAuth volta com `invalid redirect_uri` e
a mensagem não diz qual app está sendo consultado.

Os `user_id` no fim dos tokens de teste (`…-3675841384` e `…-1233186727`)
confirmam que são contas distintas. É o que o split exige: dono da aplicação
diferente do vendedor. Quando eram a mesma conta, o `marketplace_fee` "funcionou"
sem transferir nada.

## Decisões já tomadas

| Decisão | Valor |
|---|---|
| Dona da aplicação | Conta CNPJ (DG Tecnologia) — recebe o `marketplace_fee` |
| Vendedores | Os dois: CNPJ **e** CPF, para comparar |
| Onde | Local com túnel primeiro (ciclo rápido), VPS depois (prova final) |
| Ambiente | Test users **e** produção com valor real — as duas provas |

## O que NÃO vamos fazer

Não reescrever o fluxo de OAuth, o webhook nem o `marketplace_fee` — eles estão
implementados e o desenho está registrado em `docs/adr/0003` e `docs/adr/0007`.
Não adotar o SDK oficial (`mercadopago/dx-php`); a razão está no docblock do
`mp_client`. Não mexer na ordem de dedução: ela é do Mercado Pago.

---

## Fase 0 — Ambiente

Worktree própria, saindo de `dev` (o offset 0 serve `dev` hoje, então a guarda de
versão do `moodev new` passa):

```bash
moodev new paygw-mp-split --from origin/dev
moodev ls          # confirmar offset e portas antes de seguir
moodev up paygw-mp-split
```

**Túnel com hostname fixo.** O `notification_url` e o `redirect_uri` do OAuth
precisam ser alcançáveis pelo Mercado Pago, e o `redirect_uri` tem que casar
**exatamente** com o cadastrado no painel. Um túnel de URL aleatória obrigaria a
recadastrar o painel a cada sessão — use um túnel nomeado do `cloudflared` com
hostname estável (ex.: `mp.leodg.dev`) apontando para a porta HTTP da worktree.

O `wwwroot` precisa acompanhar, senão as `back_urls` e o `notification_url` saem
com `localhost`. Vai em `config-local.php` da worktree (gitignored, exemplo em
`.devcontainer/config/config-local.php.example`):

```php
$CFG->wwwroot = 'https://mp.leodg.dev';
$CFG->sslproxy = true;
```

Painel do Mercado Pago, na aplicação da conta CNPJ:

- **Redirect URI:** `https://mp.leodg.dev/payment/gateway/mercadopago/oauth_callback.php`
- **Modelo de integração:** *API de Preferências* — o `marketplace_fee` vai na
  preferência, não no pagamento. Declarar "Checkout Transparente" aqui é o erro
  que faz o campo ser silenciosamente ignorado.
- **Notificações (webhook):** `https://mp.leodg.dev/payment/gateway/mercadopago/webhook.php`, evento `payment`.

---

## Fase 1 — Costura testável e PHPUnit

Duas mudanças de estrutura, ambas copiando o que já funciona no `paygw_asaas`.

### 1.1 A costura no `mp_client`

`public/payment/gateway/mercadopago/classes/mp_client.php` monta `new curl()`
duas vezes: em `request()` (linha 297) e em `post_json()` (linha 329). O segundo
é **estático**, usado pelo fluxo OAuth — então a costura tem que ser estática
também, e chamada por `static::` para o *late static binding* levar à subclasse:

```php
protected static function make_curl(): curl {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');
    return new curl();
}
```

Trocar os dois `new curl()` por `static::make_curl()`. Uma única costura cobre os
quatro endpoints (`/oauth/token`, `/users/me`, `/checkout/preferences`,
`/v1/payments/{id}`).

### 1.2 A conta que move dinheiro, extraída como função pura

`payment_processor::start_payment()` (linha 42) tem 120 linhas que só rodam com
banco, sessão e rede — por isso o `marketplace_fee` nunca teve teste. Extrair as
duas partes puras, como o Asaas fez com `asaas_client::build_split()`:

- `payment_processor::fee_for(float $amount, float $percent): float` — o
  `round($amount * ($percent / 100), 2)` da linha 79.
- `payment_processor::build_preference_body(...): array` — recebe valor, moeda,
  referência, comissão, `wwwroot` e o booleano de teste; devolve o corpo com
  `items`, `external_reference`, `marketplace_fee`, `back_urls`,
  `notification_url`, `auto_return` e o `purpose` condicional.

O `start_payment()` passa a chamá-las. Nenhuma mudança de comportamento — é
recorte para dar entrada ao teste.

### 1.3 Fixtures

Espelhar `public/payment/gateway/asaas/tests/fixtures/`:

- `tests/fixtures/fake_mp_client.php` — `extends mp_client`, sobrescreve
  `make_curl()`, guarda `$lastbody` e `$calls`, com os botões `$nextresponse`,
  `$rawresponse`, `$nextstatus`, `$nexterrno`.
- `tests/fixtures/fake_curl.php` — cópia adaptada do
  `paygw_asaas/tests/fixtures/fake_curl.php`, mesma namespace do plugin.
  Não sobrescrever `setHeader` (o phpcs do Moodle recusa o camelCase).

Fixtures não são autoload: `require_once(__DIR__ . '/fixtures/...')` no topo do
teste, como em `asaas_client_test.php:24`.

### 1.4 Testes

Mantém os 8 de `tests/mp_client_test.php` (PKCE, moeda, URL de autorização) e
acrescenta.

**`tests/mp_client_test.php`** — camada HTTP, agora alcançável:

| Teste | Prova |
|---|---|
| `test_create_preference_body` | `marketplace_fee` chega ao corpo como valor absoluto |
| `test_erro_da_api_carrega_a_mensagem` | 400 vira `errorapi` com o texto do MP, não "HTTP 400" |
| `test_resposta_nao_json_e_recusada` | `errorinvalidresponse` em vez de array vazio |
| `test_falha_de_transporte_e_reportada` | `errno` vira `errorcurl` antes de qualquer decode |
| `test_exchange_code_manda_test_token` | `test_token` presente só em `testmode` |
| `test_exchange_code_manda_o_verifier` | `code_verifier` no corpo da troca |
| `test_refresh_token_body` | `grant_type=refresh_token` e nenhum `code` |
| `test_get_payment_escapa_o_id` | `rawurlencode` no caminho |

**`tests/payment_processor_test.php`** (novo):

| Teste | Prova |
|---|---|
| `test_comissao_e_absoluta_sobre_o_bruto` | 25% de R$ 100 = R$ 25,00 |
| `test_comissao_arredonda_para_centavos` | 9,9% de R$ 100 = R$ 9,90 |
| `test_base_liquida_configurada_grava_gross` | A divergência do ADR-0007, no MP |
| `test_marketplace_fee_vai_na_preferencia` | Chave presente, e no nível certo do corpo |
| `test_purpose_so_em_modo_teste` | `wallet_purchase` ausente em produção |
| `test_comissao_zero_nao_inventa_fee` | Comportamento explícito, não acidental |
| `test_back_urls_e_notification_url` | As quatro URLs saem do `wwwroot`, não hardcoded |
| `test_referencia_externa_e_unica` | Duas chamadas, duas referências |

Comissão zero merece atenção: **decidir e testar** se `marketplace_fee => 0` vai
no corpo ou é omitido. No Asaas, split vazio não é "sem comissão", é corpo
inválido (`asaas_client.php:209`). Se o MP recusar, a Fase 3 mostra — e o teste
passa a documentar a decisão.

### 1.5 Limpeza: o `defaultfeepercent` órfão

`settings.php` declara `paygw_mercadopago/defaultfeepercent`, mas **nada o lê**.
O `db/upgrade.php:253` do `local_marketplace` já migrou o valor para
`local_marketplace/defaultfeepercent`, e o `payment_processor` usa `25.0`
fixo (linha 62). Pior: o texto de ajuda diz que a comissão incide "sobre o
restante" depois da taxa do MP, o que contradiz o código e o `install.xml`.

Remover o campo e as strings (`en`, `pt_br`, `es`) — o valor já vive no lugar
certo, e um campo de admin que não faz nada é pior que campo ausente. As strings
de idioma têm **ordem alfabética obrigatória**; reordenar o arquivo depois de
mexer. Atualizar a tabela de configuração do README do plugin.

---

## Fase 2 — Behat

Não existe feature para gateway nenhum neste projeto — esta é a primeira. Ela
cobre o que é do Moodle (configuração e a trava de habilitar); o checkout sai do
site para o Mercado Pago e não é behat, é a Fase 3.

`tests/behat/settings.feature`, tag `@paygw_mercadopago`:

1. **A seção existe e salva** — admin abre
   `/admin/settings.php?section=paymentgatewaymercadopago`, preenche `clientid`,
   `clientsecret`, `platformsite` e `testmode`, e os valores persistem. Cobre a
   armadilha registrada no `CLAUDE.md`: a seção é `paymentgateway<nome>`, não
   `paygw_<nome>`.
2. **Sem token, o gateway não habilita** — na conta de pagamento, marcar
   *habilitado* sem `accesstoken` é recusado pelo `validate_gateway_form()`
   (`gateway.php:95`). Este é o cenário que protege dinheiro: gateway habilitado
   sem token dá "erro no checkout, diante do aluno".
3. **A tela oferece vincular** — com a aplicação configurada, o bloco de status
   mostra o link de autorização; sem ela, avisa que falta configurar.

O formulário de gateway do `core_payment` abre em modal AJAX, então os cenários
2 e 3 são `@javascript` — e `@javascript` **exige** `moodev up --full` e
`--profile=chrome`, senão morre procurando `localhost:4444`.

Se o gerador behat do `core_payment` não permitir semear a config do gateway,
criar `tests/behat/behat_paygw_mercadopago.php` com um passo `Given a conta de
pagamento :nome tem o Mercado Pago vinculado` que grava a config direto — é o
mesmo recurso que o `local_marketplace` já usa nas features dele.

---

## Fase 3 — Rodada 0: mecanismo, com test users (túnel local)

Sem dinheiro real. Prova que a montagem funciona antes de qualquer conta de
verdade entrar. Os test users são criados na aplicação da CNPJ, com o access
token de produção dela:

```
POST /users/test_user   {"site_id": "MLB"}
```

Dois: **vendedor de teste** e **comprador de teste**. O vendedor é quem vincula
por OAuth — e como a aplicação é de produção, o `testmode` do site tem que estar
ligado para o `exchange_code()` mandar `test_token=true`. São três partes no
split (comprador, vendedor e **aplicação**); misturar ambientes é recusado com
"uma das partes é de teste", sem dizer qual.

`docs/data-validation/scripts/provar-split-mercadopago.py`, espelhando o
`provar-split-asaas.py` (stdlib apenas, sem dependência):

1. Cria os dois test users e imprime as credenciais.
2. **Aborta se o `collector_id` da preferência for igual ao id do dono da
   aplicação** — esta guarda é o coração do script. Foi exatamente esse o caso em
   que o split "funcionou" sem transferir nada, e é o equivalente do
   `"as carteiras sao iguais"` do script do Asaas.
3. Cria a preferência com `marketplace_fee` usando o token do vendedor.
4. Imprime o `init_point` para pagar no navegador (o OAuth e o pagamento exigem
   navegador; não dá para automatizar, e o roteiro assume isso).
5. Depois do pagamento, consulta `GET /v1/payments/{id}` e confere:
   - `status: approved`
   - `collector_id` = vendedor, e **diferente** do dono da aplicação
   - em `fee_details`, uma entrada `type: application_fee` com o valor exato
   - `transaction_details.net_received_amount` do vendedor = bruto − taxa MP − comissão
6. Confere o saldo dos dois lados por `/v1/account/movements/search` ou pelo
   extrato do painel — **a prova é o dinheiro nos dois extratos**, não a ausência
   de erro.

Ponta a ponta pelo Moodle, na worktree com o túnel: empresa com conta BR, gateway
vinculado ao vendedor de teste, oferta em BRL, comissão 25%, comprar logado como
o comprador de teste, e conferir `paygw_mercadopago.status = approved`,
`feeamount`, a linha em `local_marketplace_sale` e a matrícula.

---

## Fase 4 — Rodadas 1 e 2: contas reais, e a pergunta do CNPJ (VPS)

Só depois da Rodada 0 verde. Merge para `dev` dispara o deploy para
`courses.leodg.dev` (não há PR `dev`→`main` no caminho normal). `testmode`
**desligado** no site, e as URLs de `courses.leodg.dev` acrescentadas às do túnel
no painel — acrescentadas, não substituídas, para o ciclo local continuar
servindo.

| Rodada | Plataforma | Vendedor | O que responde |
|---|---|---|---|
| **1** | CNPJ (DG) | **CNPJ** (empresa nova) | Controle: o split funciona entre contas reais? |
| **2** | CNPJ (DG) | **CPF** (conta antiga) | O vendedor pode ser pessoa física? |

**A ordem não é arbitrária, e não é a que foi pedida.** Rodar o CPF primeiro
parece direto, mas se ele falhar você não sabe se o culpado é o documento ou a
sua configuração — não há controle para comparar. Com o CNPJ provado antes, a
única variável que muda na Rodada 2 é o documento do vendedor, e a falha (se
houver) passa a significar alguma coisa. Foi por isso que a conta CNPJ nova
entrou: use-a como controle, não como plano B.

Cada rodada, igual: vendedor vincula por OAuth, oferta de valor mínimo, comissão
25%, compra com cartão real ou Pix, e a conferência nos **dois extratos** —
comissão na DG, líquido no vendedor.

Registrar o resultado da Rodada 2 nos dois desfechos possíveis, porque os dois
são achados:

- **CPF aceito** → o vendedor pessoa física é viável, e isso amplia quem pode
  vender na plataforma. Anotar no runbook, com o print da comissão recebida.
- **CPF recusado** → anotar a **mensagem exata** do Mercado Pago e em que ponto
  ela aparece: na autorização OAuth, na criação da preferência ou só no
  pagamento. É a diferença entre bloquear no cadastro da empresa e descobrir com
  o aluno na frente do checkout. Vira ADR-0010 e uma validação no
  `oauth_callback.php`.

**Atenção ao valor mínimo.** A taxa do Mercado Pago sai primeiro e o
`marketplace_fee` sai do que sobra; se a taxa não deixar saldo, quem recusa é o
MP. Em R$ 1,00 a taxa pode comer a margem — se a preferência for recusada, subir
para R$ 5,00 e registrar o piso encontrado no runbook. É informação de operação,
não fracasso do teste. E não confunda esse `400` com a recusa por documento: são
mensagens diferentes, e a Rodada 1 já terá mostrado qual é qual.

---

## Fase 5 — Documentação

| Arquivo | O que muda |
|---|---|
| `docs/data-validation/mercadopago-split.md` (novo) | Runbook completo, na estrutura do `asaas-sandbox.md`: as três contas e o papel de cada uma, por que o vendedor não tem aplicação, painel (redirect, modelo de integração, webhook — **na aplicação da plataforma**), túnel, test users, roteiro `curl` passo a passo, as três rodadas com os números de cada uma, ponta a ponta pelo Moodle, armadilhas |
| `docs/data-validation/scripts/provar-split-mercadopago.py` (novo) | O script da Fase 3 |
| `docs/data-validation/README.md` | Índice |
| `payment/gateway/mercadopago/README.md` | Trocar "continua sem prova" pelo resultado, com data e valores; atualizar a tabela de config (sai o `defaultfeepercent`) e a contagem de testes |
| `CLAUDE.md` | "Estado atual": total de testes, e tirar o MP de "continua sem prova" |
| `docs/architecture/estado-e-proximas-fases.md` | Mesma atualização |
| `docs/adr/0010-vendedor-pessoa-fisica-no-mercado-pago.md` | O resultado da Rodada 2, nos dois desfechos. Se o CPF for recusado, o ADR registra a mensagem, o ponto da falha e a validação que passa a existir no `oauth_callback.php`; se for aceito, registra que a restrição não existe — o que também é decisão, porque hoje ninguém sabe |

O runbook precisa registrar o **número real**: qual valor, qual comissão, qual
taxa do MP, quanto chegou em cada conta e em que data. É o que o `CLAUDE.md` faz
com o Asaas ("R$ 100 brutos → R$ 97,52 líquidos → 25% = R$ 24,38"), e é o que
distingue prova de impressão.

---

## Verificação

Na worktree, antes de qualquer PR:

```bash
# 1. Testes — ler o total, não cortar a saída
docker exec -u 1000:33 -e COMPOSER_HOME=/tmp/composer courses-free-paygw-mp-split-moodle-1 \
  php /var/www/html/public/admin/tool/phpunit/cli/init.php
docker exec -u 1000:33 -w /var/www/html courses-free-paygw-mp-split-moodle-1 \
  php vendor/bin/phpunit --testsuite paygw_mercadopago_testsuite

# 2. phpcs — o CI roda com --max-warnings 0, aviso também reprova
docker exec -u 1000:33 courses-free-paygw-mp-split-moodle-1 \
  phpcs --standard=moodle -p --report=summary /var/www/html/public/payment/gateway/mercadopago

# 3. Behat com navegador
moodev up --full paygw-mp-split
docker exec -d -u 1000:33 courses-free-paygw-mp-split-moodle-1 \
  sh -c 'cd /var/www/html/public && php -S 0.0.0.0:8000 >/tmp/behatweb.log 2>&1'
docker exec -u 1000:33 courses-free-paygw-mp-split-moodle-1 \
  php /var/www/html/public/admin/tool/behat/cli/util.php --enable
docker exec -u 1000:33 -w /var/www/html courses-free-paygw-mp-split-moodle-1 \
  vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml \
  --profile=chrome --tags "@paygw_mercadopago"

# 4. Upgrade limpo depois do bump em version.php
moodev cli upgrade.php --non-interactive
```

O nome do stack sai do `moodev ls` — confira em vez de presumir o `-paygw-mp-split-`
acima. O `paygw_mercadopago` já está em `.github/moodle-plugins.txt`, então o CI
roda o job dele sozinho; nada a acrescentar lá.

**A prova de verdade não é comando nenhum destes.** É o extrato das duas contas:
comissão na CNPJ, líquido na CPF, na mesma venda. Sem os dois números, o
resultado é "não deu erro" — e o README do plugin existe justamente para lembrar
que já foi assim uma vez.

## Ordem de entrega

Fases 1 e 2 num PR (código e testes, verde no CI). Fase 3 depois, porque pode
mudar o código — se o MP recusar `marketplace_fee: 0` ou exigir campo que não
mandamos, a correção volta para a Fase 1. Fase 4 depois do merge em `dev`, e as
duas rodadas na ordem: CNPJ primeiro, CPF depois. A documentação da Fase 5
acompanha cada PR; o runbook e o ADR-0010 fecham no fim, com os números.

Só abrir o PR com **tudo** pronto: commit empurrado depois do merge fica órfão, e
isso já aconteceu quatro vezes neste projeto.
