# ADR-0012 — Duas assinaturas, e só uma tem split

**Situação:** Aceita · **Data:** 2026-09-15

## Contexto

Este projeto usa a palavra "assinatura" para duas coisas diferentes, e tratá-las
como uma só levou a decisões erradas. A distinção apareceu numa conversa de
15/09/2026 e foi confirmada no código.

**A primeira é B2B: a empresa parceira paga uma mensalidade à plataforma** para
usar o Moodle e poder vender cursos. Quem vende é a LDG, quem recebe é a LDG, e
**não há terceiro**. Mora em `local_marketplace_plan.monthlyfee`.

**A segunda é B2C: a parceira vende uma assinatura ao aluno** — acesso recorrente
a cursos, módulos ou certificados. Quem vende é a parceira, quem recebe é a
parceira, e **a plataforma fica com uma comissão**. Mora numa `offer` com
`accessmode = recurring`.

|  | B2B | B2C |
|---|---|---|
| Quem vende | a plataforma | a empresa parceira |
| Quem recebe | a LDG, 100% | a parceira, menos a comissão |
| Split | **não existe** | **obrigatório** |
| Onde mora | `local_marketplace_plan.monthlyfee` | `offer` com `accessmode = recurring` |
| Estado em 15/09/2026 | **não cobra nada** | avulso funciona; recorrente em construção |

A segunda linha da tabela é uma lacuna real, e o próprio código a declara. O
docblock de `local_marketplace/classes/plan.php` diz que o plano **"NÃO cobra
nada: a mensalidade é informativa até existir a `paymentarea 'plan'` no
`service_provider`"**. Conferido: `service_provider::get_payable()` ignora o
`paymentarea` e trata o `itemid` como oferta. A cobrança B2B não existe.

## Por que isso importa agora

Porque muda qual limitação do Mercado Pago atinge o quê.

Medido em 15/09/2026, com a aplicação do tipo **Assinaturas** e contas
distintas: `POST /preapproval` aceita `marketplace_fee`, `application_fee` e
`marketplace`, na raiz e dentro de `auto_recurring`. Devolve `201` nos cinco
formatos e **não devolve nenhum deles no `GET`**. O recurso não tem onde guardar
comissão ([`../data-validation/mercadopago-assinatura.md`](../data-validation/mercadopago-assinatura.md)).

Enquanto "assinatura" era uma coisa só, a conclusão era "o Mercado Pago não
serve para assinatura". Separadas, a leitura muda:

- **para a B2B o `preapproval` é exatamente a ferramenta certa** — sem terceiro,
  não há comissão a carregar, e ele entrega cobrança automática com o cartão
  guardado no próprio Mercado Pago;
- **só a B2C precisa de outro caminho**, que é cobrar cada ciclo por
  `/v1/payments` com `application_fee`.

## Decisão

**Tratar as duas como produtos distintos, com mecanismos distintos.**

| | Mecanismo | Aplicação do MP |
|---|---|---|
| B2B | `POST /preapproval` | Assinaturas |
| B2C | um `POST /v1/payments` por ciclo, com `application_fee`, sobre cartão guardado no MP | Checkout Bricks |

É por isso que `application::type_for_recurring()` devolve **`bricks`**, e não
`subscriptions` — o nome mais óbvio é o errado, e o docblock do método carrega a
medição junto para que ninguém "conserte" isso depois.

**O `preapproval` não sai do plugin**, apesar de não servir ao split. Ele é o
motor da B2B, e removê-lo por causa de uma limitação que só atinge a B2C seria
jogar fora a ferramenta certa para o outro produto.

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| Uma assinatura só, genérica, com split opcional | Esconde que os mecanismos são diferentes. O código teria um `if` decidindo entre dois endpoints incompatíveis, e a escolha erraria calada |
| Usar `preapproval` para as duas, cobrando a comissão fora do gateway | A plataforma passaria a receber por fora da venda, o que colide com o [ADR-0003](0003-quem-cria-a-cobranca-emite-a-nota.md): a cobrança nasce na conta do vendedor por regra fiscal |
| Usar Asaas para as duas | Funciona, e custa a taxa fixa de R$ 1,99 de Pix em toda venda de ticket baixo — 39,8% numa venda de R$ 5,00 |
| Construir a cobrança B2B nesta rodada | É quase uma feature inteira de `local_marketplace` em cima de um trabalho de gateway. Decidido em 15/09: esta rodada entrega só o gateway |

## Consequências

**Fica mais fácil:** a aplicação de Assinaturas deixa de ser uma aposta e passa a
ter uso garantido, independentemente de qualquer medição futura de split. E a
pergunta "o Mercado Pago serve para assinatura?" passa a ter duas respostas, cada
uma com o seu porquê.

**Fica mais difícil:** são dois caminhos de recorrência para manter no mesmo
plugin, e um deles — o da B2C — depende de a plataforma disparar as cobranças, o
que traz agendamento, retentativa e cartão vencido para dentro de casa. O Asaas
resolvia isso no lado dele.

**Fica pendente:** a `paymentarea 'plan'` e o ciclo da mensalidade da empresa.
Enquanto não existirem, a coluna `monthlyfee` continua informativa — e o docblock
do `plan.php` continua sendo a documentação honesta disso.

## Como saber que erramos

Se alguém trocar `application::type_for_recurring()` para `subscriptions`
achando que corrige um nome, a assinatura B2C passa a nascer sem comissão e
**sem erro nenhum** — o mesmo modo de falha que originou o
[ADR-0001](0001-gateways-alem-do-mercado-pago.md). O teste
`test_so_bricks_cobra_ciclo_de_assinatura_com_comissao` existe para isso.

Se a cobrança B2B for construída e precisar de split, esta decisão está errada e
a premissa "não há terceiro" precisa ser revista — seria o caso, por exemplo, de
um revendedor entre a plataforma e a empresa.
