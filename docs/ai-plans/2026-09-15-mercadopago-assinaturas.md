# Mercado Pago: assinatura com split, e uma aplicação por tipo de integração

**Situação:** fechado com pendências documentadas · **Início:** 2026-09-15 · **Última sessão:** 2026-09-17

> **Por que "com pendências" e não "concluído":** a venda com split está
> provada com dinheiro real em 3 dos 4 fluxos (Pix na assinatura, certificado,
> curso avulso). O cartão tem o fluxo técnico correto mas nenhuma aprovação
> real ainda, e o modelo de cobrança do ciclo 2+ mudou de novo nesta rodada
> (ver abaixo). Nada disto bloqueia o que já vende - são itens para a próxima
> rodada. Detalhe em **Estado da execução**, logo abaixo.

---

## Estado da execução — atualizado em 17/09/2026 (fechamento da rodada 5)

Ponto de retomada. Quem chegar aqui numa sessão nova lê **esta seção primeiro**.
As rodadas anteriores desta seção (guardadas no histórico do git) pararam em
pontos **já superados** - leia daqui, não delas.

**Worktree:** `paygw-mp-assinatura`, branch `feature/paygw-mp-assinatura`.
**Túnel:** `mp.leodg.dev` → `https://localhost:8443`, funcionando.
**Verde:** PHPUnit `127/127` no gateway, `138/138` no `local_marketplace`,
phpcs limpo nos dois, `npx grunt amd` limpo.

### Rodada 5 (mesmo dia): configuração por empresa e visibilidade

Fora da prova com dinheiro real, três coisas entraram nesta rodada:

- **`card_capture` e `payment_methods` viram configuráveis POR CONTA de
  pagamento** (empresa), não só por site - o padrão do plugin continua
  valendo pra quem não escolheu nada, preservando o comportamento anterior.
  Quem preenche é sempre o ADMIN, na tela de contas de pagamento do core.
- **Forma de pagamento da assinatura passou a aparecer na tela** - admin
  (`report.php?view=subscriptions`) e aluno (`mysubscriptions.php`, só
  leitura) - via `api::payment_method_for()`, mesmo padrão dos outros
  dispatchers do `local_marketplace`.
- **Desenho (documento, zero código) da assinatura SaaS** que a empresa
  parceira paga à PLATAFORMA (hospedagem/uso, dois planos: Start e PRO) -
  ver `docs/ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md`. Não
  faz parte deste PR; é o próximo passo depois dele.

| Etapa | Situação |
|---|---|
| Fases 0 a 4 | **feitas** |
| Fase 5 — prova de ponta a ponta | **fechada com pendências** - ver abaixo |

### Decisão tomada nesta rodada: cartão também vira "fatura por ciclo"

A rodada 3 tinha deixado em aberto uma decisão: o ciclo 2+ do cartão trava
sem ESC (Mercado Pago exige CVV em toda cobrança automática nesta conta, e
isso não muda por histórico de pagamento - medido). **Decidido sem esperar
resposta do suporte do MP**: o cartão passa a seguir o MESMO modelo que
Pix/boleto já usavam - cada ciclo pede confirmação do aluno, em vez de
tentar uma cobrança que sabidamente falha.

A diferença do cartão para Pix/boleto é que há um instrumento guardado (o
`card_id`) - falta só o CVV, não o cartão inteiro de novo. Medido contra a
conta real: `mp.createCardToken({cardId, securityCode})` tokeniza PELO
NAVEGADOR, com a public key - o CVV nunca chega ao servidor, só o token
pronto. Implementado em `confirm_cycle.php` + `card_form.js:
initConfirmCycle()`. `charge_cycle()` (a cobrança automática antiga)
continua no código, sem uso, pronta para o dia em que ESC for habilitado -
ver o texto de solicitação enviado ao usuário para mandar ao suporte do MP,
sem expectativa de resposta favorável.

**`charge_due_cycles` não tenta cobrar sozinho mais nada** - nem cartão, nem
Pix, nem boleto. Toda cobrança agora emite o ciclo e avisa o aluno.

### Provado com dinheiro real

| Fluxo | Resultado |
|---|---|
| Assinatura por **Pix** | aprovada, `application_fee` correto, matrícula nos 3 cursos |
| Certificado (Checkout Pro, Pix) | aprovado, split correto, entitlement + matrícula |
| Curso avulso (Checkout Pro, Pix) | aprovado, split correto, matrícula |
| Cancelamento de assinatura | testado via `cancel_subscription()` - acesso já pago preservado, cobrança futura para |

### Correto no código, sem aprovação real ainda

| Fluxo | Situação |
|---|---|
| Assinatura por **cartão**, ciclo 1 | fluxo técnico correto (11 bugs corrigidos, documentados abaixo) - 3 tentativas reais processaram mas foram recusadas por antifraude (`cc_rejected_high_risk`/`bad_filled_security_code`), não por bug |
| Confirmar ciclo 2+ do cartão com CVV | codado e medido em isolamento (curl) contra a conta real - nunca exercitado pela tela |
| Boleto na assinatura | codado e testado por lógica; nunca comprado de verdade (a oferta de teste precisou subir para R$20, o mínimo do boleto) |
| Lembrete de vencimento, ciclo 2 do Pix/boleto, trocar Pix→cartão, cancelar pelo botão real | codados com TDD; nunca exercitados pela tela |

### Pendências, em ordem de impacto

1. **Testes de tela do CRUD** listados na tabela acima - nada quebrado
   conhecido, só não exercitado pela interface real. Isto inclui a
   confirmação de ciclo por CVV, que é a peça nova desta rodada.
