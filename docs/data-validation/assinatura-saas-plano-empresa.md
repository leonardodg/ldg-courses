# Provar a assinatura SaaS: empresa pagando a plataforma

Roteiro para testar, pela tela, o que as Fases A–E implementaram: a empresa
parceira escolhe um plano (Start ou PRO) e paga a **PLATAFORMA** — o oposto da
venda de curso, onde a empresa é quem recebe. Ver o desenho completo em
`docs/ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md` e o plano de
implementação em `docs/ai-plans/steady-wibbling-tiger.md` (worktree `dev`).

> **Nada disto foi clicado ainda.** Todas as fases têm PHPUnit verde, mas
> "verde no teste" não é "provado com dinheiro real" — a mesma distinção que
> `docs/data-validation/mercadopago-split.md` já registra para o split de
> curso.

## Pré-requisito único: a conta da plataforma precisa ter gateway vinculado

A conta nasce sozinha, no primeiro checkout de plano
(`service_provider::get_payable_plan()` chama
`api::get_or_create_platform_account()`), mas nesse momento ela **não tem
gateway nenhum** — o modal de pagamento apareceria vazio para quem clicasse
primeiro. Por isso, criar (ou conferir) a conta ANTES, com o script novo:

```bash
docker exec -u 1000:33 -w /var/www/html/public ldg-courses-moodle-1 \
  php local/marketplace/cli/platform_account.php --country=BR
```

Já rodado nesta worktree (`saas-planos-start-pro`, banco atual). O resultado
real foi:

```
Conta: Platform (BR)
accountid: 9
idnumber: platform_br

Vincule o(s) gateway(s) direto por estes enderecos (exige
login de administrador com moodle/payment:manageaccounts):

  https://localhost:8443/payment/manage_gateway.php?accountid=9&gateway=mercadopago
  https://localhost:8443/payment/manage_gateway.php?accountid=9&gateway=asaas
  https://localhost:8443/payment/manage_gateway.php?accountid=9&gateway=pagarme
```

**Confirmado no banco:** `mdl_payment_accounts.id = 9`, `contextid = 1`
(contexto do SITE — não de categoria nenhuma), e **nenhuma linha** em
`local_marketplace_account` apontando pra ela — exatamente o desenho: conta
sem empresa dona.

### Vincular o Mercado Pago

1. Logado como **administrador do site**, abrir o primeiro link acima
   (`manage_gateway.php?accountid=9&gateway=mercadopago`).
2. Marcar **Enabled**.
3. Clicar em "Vincular conta" (a mesma tela que a empresa usa em
   `company.php`) e completar o OAuth com a conta REAL da plataforma no
   Mercado Pago (a mesma conta CNPJ que já recebe a comissão das vendas de
   curso — ver `docs/data-validation/mercadopago-split.md`). **Não é uma
   conta de teste nova**: é a autorização de produção da própria plataforma,
   porque é ela quem vai efetivamente receber a mensalidade.
4. Salvar.

Asaas e Pagar.me seguem o mesmo padrão, se quiser testar os três — mas o
Pagar.me **nunca vai conseguir cobrar** um plano de verdade, porque toda
assinatura SaaS é recorrente e esse gateway recusa recorrência na porta (ver
o commit da Fase D). Vincular lá serve só para conferir que a guarda de
auto-vínculo deixa passar a conta da plataforma — não para cobrar.

## Login do gerente da empresa

O banco desta worktree já tem empresas reais de sessões anteriores. A mais
completa é a **Ivana Academy** (`ivana-academy`, `companyid = 2`), com
Mercado Pago já vinculado do lado dela (venda de curso).

| Campo | Valor |
|---|---|
| URL do site | `https://localhost:8443` |
| Usuário | `vendedordemo` |
| Senha (redefinida agora, só para este teste) | `LdgTeste2026!` |
| Papel na empresa | `owner` de Ivana Academy |

Senha redefinida com:

```bash
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  php admin/cli/reset_password.php --username=vendedordemo \
  --password='LdgTeste2026!' --ignore-password-policy
```

## Passo a passo do teste

1. Entrar em `https://localhost:8443` com `vendedordemo` / `LdgTeste2026!`.
2. Ir para `https://localhost:8443/local/marketplace/company.php` (sem
   parâmetro — como só há uma empresa para este usuário, ele já cai direto
   em `?company=ivana-academy`).
3. Na seção **Plano**: hoje deve aparecer "Sem plano" (`company.planid` nulo).
4. No seletor, escolher um plano **pago** — `Start — R$50/month` é o mais
   barato para testar de verdade. Clicar em "Selecionar plano".
5. A página recarrega e agora mostra "Plano atual: Start — R$50/month",
   "Ainda não paga" e o botão **"Pay subscription"**.
6. Clicar no botão — abre o modal padrão do `core_payment` (o mesmo da
   compra de curso), listando só os gateways habilitados na conta **9**
   (a da plataforma, não a de Ivana Academy).
7. Escolher Mercado Pago e completar o pagamento com um cartão/Pix real.
8. Ao voltar (`service_provider::get_success_url('plan', ...)` manda para
   `company.php?company=ivana-academy`), conferir:
   - a seção Plano agora mostra **"Subscription paid until"** com uma data
     ~30 dias à frente;
   - o dinheiro caiu na conta **da plataforma** no Mercado Pago, não na de
     Ivana Academy;
   - `company.planexpiry` no banco bate com a data mostrada na tela.

## O que conferir depois, fora da tela

```bash
docker exec -u 1000:33 ldg-courses-db-1 mariadb -umoodle -pmoodle moodle -e \
  "SELECT id, name, shortname, planid, planexpiry FROM mdl_local_marketplace_company WHERE shortname='ivana-academy';"
```

`planid` precisa apontar para o `start_50` (ou o plano escolhido), e
`planexpiry` precisa ser um timestamp ~30 dias no futuro a partir do momento
do pagamento.

```bash
docker exec -u 1000:33 ldg-courses-db-1 mariadb -umoodle -pmoodle moodle -e \
  "SELECT id, component, paymentarea, itemid, accountid, amount, feeamount, status, mppaymentid
   FROM mdl_paygw_mercadopago WHERE paymentarea='plan' ORDER BY id DESC LIMIT 5;"
```

`paymentarea` tem que ser `plan`, `itemid` tem que ser o `companyid` (2, não
um `offerid`), `accountid` tem que ser **9** (a conta da plataforma), e
`feeamount` tem que ser **0** — sem `application_fee` nenhum, porque a
plataforma fica com o valor inteiro.

## Pendências que esta prova NÃO fecha

- Valor exato da mensalidade do PRO (ainda `R$97`, placeholder do seed).
- Ciclo 2 do plano (cobrança automática 30 dias depois) — o motor é o mesmo
  já provado para assinatura de curso (`charge_due_cycles`), mas nunca foi
  exercitado especificamente para `paymentarea = 'plan'`.
- Cancelamento/downgrade de plano pela tela (hoje só existe trocar de tier
  via `<select>`, sem cobrança; não há botão de cancelar a mensalidade).
- Asaas e Pagar.me vinculados de verdade — só o Mercado Pago tem conta de
  produção pronta nesta worktree.
