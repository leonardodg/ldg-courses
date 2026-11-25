# Provar o split no Mercado Pago

Roteiro para ver o `marketplace_fee` sair de uma conta e chegar na outra. É o
irmão do [`asaas-sandbox.md`](asaas-sandbox.md), e existe pelo mesmo motivo: o
split é o coração do modelo, e no Mercado Pago ele **nunca foi visto
transferindo nada**.

> Desconfie de sucesso sem erro. Foi exatamente assim que o `marketplace_fee`
> pareceu funcionar da primeira vez — a preferência foi aceita, o pagamento
> aprovou, e nenhum centavo mudou de dono, porque vendedor e marketplace eram a
> mesma conta.

## As três contas, e o papel de cada uma

| Papel | Documento | `user_id` | Aplicação |
|---|---|---|---|
| **Plataforma** (DG Tecnologia) | CNPJ | `3675841384` | `2401225442871147` — a única que o plugin usa |
| **Vendedor** (conta nova) | **pessoa física** | `1233186727` | existe, e não entra em lugar nenhum |
| Conta antiga | pessoa física | — | nenhuma, e não precisa |

**Como saber o tipo de uma conta**, já que isso decide o teste: `GET /users/me`
com o token dela. A conta jurídica traz `identification.type: CNPJ` e a tag
`business`; a de pessoa física não traz nenhum dos dois. Foi assim que se
descobriu que a "empresa nova" era pessoa física — depois de a prova já ter
rodado, e é por isso que a checagem entrou no roteiro.

**O vendedor não precisa de aplicação.** Ele autoriza a aplicação da plataforma
por OAuth, e o `access_token` dele nasce dessa autorização — não de credenciais
coladas à mão. Um token colado fora do fluxo OAuth cria a preferência e **não**
habilita o split.

**A aplicação tem de viver na conta da plataforma.** São três partes no split:
comprador, vendedor e a aplicação. A comissão volta para o **dono da
aplicação**, e não para quem criou a preferência.

## No painel do Mercado Pago

Tudo na aplicação `2401225442871147`, da conta CNPJ. Cadastrar na aplicação do
vendedor devolve `invalid redirect_uri` **sem dizer qual aplicação foi
consultada** — é meia hora perdida procurando no lugar errado.

| Campo | Valor |
|---|---|
| Modelo de integração | **API de Preferências** |
| Redirect URI | `<site>/payment/gateway/mercadopago/oauth_callback.php` |
| Notificações (webhook) | `<site>/payment/gateway/mercadopago/webhook.php`, evento `payment` |

O modelo importa: o `marketplace_fee` vai **na preferência**, não no pagamento.
Declarar "Checkout Transparente" faz o campo ser ignorado em silêncio — e
silêncio aqui significa venda sem comissão que não acusa erro.

## O endereço precisa ser público

O `notification_url` é alcançado pelo Mercado Pago, e o `redirect_uri` tem que
casar **exatamente** com o do painel. `https://localhost:8443` não serve para
nenhum dos dois.

Túnel com **hostname fixo** (`cloudflared` nomeado), apontando para a porta HTTP
da worktree. URL aleatória obrigaria a recadastrar o painel a cada sessão.

O `wwwroot` precisa acompanhar, senão as `back_urls` e o `notification_url` saem
com `localhost`. Em `config-local.php` da worktree (gitignored):

```php
$CFG->wwwroot = 'https://mp.leodg.dev';
$CFG->sslproxy = true;
```

## As rodadas

O plano previa três, com o CNPJ servindo de controle antes de arriscar o CPF.
Duas bastaram, e não pelo motivo previsto — a conta usada como controle era
pessoa física, e ninguém sabia.

| Rodada | Plataforma | Vendedor | O que respondeu |
|---|---|---|---|
| **0** | CNPJ | test user | Nada: o sandbox não cobra. Ver abaixo |
| **1** | CNPJ | **pessoa física** | O split funciona, e o vendedor **não** precisa de CNPJ |
| ~~2~~ | — | — | Cancelada: a Rodada 1 já respondeu |

**A lição vale mais que a economia de uma rodada.** O desenho do teste supunha
saber o tipo de cada conta, e essa suposição nunca foi verificada — o rótulo
"empresa nova" veio do cadastro, não da API. Confira com `/users/me` **antes** de
desenhar a rodada, não depois de concluí-la: o resultado teria sido lido ao
contrário se a prova tivesse falhado.

### Rodada 0 — test users

O script faz tudo que é API; OAuth e pagamento exigem navegador, e não há como
automatizar (o Mercado Pago pede login das duas pontas).