2. **Assinatura por cartão aprovada de verdade** (ciclo 1) - tentar de novo
   depois de um intervalo maior sem tentativas em sequência (o antifraude
   parece ligado ao padrão de várias tentativas de valor baixo na mesma
   conta, não a um cartão específico).
3. **Preço da oferta "Assinatura - 3 cursos"** está em **R$20** (subido de
   R$5 para caber o mínimo do boleto) - decidir se volta para R$5 ou fica,
   dependendo de quando o boleto for testado de verdade.
4. **Linhas de teste órfãs** na tabela `paygw_mercadopago` (várias tentativas
   `pending`/`rejected` desta rodada de depuração) - não afetam nada, mas
   podem ser limpas se incomodarem um relatório.
5. **Resposta do suporte do Mercado Pago sobre ESC** - se vier favorável,
   `charge_due_cycles` pode voltar a chamar `charge_cycle()` para cartão em
   vez de `issue_card_cycle()`; a troca é de uma linha só. Sem expectativa.

### Ambiente da prova, já montado

| | |
|---|---|
| Vendedor | `1233186727` (Ivana), **produção**, Preferências e Bricks na conta de pagamento **2** |
| Plataforma | `3675841384` — contas distintas, a guarda aceita |
| Empresa | **Ivana Academy** (`ivana-academy`), comissão **80%**, origem `company` |
| Ofertas | Assinatura 3 cursos (`6`), Certificado (`13`), Curso avulso (`2`) — todas **R$ 5,00** |
| `testmode` | **desligado**, chaves de produção |
| Captura de cartão | **`brick`** (SAQ A) — trocado de `direct` nesta rodada, ver abaixo |
| Aluno | `aluno.prova.mp@leodg.dev` |
| Vitrine | `/local/marketplace/offers.php?company=ivana-academy` |

### ONDE PAROU — o próximo passo exato

**O fluxo técnico funciona de ponta a ponta.** Três pagamentos reais foram
criados na API (`178404134477`, `178405194209`, `179384118044`) com cliente,
cartão salvo, cobrança e `application_fee` corretos. Nenhum foi **aprovado**
ainda: dois voltaram `cc_rejected_high_risk` e um `cc_rejected_bad_filled_
security_code` — recusas do banco/antifraude, não bugs.

**Hipótese mais provável**: várias tentativas de valor baixo (R$ 5) em
sequência rápida, na mesma conta de vendedor, no mesmo dia — o padrão que
qualquer antifraude associa a teste de cartão roubado, mesmo sendo teste
legítimo. **Não tentar de novo em sequência.** Esperar (a orientação dada foi
"algumas horas") antes da próxima tentativa, e evitar rodadas de tentativas
seguidas quando ela vier.

**Se persistir depois de esperar**, abrir chamado com o suporte do Mercado
Pago perguntando pelo status de risco da conta — comum em contas novas.

### Nove defeitos corrigidos nesta rodada, na ordem em que apareceram

Todos só visíveis testando de verdade — nenhum apareceu em phpunit, phpcs ou
behat. Medição completa de cada um em
[`../data-validation/mercadopago-assinatura.md`](../data-validation/mercadopago-assinatura.md).

1. **Formulário aninhado** — o Brick renderiza o próprio `<form>`; dentro do
   nosso, o clique submetia o externo vazio.
2. **`onReady` obrigatório no Brick** — sem ele fica preso no esqueleto de
   carregamento, erro só no console.
3. **`payment_method_id` vazio derruba o `save_card`** — ausente ≠ vazio.
4. **`step()` prometia `array`, o primeiro passo devolve `string`** —
   `TypeError` antes mesmo de chamar o Mercado Pago.
5. **`cardNumber.getBin()` não existe** — o SDK avisa a bandeira pelo evento
   `binChange`, não por um método de consulta.
6. **A busca por BIN pega o primeiro resultado, que pode não ser cartão** —
   Pix, boleto e Mercado Crédito vêm misturados na mesma lista.
7. **O parâmetro certo da busca por BIN é `bins`, no plural** — `bin` no
   singular (usado num teste manual) devolve um catálogo genérico, sempre
   igual, não filtrado.
8. **`issuer_id` faltando E, quando presente, teria que ser número** — string
   quebra o corpo inteiro (`the body must be a Json Object`); o campo certo
   vem do endpoint dedicado `card_issuers` / `mp.getIssuers()`, não do
   `issuer` genérico da busca por BIN.
9. **A causa raiz de verdade do erro 127**: o token dos Secure Fields (modo
   `direct`) não carrega BIN nenhum (`GET /v1/card_tokens/{id}` devolve
   `first_six_digits` vazio) — sem dado de cartão no token, o Mercado Pago
   não tem contra o que resolver bandeira nem emissor. **Trocado para o modo
   `brick`**, o componente que o próprio Mercado Pago mantém para
   salvar-e-cobrar-depois.
10. **`/v1/payments` exige `X-Idempotency-Key`** — nenhum outro endpoint do
    plugin exige. Usa a `external_reference` do ciclo.
11. **`security_code_id can't be null`** — re-tokenizar o cartão guardado por
    `card_id` sem CVV devolve token `active`, mas ele **não cobra**.
    `charge_first_cycle()` agora cobra com o MESMO token que o navegador
    criou (que carrega o CVV por dentro), em vez de re-tokenizar.

E um décimo segundo, fora do gateway, no `availability_marketplace`: **o
botão de comprar aparecia só na condição NEGADA**, por leitura errada do
`$not` — nunca para quem deveria comprar. Corrigido.

