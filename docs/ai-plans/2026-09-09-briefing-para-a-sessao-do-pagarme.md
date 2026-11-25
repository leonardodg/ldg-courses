# Briefing para a sessão do `paygw_pagarme`

Documento para ser **colado como prompt inicial** da próxima sessão, ou lido por
quem for abri-la. Ele não é o plano: o plano se escreve depois de medir.

---

## O prompt

> Implemente o `paygw_pagarme`, terceiro gateway de pagamento deste projeto,
> seguindo o padrão dos dois que já existem.
>
> **Antes de escrever qualquer linha de código, meça o comportamento da API do
> Pagar.me no sandbox** e escreva o resultado. Duas APIs neste projeto aceitaram
> um campo e o descartaram em silêncio, respondendo `2xx` — o `marketplace_fee`
> no `preapproval` do Mercado Pago e o `creditCard` no `PUT /subscriptions`
> comum do Asaas. Depois de mandar, leia de volta; quando houver dinheiro, leia
> o extrato.
>
> Leia primeiro, nesta ordem: `CLAUDE.md`,
> `docs/ai-plans/2026-09-09-provas-de-pagamento-e-assinatura.md` e
> `docs/ai-plans/2026-08-27-plano-original-asaas-e-pagarme.md`.
>
> Antes de criar a worktree, **sincronize o `dev` com
> `upstream/MOODLE_502_STABLE`** — este projeto acompanha o Moodle em vez de
> forkar, e feature nascida de um `dev` atrasado encontra o merge do upstream
> depois, com o código dela no meio do caminho.
>
> Use worktree própria criada com `moodev new`, TDD, PHPUnit, Behat local com
> `--profile=chrome`, `phpcs --standard=moodle` lendo o total, e documentação em
> `docs/data-validation/` no formato dos roteiros que já existem.
>
> Não abra o PR antes de terminar tudo, e confirme que ele ainda está aberto
> antes de empurrar qualquer commit adicional.

---

## O que ler, e por quê

### Obrigatório antes de começar

| Arquivo | Por quê |
|---|---|
| `CLAUDE.md` | decisões que não se revisitam, restrições externas verificadas, erros já cometidos |
| `docs/ai-plans/2026-09-09-provas-de-pagamento-e-assinatura.md` | **o mais importante**: o que medir antes de codar, o contrato dos seis métodos, e os erros de método a não repetir |
| `docs/ai-plans/2026-08-27-plano-original-asaas-e-pagarme.md` | a Fase 2 do Pagar.me já escrita, esperando CNPJ |
| `docs/ai-plans/2026-08-27-gateways-asaas-e-pagarme.md` | por que o Asaas veio antes, e o que foi feito naquele ciclo |

### Decisões que o gateway precisa respeitar

| ADR | O que fixa |
|---|---|
| `docs/adr/0001-gateways-alem-do-mercado-pago.md` | o núcleo não sabe o nome de gateway nenhum; e a correção de 09/09 sobre o `preapproval` |
| `docs/adr/0003-quem-cria-a-cobranca-emite-a-nota.md` | a cobrança nasce na conta do vendedor — regra fiscal, não arquitetura |
| `docs/adr/0007-comissao-sobre-o-bruto.md` | base configurável, termos fotografados na venda |
| `docs/adr/0010-vendedor-pessoa-fisica-no-mercado-pago.md` | o vendedor não precisa de CNPJ; e a lição de conferir o tipo pela API |

### O molde: `paygw_asaas`

É o gateway mais completo, e o mais próximo do que o Pagar.me vai precisar.
Vale ler inteiro antes de começar:

```
public/payment/gateway/asaas/
├── classes/asaas_client.php          TODO HTTP passa aqui; costura make_curl()
├── classes/credentials.php           chave do vendedor cifrada, ambientes lado a lado
├── classes/gateway.php               os métodos que o núcleo pergunta
├── classes/payment_processor.php     cobrança, webhook, assinatura, estorno
├── classes/form/link_form.php        vínculo fora do formulário do core
├── classes/task/reconcile.php        varredura de pendentes
├── db/{install.xml,services.php,tasks.php,upgrade.php}
├── lang/{en,es,pt_br}/paygw_asaas.php
├── tests/{asaas_client,credentials,payment_processor}_test.php
├── tests/fixtures/{fake_asaas_client,fake_curl}.php
├── {link,unlink,return,webhook}.php
└── README.md
```