```bash
export MP_CLIENT_ID=...          # aplicacao DA PLATAFORMA
export MP_CLIENT_SECRET=...
export MP_ACCESS_TOKEN=...       # token de producao da conta da plataforma
export MP_REDIRECT_URI=https://mp.leodg.dev/payment/gateway/mercadopago/oauth_callback.php

python3 docs/data-validation/scripts/provar-split-mercadopago.py usuarios
python3 docs/data-validation/scripts/provar-split-mercadopago.py autorizar
# abra a URL logado como o VENDEDOR de teste; o callback devolve o code
export MP_CODE=... MP_VERIFIER=...
python3 docs/data-validation/scripts/provar-split-mercadopago.py trocar
export MP_SELLER_TOKEN=...
python3 docs/data-validation/scripts/provar-split-mercadopago.py cobrar
# pague no init_point, logado como o COMPRADOR de teste
python3 docs/data-validation/scripts/provar-split-mercadopago.py conferir <payment_id>
```

O `testmode` do site tem que estar **ligado**: sem ele o `exchange_code()` não
manda `test_token=true`, a aplicação entra como produção, e o checkout morre com
"uma das partes é de teste" sem dizer qual.

O script **aborta** se o `collector_id` do pagamento for o dono da aplicação. É a
guarda que dá sentido ao resto, e o equivalente do `"as carteiras sao iguais"` do
script do Asaas.

### Rodadas 1 e 2 — contas reais

Na VPS, com `testmode` **desligado** e as URLs de `courses.leodg.dev`
acrescentadas às do túnel no painel — acrescentadas, não substituídas.

Cada rodada, igual: o vendedor vincula por OAuth pelo painel da empresa, oferta
de valor mínimo, comissão 25%, compra com cartão ou Pix, e conferência nos
**dois extratos**.

**Atenção ao valor mínimo.** A taxa do Mercado Pago sai primeiro e o
`marketplace_fee` sai do que sobra. Em R$ 1,00 a taxa pode não deixar saldo, e
quem recusa é o Mercado Pago. Se a preferência for recusada, suba para R$ 5,00 e
anote aqui o piso encontrado. Não confunda essa recusa com a por documento: são
mensagens diferentes, e a Rodada 1 mostra qual é qual.

## Teste de ponta a ponta pelo Moodle

1. Empresa com conta de pagamento no país `BR` e o gateway vinculado.
2. Oferta publicada em BRL, com preço.
3. Comissão da empresa em 25%.
4. Comprar a oferta → escolher Mercado Pago → cai no Checkout Pro.
5. Pagar, e conferir nesta ordem:
   - `paygw_mercadopago.status` = `approved` e `mppaymentid` preenchido
   - `feeamount`, `feepercent`, `feebase` (**sempre `gross`**) e `feesource`
   - a linha em `local_marketplace_sale`
   - a matrícula do aluno
   - `fee_details` do pagamento, com `type: application_fee`
   - **o extrato das duas contas**

## O que conta como prova

`fee_details` diz o que foi **cobrado**. O extrato diz o que **chegou**. A prova
é o segundo — e são necessários os dois lados: comissão na plataforma, líquido no
vendedor, na mesma venda.

## Armadilhas

**Aplicação e vendedor na mesma conta.** O split é aceito e não transfere nada.
É o erro original deste projeto, e a razão de o script abortar.

**Redirect URI na aplicação errada.** `invalid redirect_uri`, sem dizer qual
aplicação foi consultada.

**Integração declarada como Checkout Transparente.** O `marketplace_fee` é
ignorado em silêncio.

**Ambientes misturados.** Aplicação de produção com vendedor de teste é recusado
com "uma das partes é de teste", sem dizer qual das três. O `test_token` no OAuth
resolve.

**Comprador como visitante.** Em teste, visitante não é usuário de teste e a
compra é recusada. Por isso a preferência leva `purpose: wallet_purchase` no modo
de teste — e **só** nele: em produção isso cortaria pagamento sem cadastro,
boleto e dinheiro.

**Empresa "sem meio de pagamento" após vincular.** `account::is_available()`
exige o gateway **habilitado**, não só o token presente.

**Sessão de usuário de teste contamina a compra seguinte.** Depois de mexer com
test users, o navegador guarda a sessão deles em `mercadopago.com.br`. A compra
de produção seguinte falha com *"Uma das partes com as quais você está tentando
efetuar o pagamento é de teste"* — e a mensagem não diz qual parte, então a
suspeita cai no vendedor ou na aplicação, que estão certos. O pagador é que era
de teste. **Saia da conta no `mercadopago.com.br`** ou limpe os cookies do
domínio; fechar a aba anônima não basta se você reabriu outra da mesma sessão.
Fora do modo de teste o `purpose` não é enviado, então o Pix funciona sem
cadastro — pagar sem logar elimina a classe inteira de problema.