### Decisão em aberto, e é grande: ciclo 2+ trava sem ESC

O item 11 acima resolve o **ciclo 1** (tem o aluno na tela, digitando CVV).
Os **ciclos 2 em diante** (`charge_due_cycles()`) continuam re-tokenizando
por `card_id` sem CVV — não há aluno para digitar nada um mês depois — e vão
bater na MESMA validação (`security_code_id can't be null`), sempre, porque
o recurso que dispensa CVV (**ESC**, "Established Stored Credential") não se
liga sozinho por histórico de pagamento — testado: nem depois de uma
cobrança aprovada no mesmo cartão.

Falta decidir entre:

1. **Pedir ao suporte do Mercado Pago para habilitar ESC** nesta conta — só
   assim a cobrança mensal continua de verdade automática;
2. **Redesenhar o ciclo 2+ para pedir ação do aluno** a cada mês — o mesmo
   modelo já aceito para Pix/boleto (ver abaixo), estendido também ao cartão
   se o ESC não vingar.

Sem uma das duas, **a assinatura por cartão cobra o ciclo 1 e trava no ciclo
2** — falha que não aparece no ato da compra, só um mês depois.

### Decidido, e não implementado: Pix e boleto na assinatura

O usuário pediu Pix/boleto como opção na assinatura e confirmou o modelo:
**gerar uma fatura nova a cada ciclo** (Pix/boleto não tem cobrança automática
recorrente no Mercado Pago — o aluno precisa agir a cada ciclo, como uma
conta normal). Mesmo modelo do Asaas (`invoiceUrl`). **Nada disto foi
codado ainda** — ficou registrado aqui para não se perder:

- `subscribe.php` precisa de uma escolha de meio de pagamento (cartão vs
  Pix/boleto), não só os três modos de captura de cartão;
- para Pix/boleto, `charge_first_cycle()` (e o equivalente no ciclo 2+) cria
  um pagamento com `payment_method_id` `pix` ou `bolbradesco` em vez de
  tokenizar cartão, e mostra o QR code / linha digitável;
- o aluno precisa ser notificado a cada ciclo (e-mail ou notificação do
  Moodle) com o link para pagar — não há tela em que ele já esteja parado
  esperando, como no ciclo 1.

### Não testado ainda nesta rodada: as outras duas ofertas

A Fase 5 original previa comprar três tipos de oferta. Só a **assinatura**
foi exercitada nesta rodada (repetidamente, por causa dos bugs). Faltam:

- **Certificado** (`13`) — Checkout Pro, `marketplace_fee`, avulso;
- **Curso avulso** (`2`) — Checkout Pro, idem.

Nenhuma delas depende dos bugs corrigidos aqui (são do fluxo de `preferences`,
não de `bricks`), mas nenhuma foi comprada de verdade ainda.

### Pendências de UI anotadas, não corrigidas

- **Layout dos campos do cartão no modo `direct`** não acompanha o tema
  escuro, e a altura está errada. Ficou pendente porque o modo ativo agora é
  `brick` (o Card Payment Brick tem o próprio layout, mantido pelo Mercado
  Pago) — só importa se `direct` voltar a ser usado.
- A tela está em **inglês** porque o site está; as strings `pt_br` existem.

### Em aberto, para rodadas próprias

- **Cobrança B2B** (`paymentarea 'plan'`).
- **Vínculo pelo próprio vendedor**: a capability já é checada no contexto da
  conta; falta papel e ponto de entrada, no `local_marketplace`.
- **Liberação do `advanced_payments`** no suporte do MP.
- **Cancelar assinatura**: implementado, sem prova — depende de uma compra
  aprovada.
- **Testar o ciclo 2** (mover `timecreated` para trás e rodar
  `charge_due_cycles` sem esperar 30 dias) — bloqueado pela decisão do ESC
  acima; testar antes disso só reproduziria o erro já conhecido.

---

## Contexto

O modelo de negócio depende de **assinatura com split**: venda recorrente de
cursos, módulos e certificados, com a comissão da plataforma saindo de cada
ciclo. Hoje o Mercado Pago é o gateway de venda avulsa e o Asaas é o único com
assinatura, o que custa caro — a taxa de Pix do Asaas é **fixa em R$ 1,99**, ou
39,8% numa venda de R$ 5,00, contra R$ 0,05 do MP
([`comparacao-medida.md`](../gateway-pay/comparacao-medida.md)).

A afirmação que sustenta esse arranjo é o
[ADR-0001](../adr/0001-gateways-alem-do-mercado-pago.md): `POST /preapproval`
aceita `marketplace_fee` e **descarta em silêncio**. A medição de 08/09/2026 é
real, mas foi feita com **a aplicação de Checkout Transparente** — e no Mercado
Pago o modelo declarado da aplicação muda o comportamento sem avisar. Já
custou uma rodada aqui: declarar "Checkout Transparente" faz o `marketplace_fee`
da preferência ser ignorado sem erro.

Existe agora uma aplicação nova, do tipo **Assinaturas**, na conta CNPJ da
plataforma (`client_id 6990306155285574`, credenciais em
`docs/private/mercadopago.txt`). A pergunta do ADR-0001 volta a ficar em aberto
**para o tipo certo de aplicação**, e é ela que este plano mede antes de
escrever qualquer linha de código.

Junto vem uma limitação estrutural a resolver: o plugin hoje conhece **uma**
aplicação (`clientid`/`clientsecret` em `settings.php`) e guarda **um** token por
conta de pagamento. Aplicação no MP é por tipo de integração, e cada uma exige
OAuth próprio. Sem plugin multi-aplicação não há como a mesma empresa vender
avulso por Preferências e assinatura por Assinaturas.