Leia também `public/payment/gateway/mercadopago/README.md` — ele documenta as
armadilhas do outro modelo (OAuth, `marketplace_fee`, ausência de recorrência).

E `public/local/marketplace/README.md`, que traz as permissões sobre dinheiro e
a diferença entre cancelar e estornar.

### Roteiros de validação, que são o formato a seguir

| Arquivo | O que ensina |
|---|---|
| `docs/data-validation/asaas-sandbox.md` | prova do split avulso: duas contas, webhook, `curl` passo a passo |
| `docs/data-validation/asaas-assinatura.md` | prova do ciclo sem esperar mês, e as armadilhas achadas |
| `docs/data-validation/mercadopago-split.md` | prova do split com três contas, e por que sucesso sem erro não é prova |
| `docs/data-validation/painel-de-testes.md` | todas as telas por papel, e como repetir os testes |
| `docs/data-validation/scripts/provar-split-asaas.py` | script de prova, stdlib apenas |
| `docs/data-validation/scripts/provar-split-mercadopago.py` | idem, com a guarda que aborta quando `collector_id` é o dono da aplicação |

### Ambiente

`docs/dev/moodev.md`, `docs/dev/guia-worktrees.md`, `docs/dev/behat.md`,
`docs/dev/guia-desenvolvedor.md`.

E `docs/gateway-pay/levantamente.txt`, o levantamento que escolheu o Pagar.me.

---

## O contrato que o gateway precisa cumprir

O núcleo pergunta por `component_class_callback`. Implemente o que o Pagar.me
suportar; o que não implementar cai no padrão.

| Método em `\paygw_pagarme\gateway` | Para quê | Padrão se ausente |
|---|---|---|
| `get_supported_currencies()` | lista de meios por país | `[]` |
| `get_supported_countries()` | idem | `[]` |
| `cancel_recurring($component, $itemid, $userid)` | parar de cobrar | `false` |
| `refund($paymentid)` | estornar | `false` |
| `refund_blocker($paymentid)` | esconder o botão quando não dá | `errorrefundunknown` |
| `pending_invoice($component, $itemid, $userid)` | fatura em aberto do ciclo | `null` |

Do marketplace, o gateway consome: `api::commission_terms_for()`,
`api::recurrence_for()`, `api::record_sale()`.

---

## O que medir antes de codar

Escreva o resultado de cada uma em `docs/data-validation/pagarme-sandbox.md`,
com o número e a resposta crua da API. Nesta ordem:

1. **O split chega na cobrança?** Não basta a API aceitar o campo — confira que
   ele aparece no `GET` da cobrança, com valor. Foi o que separou o Asaas do
   Mercado Pago.
2. **Qual é a base do percentual?** Incide sobre o bruto ou sobre o líquido? No
   Asaas é o líquido, e comissão sobre o bruto exige valor fixo — que congela.
3. **Há assinatura com split?** E o split vale em **cada ciclo**, ou só na
   primeira cobrança?
4. **Quantas cobranças a assinatura gera de uma vez**, e em que ordem a lista
   volta? No Asaas vêm cinco, da mais distante para a mais próxima — e isso virou
   bug real.
5. **O estorno reverte o split?** E vale para quais formas de pagamento? No Asaas
   boleto não estorna nunca, nem depois de baixa manual.
6. **Estornar um ciclo cancela a assinatura?** No Asaas não cancela, e por isso o
   cancelamento anda junto no nosso código.
7. **Onde o instrumento fica guardado**, e o que acontece quando ele falha.
8. **Qual é o mínimo de valor** que o split aceita sem a taxa comer a margem.

---

## Cenários a reproduzir, os mesmos dos outros dois

Cada um com prova escrita, e o resultado no runbook.

**Cobrança avulsa**

- criar cobrança com split e conferir o split na resposta do `GET`
- pagar e conferir a comissão nos **dois extratos**
- webhook chegando sozinho: `status`, `feeamount`, `local_marketplace_sale`,
  direito de acesso e matrícula
- reconciliação encontrando a linha pendente órfã
- estorno total: dinheiro de volta, comissão revertida, acesso revogado
- estorno parcial: medir se reduz a comissão, e recusar se não reduzir
- estorno por forma de pagamento: quais o gateway aceita

**Assinatura**

- criar e conferir que a cobrança escolhida é a de vencimento **mais próximo**
- ciclo 1 pago pelo webhook
- ciclo 2 em diante: a cobrança nasce no gateway e o webhook a adota, copiando
  contexto e termos da linha anterior
