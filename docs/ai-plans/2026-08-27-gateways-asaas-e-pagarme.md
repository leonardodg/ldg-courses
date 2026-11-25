# Gateways além do Mercado Pago — Asaas entregue, Pagar.me em espera

**Data:** 2026-08-27 · **Agente:** Claude Code (Opus 5)
**Resultado:** parcial — PRs [#53](https://github.com/leonardodg/courses-free/pull/53),
[#55](https://github.com/leonardodg/courses-free/pull/55),
[#56](https://github.com/leonardodg/courses-free/pull/56),
[#57](https://github.com/leonardodg/courses-free/pull/57),
[#58](https://github.com/leonardodg/courses-free/pull/58),
[#59](https://github.com/leonardodg/courses-free/pull/59) merjeadas.
**Pagar.me não começou** — falta CNPJ para abrir conta.

> Este é o **registro do ciclo**. O plano que o originou é o
> [`2026-08-27-plano-original-asaas-e-pagarme.md`](2026-08-27-plano-original-asaas-e-pagarme.md),
> e é lá que a **Fase 2 do `paygw_pagarme` está escrita por inteiro**, esperando
> o CNPJ. Quem for retomar o Pagar.me começa por ele, não por aqui.

---

## Contexto

O split é o coração deste modelo e **nunca tinha sido visto funcionar**. No
Mercado Pago, vendedor e marketplace foram a mesma conta no teste: o
`marketplace_fee` não transferiu nada e **não houve erro**, o que é o pior tipo
de falso positivo.

O pedido: implementar Pagar.me e Asaas, em worktrees isoladas, revisando de
passagem as decisões existentes. Com um requisito que moldou tudo — *"cada
gateway é configurado por país, e eu como administrador vou ter várias contas de
gateways diferentes, às vezes o mesmo gateway em países diferentes"*.

Havia ainda um problema estrutural: o núcleo conhecia o Mercado Pago pelo nome em
quatro lugares (`api.php:135`, `company.php:134`, `report.php:115`,
`mysubscriptions.php:160`). Com um segundo gateway, o relatório passaria a
**mentir por omissão** — a venda existiria, o aluno estaria matriculado, e o
total simplesmente não contaria aquele dinheiro.

## Decisões

| Decisão | Alternativa recusada | Por quê |
|---|---|---|
| **Asaas antes do Pagar.me** | seguir a ordem pedida | No Pagar.me o split em Pix só existe no `POST /orders` transparente; o Link de Pagamento não faz split em Pix nem boleto. Começar por ele exigiria QR Code próprio e polling **antes** de a primeira prova de split existir. |
| **Fase 0 antes dos gateways** | refatorar depois | O relatório mentiria em silêncio no intervalo, e "depois" chega quando alguém reclama de um número errado. |
| **País ISO-3166 na oferta** | `siteid` do MP, aproveitando um `git stash` pronto | Asaas e Pagar.me só existem no Brasil e não têm código de site. O núcleo falando `MLB` obrigaria o `paygw_asaas` a aprender o vocabulário do concorrente. |
| **Moeda derivada do país**, em `before_validate()` | validar a moeda escolhida no formulário | Derivar torna "BRL vendendo na Argentina" **impossível**, não apenas inválido. Validar dependia de o vendedor já ter vinculado a conta. |
| **Vendedor cria a cobrança** | plataforma como merchant of record | Regra fiscal do usuário: a plataforma não emite nota por outra empresa. Recusou o modelo de marketplace nativo do Asaas/Pagar.me, que é **mais simples de integrar**. |
| **Credencial cifrada, ambientes lado a lado** | texto puro, como o token do MP | Uma API key não expira sozinha e dá acesso amplo à conta bancária. Guardar homologação e produção juntas impede usar chave de teste em produção por engano. |
| **Credencial gravada fora do formulário do core** | usar o formulário de gateway | Ele serializa em `config` tudo que devolve: a chave ficaria em claro no JSON e voltaria à tela a cada edição. |
| **Tabela `local_marketplace_sale`** | uma tabela por gateway, somadas no relatório | O relatório voltaria a conhecer o nome de cada gateway. |
| **Ler só `{payments}` do core** foi descartado | — | Falta lá a comissão retida e o id da transação, e recalcular o percentual diverge do extrato. |
| **Cohort adiado** | cohort agora, como estrutura principal | Cohort é binário, direito de acesso vence: perguntas diferentes que divergiriam em silêncio. E `enrol_cohort` brigaria com o `enrol_marketplace`, que matricula por diferença. |
| **Baixa manual entrega o curso e registra R$ 0,00** | não entregar | O aluno pagou; negar acesso o puniria pelo que o vendedor fez. Matrícula sem comissão não é problema a resolver. |

## O que mudou

**Fase 0 — núcleo (PR #53):**
- criados `classes/country.php`, `classes/company_account.php`, `classes/sale.php`
- tabelas `local_marketplace_account` e `local_marketplace_sale`; `offer.country`
- `defaultfeepercent` migrou para as settings do `local_marketplace`; o `?: 25`
  virou comparação com `false`, porque um 0% configurado voltava a 25 em silêncio
- `api::commission_for()` e `api::record_sale()` — pontos únicos para os gateways
- `company::get_payment_account()` passou a exigir o país
- upgrade com backfill: adota contas (`MLB→BR`) e vendas aprovadas do MP

**Fase 1 — `paygw_asaas` (PR #58), 24 arquivos:**
- `asaas_client` com costura de transporte (`make_curl()`), que é o que permite
  testar montagem de corpo e mapeamento de erro sem rede
- `credentials` com `\core\encryption` e ambientes lado a lado
- `link.php`/`unlink.php`, `webhook.php` autenticado, tarefa de conciliação
  horária, AMD à mão, lang en/pt_br/es

**Relatório (PR #55):** aba **Alunos**, saindo do direito de acesso.

**Documentação (PRs #56, #57, #59):** `docs/data-validation/asaas-sandbox.md`
com guia manual e o script `provar-split-asaas.py`; ADRs
[0001](../adr/0001-gateways-alem-do-mercado-pago.md) a
[0004](../adr/0004-cohort-por-empresa-adiado.md); `decisoes-marketplace` §5
marcada como superada; `data-model` e `CLAUDE.md` atualizados.

## Descobertas

Nenhuma destas estava em documentação. Todas custaram uma ida à API real.

| Descoberta | Consequência |
|---|---|
| **Baixa manual cancela o split.** `receiveInCash` → split `CANCELLED`, plataforma recebe zero | Era um **furo de receita**: o plugin entregava o curso e registrava R$ 25,00 que nunca chegariam. `fee_from()` passou a ler o split real |
| **Nome de evento ≠ valor de status.** Existe o status `RECEIVED_IN_CASH`; o evento `PAYMENT_RECEIVED_IN_CASH` **não existe** | A lista de eventos do webhook estava errada. A baixa manual chega como `PAYMENT_RECEIVED` |
| **A URL de retorno exige o mesmo domínio cadastrado na conta que emite a cobrança** | O **vendedor** precisa cadastrar o domínio *da plataforma* — não o site próprio, que é o que se cadastraria por instinto |
| **O Asaas recusa cobrança de cliente sem CPF/CNPJ**, embora crie o cliente sem ele | O campo de perfil com documento passou de opcional a obrigatório |
| **Subconta pessoa física não libera Pix**, mesmo com `/myAccount/status` `APPROVED` em tudo | Subconta de teste precisa ser CNPJ. Com CNPJ, o campo `site` também passa a ser persistido — na PF volta nulo, em silêncio |
| **Conta PF não cria subconta** (`403`) | Só afeta o teste: em produção o vendedor tem conta própria |
| **O split incide sobre o `netValue`** | R$ 100 → R$ 97,52 → 25% = R$ 24,38. Recalcular sobre o bruto diverge do extrato |
| **`POST /accounts` aceita `site` e `webhooks`** | A subconta nasce já configurada, num único chamado |

Uma descoberta de processo, não de API: **empilhar PRs é armadilha**. As #54 e
#55 apontavam para `feature/gateways-nucleo`, que foi merjeada dez segundos
antes — o conteúdo não chegou ao `dev` e precisou da #58 para ser resgatado. O
botão de merge não avisa que a base já foi absorvida. **PR de feature sai direto
contra `dev`.**

## Verificação

**O split, no sandbox, com duas contas distintas:**

```
cobrança ... pay_w1ijat3r74sx4nlp | CONFIRMED
bruto ...... R$ 100,00 | líquido R$ 97,52
SPLIT ...... carteira da plataforma | AWAITING_CREDIT | 25% | R$ 24,38
```

97,52 × 25% = 24,38 — exatamente o que `fee_from()` calcula.

**Ponta a ponta pelo Moodle, com comissão real:**

```
plugin cria a cobrança  →  R$ 49,90, split anexado
cartão fictício         →  CONFIRMED, líquido R$ 48,42
process_notification    →  entregou
reenvio                 →  ignorado
relatório               →  asaas | bruto R$ 49,90 | comissão R$ 12,10
direito de acesso       →  1
matrícula               →  1
```

**A correção do furo, contra as duas cobranças reais:**

```
baixa manual  →  curso entregue, comissão R$  0,00   (split CANCELLED)
cartão pago   →  curso entregue, comissão R$ 12,10   (split AWAITING_CREDIT)
```

**Webhook:** sem token → `401`; token errado → `401`; evento irrelevante →
`200 ignored`; cobrança desconhecida → `200 ignored`; cobrança pendente →
`200 ignored` sem entregar.

**Upgrade** rodado a partir do schema antigo com dados: 15 verificações —
adoção da conta, `MLB→BR`, backfill da venda aprovada, venda pendente ficando de
fora, comissão herdada, e idempotência dos dois passos.

**Números:** 105 testes (63 núcleo, 34 Asaas, 8 MP), `phpcs` zero em 95
arquivos. Telas conferidas com sessão aberta.

## Em aberto

**`paygw_pagarme` não começou.** O Pagar.me exige CNPJ para abrir conta, e o do
usuário está em criação. Decisão deliberada de **não** escrever o plugin sem
sandbox: as oito descobertas acima vieram todas de bater na API real, e nenhuma
estava documentada. Escrever ~3000 linhas às cegas produziria exatamente a
categoria que este projeto chama de *"o código existe, ninguém viu funcionar"*.

A worktree `paygw-pagarme` já está criada (porta 8473, saindo de `dev`), e a
camada comum está estabilizada por um consumidor real — que era a razão de fazer
o Asaas primeiro.

**O que o Pagar.me vai exigir, já levantado:**
- base `https://api.pagar.me/core/v5` e `https://sdx-api.pagar.me/core/v5`, Basic
  auth com `sk_`
- Pix via `POST /orders` com `payments[0].payment_method = 'pix'` + `split[]`;
  a resposta traz `last_transaction.qr_code` e `qr_code_url`
- **página própria de QR Code com polling** — é o custo de o Link de Pagamento
  não fazer split em Pix
- o `recipient_id` da plataforma **é diferente em cada vendedor**, porque
  `recipient` é objeto interno de cada conta. O vendedor cadastra a plataforma
  como recebedor no painel dele e cola o `re_...`
- as regras de split precisam somar 100%: são duas, o recebedor padrão do
  vendedor e o da plataforma
- em assinatura, o split só aceita `percentage`

**Outros itens:**
- o split ficou em `AWAITING_CREDIT`, não `DONE` — falta ver o saldo se mover,
  o que depende da liquidação do cartão
- Pix pago de verdade: a cobrança com split é criada sem problema, mas o sandbox
  não liquida Pix; a prova saiu por cartão fictício
- cifrar também os tokens do `paygw_mercadopago`, hoje em texto puro. Ficou de
  fora por estar em produção e funcionando
- estorno não revoga acesso (`PAYMENT_REFUNDED`,
  `PAYMENT_RECEIVED_IN_CASH_UNDONE`): revogação é decisão de negócio, tomada em
  `entitlement::revoke()`, nunca por automação
- a chave de cifragem em `moodledata/secret/` precisa entrar no backup — sem ela
  as credenciais dos vendedores ficam ilegíveis. Conferido que o `rsync --delete`
  do deploy não a toca: ele mira `${VPS_PATH}/repo`, e o `moodledata` é irmão
- o teste em `courses.leodg.dev` com `usecallback` ligado ainda não foi feito;
  localmente só rodou desligado, por falta de domínio real