**Restrição que não se negocia:** dado de cartão não entra no banco do Moodle,
em hipótese alguma. Quem guarda o cartão é o gateway; o Moodle guarda no máximo
o identificador que o MP devolve.

### São DUAS assinaturas diferentes, e só uma precisa de split

Levantado com o usuário em 15/09/2026, e confirmado no código:

| | **B2B — a empresa paga a LDG** | **B2C — a parceira vende ao aluno** |
|---|---|---|
| Quem vende | a **plataforma** | a **empresa parceira** |
| Quem recebe | a LDG, **100%** | a parceira, menos a comissão |
| Split | **não existe** — não há terceiro | **obrigatório** |
| Onde mora | `local_marketplace_plan.monthlyfee` | `offer` com `accessmode = recurring` |
| Estado hoje | **não cobra nada** | avulso funciona; recorrente é este plano |

A primeira linha muda o desenho, e para melhor: **assinatura sem split não
depende de nada que a Fase 1 vá medir**. O `preapproval` do Mercado Pago serve à
mensalidade da empresa **hoje**, com cobrança automática e cartão guardado no MP,
mesmo que M2 e M3 confirmem de novo que ele não leva comissão. A aplicação de
Assinaturas deixa de ser uma aposta e passa a ter uso garantido.

E a segunda linha revela uma lacuna: o docblock de
`local_marketplace/classes/plan.php` diz, com todas as letras, que o plano **"NÃO
cobra nada: a mensalidade é informativa até existir a paymentarea 'plan' no
service_provider"**. Conferido: `service_provider::get_payable()` ignora o
`paymentarea` e trata o `itemid` como oferta. **A cobrança B2B não existe em
lugar nenhum do código.**

**Decisão (15/09/2026): esta rodada entrega só o gateway.** O
`paygw_mercadopago` fica capaz de assinatura — com split e sem — e de múltiplas
aplicações. A `paymentarea 'plan'`, a tela de contratação e o ciclo da
mensalidade viram um plano próprio, construído sobre um gateway já provado. O
critério de erro do [ADR-0001](../adr/0001-gateways-alem-do-mercado-pago.md)
continua valendo: se esta rodada precisar mexer em `local_marketplace`, a
abstração não é a certa.

**Consequência para a Fase 1:** M2 e M3 deixam de ser bloqueantes. Qualquer que
seja a resposta, o caminho B2B existe; o que está em jogo é só o B2C.

## Decisões já tomadas (sessão de 15/09/2026)

| Pergunta | Decisão |
|---|---|
| Como provar o split | **Sandbox primeiro**; dinheiro real só no formato que sobreviver à Fase 1 |
| Plano B se assinatura não levar split | **Recorrência própria com cartão salvo no MP**: ciclo 1 pago pelo aluno, ciclos seguintes por `/v1/payments` com `application_fee` |
| Aplicações no painel | **Assinaturas** (`6990306155285574`) e **Checkout Bricks** (`2598194068751669`), ambas já criadas na conta da plataforma |
| OAuth | **Uma autorização por aplicação**; a conta de pagamento guarda um token por tipo, e a oferta escolhe qual usar |
| SDK oficial | **Estudado e espelhado**, não adotado: o `mp_client` continua sendo o único ponto de contato |
| `/v1/advanced_payments` | **Medir e considerar adotar** — o que torna o [ADR-0003](../adr/0003-quem-cria-a-cobranca-emite-a-nota.md) revisável nesta rodada |

**O Bricks entrou no lugar do Checkout Transparente puro, e é uma troca melhor.**
Bricks é a UI do MP por cima da mesma Checkout API: o backend do Plano B continua
sendo `/v1/payments` com `application_fee`, mas a tokenização do cartão passa a
acontecer no componente do próprio Mercado Pago. O PAN não toca o servidor do
Moodle nem o nosso JavaScript — o que a restrição de cartão exige, de graça. A
aplicação de Transparente puro só passa a ser necessária se M4 mostrar que o
Bricks não tokeniza cartão **salvo**.

Sobre a skill `moodle-plugin-development`: ela vem de um livro de **Moodle 3.x**.
Vale o raciocínio arquitetural (extend-never-patch, encapsular para cron, os
reflexos de segurança, `version.php` ou nada acontece). **Assinatura de API vem
da árvore do 5.2**, nunca do livro.

---

## O que o SDK oficial já respondeu

[`mercadopago/sdk-php`](https://github.com/mercadopago/sdk-php) (master, ativo em
14/09/2026) é a modelagem autoritativa dos recursos da API. Ler as classes de
recurso responde, de graça, parte do que a bateria ia medir — e **muda o peso das
medições que sobram**.

**1. `PreApproval` não tem campo de split, e nem o `AutoRecurring`.**
`Resources/PreApproval.php` declara `id`, `payer_id`, `payer_email`, `back_url`,
`collector_id`, `application_id`, `status`, `reason`, `external_reference`,
`init_point`, `preapproval_plan_id`, `auto_recurring`, `summarized`,
`next_payment_date`, `payment_method_id`, `card_id`, `first_invoice_offset`.
`PreApproval/AutoRecurring.php` declara `currency_id`, `transaction_amount`,
`frequency`, `frequency_type`, `start_date`, `end_date`,
`billing_day_proportional`, `has_billing_day`, `free_trial`. **Nenhum campo de
taxa em lugar nenhum.** Isso corrobora o ADR-0001 com muito mais força que a
documentação. Não dispensa M2 — modelo de SDK atrasa em relação à API, e a
pergunta aqui é justamente se a aplicação do tipo Assinaturas muda o
comportamento —, mas rebaixa M2 de "descoberta" para **confirmação**.