- renovação **soma** ao vencimento atual, não recalcula de agora
- um único direito, com `cycles` crescendo
- avisos nos dois marcos, com textos diferentes, sem duplicar
- não pagar: acesso cortado por diferença, matrícula **suspensa** e não apagada
- pagar a atrasada: acesso de volta, sem criar segundo direito
- cancelar: para de cobrar no gateway, e o acesso pago continua até vencer
- reenvio da fatura pelo gerente, sem calar o aviso automático seguinte

**Permissões**

- aluno cancela a própria; gerente cancela a de qualquer aluno da empresa
- `refundsale` não vai para papel nenhum por padrão
- o botão some quando o gateway diz que não dá

---

## Padrões de qualidade, não negociáveis

**TDD.** Teste antes da implementação. O `marketplace_fee` do Mercado Pago
existiu meses sem teste porque só era alcançável batendo na API — a costura
`make_curl()` existe para isso não se repetir.

**Costura de teste desde o início.** `protected function make_curl()` no cliente,
e `fake_<gateway>_client` + `fake_curl` em `tests/fixtures/`. Sem isso, montagem
de corpo e mapeamento de erro só se testam com rede.

**Funções puras para o que move dinheiro.** O cálculo da comissão e a montagem do
corpo saem do método que precisa de banco e sessão, e viram estáticas testáveis.

**PHPUnit** — suíte própria, verde. **Behat local** com `moodev up --full` e
`--profile=chrome`; sem o perfil os `@javascript` morrem em `localhost:4444`.

**phpcs** — `phpcs --standard=moodle -p --report=summary <caminho>`. **Leia o
total**; o CI roda com `--max-warnings 0`, então aviso reprova. Nunca corte a
saída com `tail`.

**Regras do Moodle**: `db/access.php` para capabilities, `db/upgrade.php` para o
que precisa chegar em produção (`db/install.php` só roda em instalação nova),
strings de idioma em **ordem alfabética** nas três línguas, e o plugin
acrescentado a `.github/moodle-plugins.txt` — senão o CI não o valida.

**Comentários explicam o porquê**, não o quê. Em português, sem acentos no código;
com acentos nas strings de idioma e na documentação.

---

## Ambiente

**Passo zero: sincronize com o upstream do Moodle.** Feature que nasce de um
`dev` atrasado encontra o merge do upstream depois, com o código dela no meio do
caminho.

```bash
git -C dev fetch -q upstream MOODLE_502_STABLE
git -C dev rev-list --count origin/dev..upstream/MOODLE_502_STABLE   # zero, siga
```

Mais que zero, traga antes — `docs/dev/estrutura-worktrees.md`, seção 5.

```bash
moodev new paygw-pagarme-v2 --new-stack --from origin/dev --no-code
moodev ls        # confira offset e portas antes de seguir
```

**Não reaproveite a worktree `paygw-pagarme` que existe.** Ela é anterior ao
Asaas: o `public/payment/gateway/` dela tem só `mercadopago` e `paypal`, e o
molde não estaria lá. Crie nova a partir de `origin/dev`.

Webhook precisa de URL pública. Túnel `cloudflared` com **hostname fixo**, e o
`wwwroot` acompanhando em `config-local.php` — sem isso o `notification_url` sai
com `localhost` e o gateway nunca chega.

---

## Erros já cometidos, que custaram tempo

- **Empurrar commit para PR já merjeado** deixa o trabalho órfão. Aconteceu cinco
  vezes. Confirme que o PR está aberto antes de empurrar.
- **Medir um fluxo *próximo* do real** dá resposta confiante e errada. Meça o
  caminho que o plugin percorre de verdade.
- **Asserção de behat por palavra solta** (`should not see "error"`) dá falso
  positivo em marcação legítima. Afirme sobre o texto da tela.
- **Sessão de usuário de teste no navegador** contamina a compra de produção
  seguinte, e a mensagem de erro não diz qual parte está errada.
- **Direito de outra oferta segura o acesso**: para provar o corte, isole a
  assinatura, senão o sync suspende zero e parece bug.

---

## O que continua sem prova no projeto

Não é trabalho do Pagar.me, mas evita redescobrir:

- vendedor **pessoa jurídica** no Mercado Pago
- **retentativa de cartão guardado** quando ele vence no meio da assinatura
- **Pix e boleto liquidando de verdade** — o sandbox do Asaas não liquida nenhum