**Não há tarefa de reconciliação neste plugin.** O `paygw_asaas` roda uma de
hora em hora; o `paygw_mercadopago` não tem. A linha é gravada antes da chamada
à API, o que é certo — mas checkout abandonado ou recusado deixa `pending` órfão
sem ninguém para fechar. Foi visto acontecer nesta rodada.

## Resultados

### Rodada 1 — 08/09/2026: o split funciona, e o vendedor é pessoa física

Pagamento `178004552586`, Pix, `approved` / `accredited`.

| | |
|---|---|
| Bruto | R$ 5,00 |
| Taxa do Mercado Pago (`mercadopago_fee`) | R$ 0,05 |
| Comissão da plataforma (`application_fee`) | **R$ 1,25** |
| Líquido do vendedor (`net_received_amount`) | R$ 3,70 |
| `collector_id` | `1233186727` — **pessoa física** |
| Dono da aplicação | `3675841384` (DG, CNPJ) — **conta distinta** |

**O CNPJ não é exigido do vendedor.** A conta `1233186727` foi vinculada
acreditando-se que era pessoa jurídica; a conferência posterior em `/users/me`
mostrou `identification.type` vazio e ausência da tag `business`. Ou seja: a
rodada que servia de controle acabou respondendo, sozinha, a pergunta que estava
reservada para a Rodada 2 — e respondeu com dinheiro real, não com suposição.

Isso amplia quem pode vender na plataforma. O que continua obrigatório é o CNPJ
**da plataforma**, dona da aplicação, que é quem recebe a comissão.

`5,00 − 0,05 − 1,25 = 3,70`. A conta fecha, e com ela cai a última dúvida sobre
a **ordem de dedução**, que o README afirmava sem prova: a taxa do Mercado Pago
sai primeiro, o `marketplace_fee` sai do que sobra, e a plataforma recebe
exatamente o combinado. Quem absorve a taxa do gateway é o vendedor.

**Conferido nos dois lados.** No extrato da conta da plataforma (DG), R$ 1,25
aparece como *Dinheiro a liberar* — o equivalente do `AWAITING_CREDIT` do Asaas.
É essa a confirmação que fecha a prova: o `fee_details` diz o que foi **cobrado**,
e o extrato diz o que **chegou**.

Os dois endpoints de saldo estão fechados para esta aplicação
(`/v1/account/movements/search` devolve `404`,
`/users/me/mercadopago_account/balance` devolve `403`), então essa conferência é
manual, no painel — não dá para automatizar no script.

Falta ver o valor sair de *a liberar* e virar saldo disponível, que é o mesmo
pendente que o Asaas tem.

### Compra pelo Moodle — 08/09/2026: a cadeia inteira

A Rodada 1 criou a preferência por script. Esta passou pela vitrine, com o aluno
clicando, e o **webhook chegou sozinho** — nenhuma consulta manual à API.

Empresa Demo, oferta *Curso 1 - 30 dias* a R$ 5,00, comissão da empresa em 25%.

| Elo | O que ficou registrado |
|---|---|
| Mercado Pago | pagamento `177042328687`, Pix, `approved`/`accredited` |
| Split | `application_fee` R$ 1,25 · `mercadopago_fee` R$ 0,05 · líquido R$ 3,70 |
| `paygw_mercadopago` | `status approved`, `feeamount 1.25`, `feepercent 25.00`, `feebase gross`, **`feesource company`** |
| `payments` (core) | id 3, R$ 5,00 BRL, gateway `mercadopago`, component `local_marketplace` |
| `local_marketplace_sale` | id 3, `companyid 2`, `externalid 177042328687`, termos fotografados |
| Direito de acesso | `active`, 09/09/2026 → 09/10/2026 — **30 dias exatos**, como a oferta |
| Matrícula | curso 6, método `marketplace`, ativa |

O `external_reference` do pagamento é `mdl-1010-3-5sfAfFRZIX5w`, o formato do
plugin — é o que amarra o pagamento no Mercado Pago à linha no Moodle.

O `feesource` gravado é **`company`**, e não `site`: a comissão foi resolvida na
linha da empresa pela `commission_terms_for()`, e não caiu no padrão de fábrica.
É a diferença entre provar a cadeia e provar o fallback.

### O ciclo da assinatura — 08/09/2026: renovação, aviso e corte

Assinatura mensal (`catalog` + `recurring`, R$ 5,00), comprada e **renovada com
dinheiro real**. Cada ciclo é uma compra, e cada compra leva split:

| Ciclo | Pagamento | `application_fee` | Taxa MP | Líquido |
|---|---|---|---|---|
| 1 | `177051560827` | R$ 1,25 | R$ 0,05 | R$ 3,70 |
| 2 | `177053889661` | R$ 1,25 | R$ 0,05 | R$ 3,70 |