**2. `PreApproval` tem `card_id` e `payment_method_id`.** A assinatura do MP
**amarra um cartão guardado no MP**. É a primeira evidência de que o Plano B
não precisa inventar armazenamento: o próprio produto de assinatura já vive
desse mecanismo.

**3. `Payment.application_fee` existe, e o SDK o documenta como "fee charged by
the marketplace to the seller on this payment".** É o campo do Plano B,
confirmado no modelo, e não só na página de documentação.

**4. Achado que não estava em nenhuma busca: `/v1/advanced_payments`.**
`Resources/AdvancedPayment.php` tem `disbursements[]`, e cada
`AdvancedPayment/Disbursement` carrega `collector_id`, `amount`,
`external_reference`, **`application_fee`**, `money_release_date` e `status`.
É split **1:N**, com comissão por recebedor, mais `capture`, `cancel` e
`updateReleaseDate` (`POST /v1/advanced_payments/{id}/disburses`). Vira M7.

**5. `PreApprovalClient::update()` é `PUT /preapproval/{id}`** — é por onde se
cancela, pausa e troca o cartão de uma assinatura. O `mp_client` hoje só faz GET
e POST, o que confirma a necessidade do `PUT`.

**Consequência para o código:** o SDK entra como **fonte de nomes de campo e de
rotas**, citado no docblock do `mp_client`, e **não** como dependência. A razão
que o arquivo já dá continua valendo e ficou mais forte: o `curl` do Moodle
respeita proxy e timeout do site, e o `make_curl()` sobrescrevível é o que mantém
o teste do `application_fee` — o único número que move dinheiro — fora da rede.
A contrapartida é uma obrigação nova: **reconferir as classes do SDK a cada
rodada de gateway**, e registrar a data em que foram lidas.

---

## Fase 0 — Preparo

```bash
git -C dev fetch -q upstream MOODLE_502_STABLE
git -C dev rev-list --count origin/dev..upstream/MOODLE_502_STABLE   # zero, siga
moodev ls                                                            # ver o offset 0
moodev new paygw-mp-assinatura --from origin/dev
```

Túnel `cloudflared` com hostname fixo `mp.leodg.dev` apontando para a porta HTTP
da worktree; `$CFG->wwwroot` e `$CFG->sslproxy = 1` conforme
[`mercadopago-split.md`](../data-validation/mercadopago-split.md). `localhost`
não serve nem para `redirect_uri` nem para `notification_url`.

No painel do MP, **na conta da plataforma** (cadastrar na do vendedor devolve
`invalid redirect_uri` sem dizer qual aplicação foi consultada):

| Aplicação | Modelo | Redirect URI | Webhook |
|---|---|---|---|
| `2401225442871147` | API de Preferências | `…/oauth_callback.php` | `…/webhook.php`, evento `payment` |
| `6990306155285574` | **Assinaturas** | `…/oauth_callback.php?apptype=subscriptions` | `…/webhook.php`, eventos `subscription_preapproval` e `subscription_authorized_payment` |
| `2598194068751669` | **Checkout Bricks** | `…/oauth_callback.php?apptype=bricks` | `…/webhook.php`, evento `payment` |

> **As credenciais de teste das duas aplicações novas não são simétricas, e isso
> morde.** As do Bricks trazem prefixo `TEST-` e pertencem à conta da plataforma
> (`3675841384`); as de Assinaturas trazem prefixo `APP_USR-` e pertencem a outro
> id (`3672982509`). São três partes no split — comprador, vendedor e a
> **aplicação** —, e misturá-las devolve "uma das partes é de teste" sem dizer
> qual. **M1 confere o dono de cada token antes de qualquer cobrança.**

---

## Fase 1 — A bateria de medição (antes do código)

Script novo, irmão do que já existe:
`docs/data-validation/scripts/provar-assinatura-mercadopago.py`. Reusa de
[`provar-split-mercadopago.py`](../data-validation/scripts/provar-split-mercadopago.py)
o PKCE (`cmd_autorizar`), a troca de código (`cmd_trocar`) e — sobretudo — a
**guarda de aborto**: se `collector_id == dono da aplicação`, o script para. Foi
assim que o split pareceu funcionar da primeira vez, com vendedor e marketplace
na mesma conta.

Cada medição abaixo tem uma resposta que a derruba. Medição sem critério de
falha não mede nada.

**M1 — de quem é cada aplicação.** `GET /users/me` com o token de **cada** uma
das três, produção e teste. Tem que devolver o `user_id` da plataforma
(`3675841384`, CNPJ) para a ponta que recebe a comissão. Outro id significa que a
comissão nunca voltaria para nós, e o resto da bateria seria lido ao contrário.
É aqui que a assimetria das credenciais de teste aparece, antes de custar uma
rodada. *Mesma lição do [ADR-0010](../adr/0010-vendedor-pessoa-fisica-no-mercado-pago.md): confira o tipo da conta pela API antes de desenhar a rodada.*

**M2 — o `preapproval` honra algum campo de split?** `POST /preapproval` com o
token do **vendedor** obtido por OAuth da app de Assinaturas, um candidato por
vez, e `GET` logo depois comparando o corpo devolvido:

