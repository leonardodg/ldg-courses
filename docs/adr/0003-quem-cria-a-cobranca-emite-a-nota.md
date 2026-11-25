# ADR-0003 — Quem cria a cobrança é o vendedor, porque é ele quem emite a nota

**Situação:** Aceita · **Data:** 2026-08-27

## Contexto

Quem vende o curso é a empresa parceira. É ela quem recebe pela venda e quem
emite a nota fiscal. A plataforma fica com uma **porcentagem de serviço** — e não
pode aparecer como vendedora de um produto que não é dela.

Isso não é preferência de arquitetura, é consequência fiscal: a plataforma não
vai emitir nota por outra empresa.

O Mercado Pago já resolvia assim sem que ninguém tivesse escolhido: o token vem
do OAuth do vendedor, a preferência nasce na conta dele, e o `marketplace_fee`
transfere a comissão. Ao avaliar Asaas e Pagar.me, a mesma pergunta precisou ser
feita explicitamente — porque os dois têm um modelo "marketplace" nativo em que
**a plataforma processa** e o vendedor é um recebedor. Esse modelo é mais simples
de integrar e **inverte quem é o merchant of record**.

A documentação do Asaas dá a chave:

> o saldo líquido não destinado aos recebedores permanece na conta que criou a
> cobrança

E a prova mais concreta apareceu no payload do Pix: o **nome do recebedor** que
o banco do comprador exibe é o da conta que emitiu a cobrança.

## Decisão

**A cobrança nasce sempre na conta do vendedor.** O split leva apenas a comissão
para a carteira da plataforma.

| Gateway | Como |
|---|---|
| Mercado Pago | token OAuth do vendedor; `marketplace_fee` para a aplicação |
| Asaas | API key do vendedor; `split[]` para o `walletId` da plataforma |
| Pagar.me | `sk_` do vendedor; `split[]` para um `recipient_id` da plataforma **dentro da conta dele** |

No Pagar.me isso tem consequência incômoda e assumida: como `recipient` é objeto
interno de cada conta, o `recipient_id` da plataforma **é diferente em cada
vendedor**. O vendedor cadastra a plataforma como recebedor no painel dele e cola
o `re_...` na configuração.

A credencial do vendedor é guardada **cifrada** (`\core\encryption`), por conta de
pagamento e por ambiente. Uma API key não expira sozinha e dá acesso amplo à
conta bancária dele — é diferente de um token OAuth de seis meses.

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| Plataforma como merchant of record, vendedor como recebedor | É o modelo nativo de marketplace do Asaas e do Pagar.me, e o mais simples de integrar. **Recusado pela regra fiscal**: a plataforma passaria a vender o curso dos outros |
| Subcontas white-label criadas pela plataforma | Onboarding sem sair do site, mas a conta do vendedor vira apêndice da nossa e a responsabilidade fiscal fica ambígua. Usado **só no sandbox**, para ter uma segunda conta |
| Vendedor cola a chave, sem validação | Guardar primeiro e descobrir depois deixaria a empresa habilitada a vender com credencial que não funciona — e quem descobriria seria o aluno, no checkout |
| Credencial em texto puro, como o token do MP hoje | Um dump do banco viraria acesso à conta bancária de todos os vendedores. O token do MP fica assim por ora porque está em produção; é dívida registrada |

## Consequências

**Fica mais fácil:** a responsabilidade fiscal fica onde deve; o vendedor mantém
conta própria e independente; a comissão é rastreável no extrato dos dois lados.

**Fica mais difícil:**

- o onboarding depende do vendedor — ele gera a chave, cadastra o domínio da
  plataforma na conta dele, e no Pagar.me ainda cadastra a plataforma como
  recebedor
- em produção, uma conta independente pode precisar de **liberação do gerente de
  contas do Asaas** para fazer split para carteira externa
- guardamos credencial de terceiro: exige cifragem, capability separada, e a
  chave de cifragem em `moodledata/secret/` entra no backup — se ela sumir, todos
  revinculam
- cada gateway tem uma ordem própria de dedução de taxas. No Asaas o split incide
  sobre o `netValue`, não sobre o bruto: R$ 100 viram R$ 97,52 líquidos, e 25%
  disso são **R$ 24,38**. Por isso o relatório guarda o valor devolvido pelo
  gateway em vez de recalcular

## A baixa manual, e o que ela custa

Descoberto testando contra a API em 2026-08-27, e vale registrar porque é
consequência direta desta decisão.

Como a cobrança nasce na conta do vendedor, **é ele quem controla a baixa
manual**. Se ele marcar a cobrança como recebida em dinheiro (`receiveInCash`),
o Asaas cancela o split — dinheiro que não passou por ele não tem como ser
dividido — e a plataforma não recebe nada:

```
status ........ RECEIVED_IN_CASH
split ......... CANCELLED
valor ......... 24.38     (atribuído, e nunca transferido)
```

O caminho existe e é simples: o vendedor pede ao aluno que pague por fora, dá
baixa manual, e usa a plataforma como vitrine sem pagar comissão.

**A decisão é entregar o curso e registrar R$ 0,00.** O aluno pagou — negar
acesso o puniria pelo que o vendedor fez —, e a plataforma de fato não recebeu
nada, então o relatório precisa dizer isso. Matrícula sem comissão não é
problema a resolver aqui.

O que **não** é aceitável é o relatório mentir. A primeira versão calculava a
comissão pelo percentual e anunciava R$ 25,00 que nunca chegariam; hoje
`fee_from()` lê o split da própria resposta e descarta as entradas `CANCELLED` e
`REFUSED`. Cada entrega com comissão zero registra um `debugging()`.

> Relatório financeiro que discorda do extrato é pior que nenhum. Este
> discordaria em silêncio.

Isto vale para qualquer gateway em que o vendedor é o dono da cobrança — ou
seja, para todos, por causa desta decisão. É o preço da regra fiscal, e está
pago conscientemente.

## Como saber que erramos

Se um vendedor receber uma nota fiscal emitida pela plataforma referente ao curso
dele, a decisão foi contornada em algum lugar.

Se o onboarding se mostrar inviável na prática — vendedores desistindo por não
conseguirem gerar chave ou cadastrar recebedor —, o custo da regra fiscal supera
o benefício e o modelo precisa ser rediscutido com o contador, não com o
desenvolvedor.

Se as entregas com comissão zero deixarem de ser exceção — dá para contar pelo
relatório, que agora mostra R$ 0,00 nelas —, a baixa manual virou canal de fuga
e a decisão de entregar o curso mesmo assim precisa ser revista.

## Verificado

Em 2026-08-27, no sandbox do Asaas, com duas contas distintas:

```
cobrança ... pay_w1ijat3r74sx4nlp | CONFIRMED
bruto ...... R$ 100,00 | líquido R$ 97,52
SPLIT ...... carteira da plataforma | AWAITING_CREDIT | 25% | R$ 24,38
```

Primeira vez neste projeto que um split é visto sendo atribuído entre duas
contas. Roteiro em [`../data-validation/asaas-sandbox.md`](../data-validation/asaas-sandbox.md).