**A renovação soma, não recalcula.** Vencimento antes: 11/09. Depois da segunda
compra: **11/10** — trinta dias somados ao vencimento *atual*. Se tivesse saído
09/10, a renovação teria encurtado dois dias já pagos, que é exatamente o que o
`offer::get_access_duration()` existe para evitar.

E ficou **um** direito, com `cycles = 2` — não dois direitos. Quatro matrículas,
as mesmas. O `deliver_order()` estende em vez de criar.

**O aviso de vencimento funciona, em dois marcos.** A 3 dias do vencimento saiu o
lembrete; a 12 horas saiu o último aviso, com texto próprio. A terceira execução
mandou zero. O texto do primeiro é honesto sobre o modelo:

> There is no automatic charge — to keep your access, pay again here:
> `…/local/marketplace/offers.php?company=demo&highlight=6`

E o do segundo muda de tom, porque repetir a mesma mensagem ensinaria o aluno a
ignorar as duas:

> Last notice: Assinatura mensal is blocked in 1 day(s)

A deduplicação guarda `vencimento:marco` — `1788963212:1`. Guardar só o
vencimento, como era antes de 08/09/2026, faria o primeiro aviso marcar a linha
e o último **nunca sair**: sem erro, sem log, sem nada.

**O corte de acesso funciona, e por diferença.** Com o direito vencido, o
`sync_entitlements` marcou `expired` e suspendeu **três** das quatro matrículas —
não as quatro. O `Curso Demo 1` continuou ativo porque vinha de outro direito,
ainda vigente. O sync recalcula o que o aluno deve ter em vez de reagir ao
evento.

Matrícula é **suspensa, nunca apagada**: apagar levaria notas e progresso junto,
e quem perde acesso por vencimento costuma voltar. A reativação foi exercitada em
seguida — direito restaurado, `sync_user()` devolveu `3 reativadas` e os quatro
cursos voltaram. Essa metade foi **simulada** restaurando o direito direto no
banco, e não com um terceiro pagamento real.

### Como testar o ciclo sem esperar um mês

Não espere. O vencimento é um `timestamp` na linha do direito, e as duas tarefas
que reagem ao tempo o leem de lá:

```bash
# aviso: mover o vencimento para dentro da janela de 5 dias
php admin/cli/scheduled_task.php --execute='\local_marketplace\task\notify_expiring'

# corte: mover o vencimento para o passado
php admin/cli/scheduled_task.php --execute='\enrol_marketplace\task\sync_entitlements'
```

Uma oferta de 1 dia com cron rodando após a virada testa o mesmo código e custa
um dia por ciclo. Serve como confirmação do agendador, não como teste principal —
e atenção: com `accessdays = 1`, o vencimento cai dentro da janela de 5 dias no
**instante da compra**, então o aviso de "está vencendo" dispara junto com a
confirmação do pagamento.

### Rodada 0 — o sandbox não tem caminho

Executada em 08/09/2026 e **abandonada por limitação do Mercado Pago**, não por
defeito do plugin. Os dois caminhos do Checkout Pro foram exercitados:

| Preferência | Resultado |
|---|---|
| **Com** `purpose: wallet_purchase` | loop de redirecionamento em `/login/wallet/` |
| **Sem** `purpose` | tela de erro |

`GET /v1/payments/search` nas duas referências devolveu **zero pagamentos** — o
checkout recusa antes de criar pagamento, então não há nem `status_detail` para
ler. Isso valida por experimento o comentário que já estava no
`payment_processor`: sem o `wallet_purchase` o pagador vira visitante sem
identidade e o Mercado Pago recusa; com ele, o sandbox trava no login.

O que a Rodada 0 provou, mesmo sem cobrar: a aplicação pertence à conta CNPJ
(`/users/me` → `3675841384`, tag `business`), o OAuth do plugin funciona, o
`test_token` produz token com prefixo `TEST-`, e o Mercado Pago **aceita** o
`marketplace_fee` com `collector_id` diferente do dono da aplicação.

### Rodada 2 — cancelada, porque a Rodada 1 já respondeu

Ela existia para descobrir se o vendedor precisava ser CNPJ. A resposta veio da
Rodada 1, que rodou com pessoa física sem saber: **não precisa**. Repetir com
outra conta de pessoa física não acrescentaria nada.

O que ficou **sem prova** é o inverso, e é o caso convencional: vendedor
**pessoa jurídica**. Não há risco aparente nele — se o MP aceita a conta mais
restrita, aceitar a menos restrita é o esperado —, mas não foi visto. Quando
houver uma segunda conta CNPJ disponível, vale uma rodada de R$ 5,00 para
fechar.