- `marketplace_fee` na raiz
- `application_fee` na raiz
- `marketplace` na raiz
- os mesmos dentro de `auto_recurring`
- e o mesmo conjunto em `POST /preapproval_plan`

Critério: **o campo volta no `GET`?** Nenhum voltando, está descartado em
silêncio — o mesmo resultado do ADR-0001, agora com a aplicação do tipo certo, e
aí a resposta vira definitiva em vez de suspeita.

**M3 — a pergunta que o ADR-0001 não fez.** O `preapproval` não é onde o
dinheiro se divide; o **pagamento do ciclo** é. Com uma assinatura autorizada e
`auto_recurring.frequency = 1 / frequency_type = days` (para não esperar mês
nenhum), medir o pagamento gerado: `fee_details` traz `type: application_fee`?
Só esse campo responde. É a diferença entre "a API aceitou" e "o dinheiro se
dividiu".

**M4 — o Plano B, e é o que decide.** Cartão guardado no MP, cobrança nossa:

1. `POST /v1/customers` na conta do **vendedor**
2. `POST /v1/customers/{id}/cards` com o `card_token` gerado **no navegador**
   pelo Card Payment Brick — o PAN nunca passa pelo servidor do Moodle nem pelo
   nosso JavaScript
3. `POST /v1/payments` com o cartão salvo **+ `application_fee`**, usando o token
   do vendedor emitido pela aplicação **Bricks**
4. **uma segunda cobrança, dias depois, sem CVV** — é ela, e só ela, que prova
   débito automático

Critérios: (a) a segunda cobrança exige `security_code`? (b) `fee_details` traz
`application_fee` nas **duas**? (c) o Brick tokeniza cartão **já salvo**, ou só
cartão novo? Um "sim" em (a) mata o Plano B e devolve o assunto para o Asaas; um
"não" em (b) diz que o `application_fee` depende de qual aplicação emitiu o
token; um "só cartão novo" em (c) obriga a criar também a aplicação de Checkout
Transparente puro.

**M5 — escopo do OAuth por aplicação.** O token do vendedor emitido pela app de
Assinaturas consegue criar `preapproval` em nome dele? E o da app de Preferências
consegue? Se um token servir para tudo, a Fase 3 encolhe bastante.

**M7 — o split 1:N do `/v1/advanced_payments`.** `POST` com dois
`disbursements`: um para o vendedor e um para a plataforma, cada um com o seu
`application_fee`. Medir: (a) a API aceita com o token de quem? (b) o
`money_release_date` por recebedor funciona? (c) dá para combinar com cartão
salvo, fechando recorrência **e** split num só mecanismo?

> **O que ele custa, e a decisão é sua.** Quem cria o advanced payment é a
> **plataforma**, não o vendedor — e o [ADR-0003](../adr/0003-quem-cria-a-cobranca-emite-a-nota.md)
> diz que a cobrança nasce na conta do vendedor por **regra fiscal**: a
> plataforma não emite nota por outra empresa. Adotar este caminho exige uma
> resposta fiscal do seu lado, e o ADR passa a precisar de revisão datada. A
> medição roda de qualquer forma; a adoção espera essa resposta.

**M6 — o que o webhook recebe.** Quais tópicos cada aplicação envia de verdade
(`payment`, `subscription_preapproval`, `subscription_authorized_payment`), e se
o header `x-signature` chega — o segredo já existe em `docs/private/mercadopago.txt`.

**Saída da Fase 1:** `docs/data-validation/mercadopago-assinatura.md`, roteiro
repetível com id de cada chamada anexado. Sem ids, não é prova.

> **Só se passar:** repetir M3/M4 com dinheiro real, R$ 5,00, duas contas
> distintas, conferindo `fee_details` **e o extrato dos dois lados**. O sandbox
> do Checkout Pro já entrou em loop em `/login/wallet/` e não gerou pagamento
> nenhum; `fee_details` diz o que foi cobrado, extrato diz o que chegou.

---

## Fase 2 — Documentação antes do código

Não é disciplina: o que se descobre investigando se perde se ficar na cabeça de
quem investigou ([`padrao-de-implementacao.md`](../dev/padrao-de-implementacao.md)).

- **`docs/adr/0001-…`** — nova seção `## Correção de 2026-09-15`, acrescentada ao
  fim. O padrão daqui é **acrescentar datado**, nunca reescrever o histórico.
- **`docs/adr/0012-uma-aplicacao-por-tipo-de-integracao.md`** — por que a
  credencial deixa de ser única e vira um registro tipado, e por que o conjunto
  de tipos é **fechado** (o código ramifica por tipo; lista aberta fingiria uma
  generalidade que não existe).
- **`docs/gateway-pay/comparacao-medida.md`** — a linha "Split em assinatura:
  **impossível**" e a seção de limitações do MP, conforme a medição.
- **`docs/adr/0003-…`** — só se M7 passar **e** você responder a questão fiscal:
  seção `## Revisão de 2026-…` explicando o que mudou. Enquanto não houver as
  duas coisas, o ADR fica como está e o advanced payment fica registrado como
  alternativa medida e não adotada.
- **`public/payment/gateway/mercadopago/README.md`** e **`CLAUDE.md`** (a tabela
  de restrições externas e a seção "Estado atual").

---

## Fase 3 — Implementação

### 3a. Uma aplicação por tipo de integração

**`classes/application.php`** (novo) — dono do conjunto fechado de tipos e das
credenciais de cada um:

```php
const TYPES = ['preferences', 'subscriptions', 'bricks'];
public static function credentials(string $type): ?\stdClass;   // clientid, clientsecret, site
public static function token_field(string $type): string;       // accesstoken | accesstoken_<type>
```

**Sem migração de configuração, de propósito.** O tipo `preferences` continua
lendo `clientid`/`clientsecret`, os nomes que já estão no ar; os novos leem
`clientid_subscriptions` e `clientid_bricks`. O mesmo vale para o token na
conta: `accesstoken` segue sendo o de Preferências, e os outros ganham sufixo.
Um passo de upgrade que renomeasse config é risco puro por zero ganho — e um
passo que "roda sem fazer nada" é a família de defeito que o `db_schema_test`
existe para pegar.

Mudanças por arquivo:

| Arquivo | O quê |
|---|---|
| `settings.php` | um bloco por tipo, gerado em laço sobre `application::TYPES`; `configpasswordunmask` no secret, como já é hoje |
| `oauth_start.php`, `oauth_callback.php`, `oauth_unlink.php` | `apptype` como `PARAM_ALPHANUMEXT`, validado contra `TYPES`; viaja dentro do `state`, que já existe |
| `classes/gateway.php` | `describe_oauth_status()` passa a listar **uma linha por tipo configurado**; os campos escondidos (`accesstoken`, `refreshtoken`, …) viram um conjunto por tipo — campo ausente no form é **apagado** ao salvar, e essa armadilha já está documentada no arquivo |
| `classes/task/refresh_tokens.php` | itera os tipos |
| `classes/privacy/provider.php` | declara as colunas novas |

`validate_gateway_form()` continua recusando habilitar sem token, e ganha a
regra nova: recusar quando a conta declara vender assinatura e **não** tem o
token do tipo capaz de assinatura. Melhor recusar na tela do que o aluno
descobrir no checkout.

### 3b. Recorrência (escrito para o Plano B; a Fase 1 confirma ou troca o motor)

**Tabela.** `db/install.xml` + `db/upgrade.php` — **uma linha por ciclo**, como
no `paygw_asaas`, ligadas por `subscriptionid`: cada ciclo é um pagamento com a
sua própria comissão fotografada.

| Coluna | Para quê |
|---|---|
| `apptype` | qual aplicação criou esta linha; sem ela não se sabe com que token consultar |
| `subscriptionid` | o `preapproval_id`, ou o id da assinatura nossa no Plano B |
| `cycles` | ciclo a que a linha corresponde |
| `mpcustomerid`, `mpcardid` | **identificadores do MP**, nunca dado de cartão |
| `paymentmethod` | o que o aluno usou; decide se há débito automático |

Sem guarda `table_exists()` antes de `add_field` — a tabela é do próprio plugin,
e a guarda só faz nome errado passar calado. Depois: `php admin/cli/check_database_schema.php`,
que tem que dizer `Database structure is ok.`

**`classes/mp_client.php`** — todo HTTP continua passando por aqui, pela razão
que o docblock do arquivo já dá. Métodos novos, com nome de campo e rota
**copiados do SDK oficial**, e o docblock dizendo qual classe do SDK sustenta
cada um: `create_preapproval` / `get_preapproval` / `update_preapproval`
(`PUT /preapproval/{id}` — é por onde se cancela, pausa e troca o cartão),
`create_customer`, `save_card`, `create_payment`, `search_customer` e, se M7
passar, `create_advanced_payment`. O `request()` hoje só faz GET e POST: precisa
de `PUT`, e o `make_curl()` sobrescrevível mantém tudo isso testável sem rede.

**`classes/payment_processor.php`** — `start_payment()` ramifica por
`\local_marketplace\api::recurrence_for()`, exatamente como
`asaas/classes/payment_processor.php:145`, inclusive o `class_exists()` que
mantém o plugin servindo a qualquer componente do `core_payment`.
`process_notification()` passa a tratar os tópicos de assinatura, e a adoção do
ciclo N copia **contexto e termos da linha anterior** — nunca resolve a comissão
de novo ([ADR-0007](../adr/0007-comissao-sobre-o-bruto.md)).

**`classes/gateway.php`** — implementa os métodos do contrato que hoje estão
vazios no MP: `cancel_recurring`, `pending_invoice`, `refund`, `refund_blocker`.
As assinaturas são as do `paygw_asaas` (`classes/gateway.php:220-325`), porque o
núcleo chama por `component_class_callback` e não sabe o nome de gateway nenhum.

**`classes/task/charge_due_cycles.php`** (novo, só no Plano B) — cobra o ciclo
vencido com o cartão guardado no MP. Toda a lógica em classe testável; a tarefa
só orquestra.

**`amd/src/bricks_card.js`** (novo, só no Plano B) — monta o Card Payment Brick
dentro do modal de pagamento e devolve **apenas** o `card_token` ao servidor.
Duas armadilhas já registradas nesta base: não há transpilador, então o `src`
precisa ser **AMD de verdade**; e com `cachejs` ligado o Moodle serve
`amd/build/`, de modo que módulo sem build simplesmente não roda — sem erro no
console e sem pista. `npx grunt amd` **no host**, porque o node do container é
v20 e o 5.2 exige v22.

### 3c. Segurança

- **Cartão:** `card_token` gerado no navegador pelo SDK do MP. O servidor do
  Moodle vê `customer_id` e `card_id`, e nada mais. Isso vai no docblock do
  `privacy\provider`, porque é exatamente o tipo de decisão que alguém desfaz
  por engano seis meses depois.
- **Webhook:** hoje `webhook.php` **não valida `x-signature`** — ele se defende
  reconsultando o pagamento na API, o que já impede um `POST` forjado de liberar
  acesso. Mas com assinatura entram tópicos novos, e validar o header é barato:
  HMAC-SHA256 sobre o manifesto, comparado com `hash_equals()`, segredo em
  `admin_setting_configpasswordunmask`. Falha de assinatura responde 401 e
  **não** consulta a API.
- `require_sesskey()` nas ações destrutivas (`oauth_unlink.php` já tem; as novas
  seguem igual), `optional_param` tipado em tudo que vem da URL.

---

## Fase 4 — Testes

TDD: **teste vermelho antes do código**, em português, com nome que diz a regra.

| Arquivo | Prova |
|---|---|
| `tests/application_test.php` (novo) | tipo desconhecido é recusado; `preferences` lê os nomes legados |
| `tests/mp_client_test.php` | os corpos novos, com `fake_curl` — o `application_fee` é o número que move dinheiro, e o motivo de `make_curl()` ser sobrescrevível |
| `tests/payment_processor_test.php` | ramo de assinatura; comissão por ciclo saindo **da linha**; idempotência do webhook reenviado; ciclo N herdando os termos do anterior |
| `tests/webhook_signature_test.php` (novo) | assinatura inválida não chega a consultar a API |
| `local_marketplace/tests/db_schema_test.php` | já cobre os dez plugins; roda de graça |
| `tests/behat/settings.feature` | duas aplicações na tela; trava de habilitar sem o token do tipo declarado |

```bash
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  php vendor/bin/phpunit --testsuite paygw_mercadopago_testsuite
docker exec -u 1000:33 ldg-courses-moodle-1 \
  phpcs --standard=moodle -p --report=summary public/payment/gateway/mercadopago
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 npx grunt
```

O phpcs se lê **inteiro** — o CI roda com `--max-warnings 0`, e já se reportou
"zero violações" com 16 erros presentes. Strings novas em `en`, `pt_br` e `es`,
com o **mesmo conjunto de chaves**, e o arquivo reordenado alfabeticamente
depois de acrescentar. Feature behat nova precisa de
`php public/admin/tool/behat/cli/util.php --enable`. `version.php` sobe.

---

## Fase 5 — Prova de ponta a ponta

Pela vitrine, com o webhook chegando sozinho — como em 08/09/2026:

1. oferta `recurring` comprada pelo Moodle, ciclo 1 pago
2. direito de acesso criado e matrícula pelo `enrol_marketplace`
3. ciclo 2 cobrado **sem o aluno tocar em nada** (o que nenhum gateway daqui
   provou até hoje)
4. `application_fee` em cada ciclo, conferido nos **dois** extratos
5. cancelar no Moodle para de cobrar no MP (`api::stop_recurring_billing`)
6. vencimento suspende a matrícula **por diferença**; pagar o atrasado devolve

Para exercitar o ciclo não se espera mês nenhum: move-se o `timeend` do direito
e roda-se a tarefa.

---

## Arquivos críticos

```
docs/data-validation/scripts/provar-assinatura-mercadopago.py   (novo)
docs/data-validation/mercadopago-assinatura.md                  (novo)
docs/adr/0012-uma-aplicacao-por-tipo-de-integracao.md           (novo)
docs/adr/0001-gateways-alem-do-mercado-pago.md                  (seção nova, datada)
docs/gateway-pay/comparacao-medida.md
public/payment/gateway/mercadopago/classes/application.php      (novo)
public/payment/gateway/mercadopago/classes/mp_client.php
public/payment/gateway/mercadopago/classes/payment_processor.php
public/payment/gateway/mercadopago/classes/gateway.php
public/payment/gateway/mercadopago/classes/task/charge_due_cycles.php (novo)
public/payment/gateway/mercadopago/amd/src/bricks_card.js               (novo)
public/payment/gateway/mercadopago/{settings,webhook,oauth_*}.php
public/payment/gateway/mercadopago/db/{install.xml,upgrade.php,tasks.php}
public/payment/gateway/mercadopago/lang/{en,pt_br,es}/paygw_mercadopago.php
```

Nada em `local_marketplace` deve precisar mudar. **Se precisar, a abstração não é
a certa e o [ADR-0001](../adr/0001-gateways-alem-do-mercado-pago.md) tem que ser
revisitado** — é o critério de erro que ele próprio declara.

## Verificação

`check_database_schema.php` limpo · suite do plugin verde · phpcs sem erro **e
sem aviso**, lido por inteiro · `grunt` limpo · behat sem JS e depois
`--profile=chrome` · o roteiro da Fase 5 executado com ids anexados.

Só então: commit → PR para `dev` → CI verde → merge. **Nessa ordem**, e com nada
pendente ao abrir o PR.

## Em aberto, de propósito

- **Vendedor pessoa jurídica no MP** continua sem prova; segue fora desta rodada.
- **Boleto liquidando** e **estorno parcial reduzindo o split** no MP.
- A Fase 3b assume o Plano B. Se M2/M3 mostrarem que o `preapproval` honra
  split, o motor muda para `preapproval` e a tabela de colunas encolhe —
  `charge_due_cycles` deixa de existir, porque quem cobra passa a ser o MP.
- **A adoção do `/v1/advanced_payments` depende de uma resposta fiscal sua**, não
  da medição. M7 roda e registra o resultado; a decisão de trocar quem emite a
  cobrança fica em aberto até lá.
- **Reconferir as classes do SDK a cada rodada de gateway.** Lidas em
  15/09/2026, no `master`. Espelhar sem reconferir é como o `mp_client` ficaria
  desatualizado em silêncio — a mesma forma de falha que este plano existe para
  evitar.
