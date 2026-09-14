# ADR-0011 — O extrato é a fonte da comissão, não a cobrança

**Situação:** Aceita · **Data:** 2026-09-11

## Contexto

Três gateways, três formas diferentes de mentir sobre o mesmo campo.

O `local_marketplace_sale.feeamount` guarda a comissão de cada venda. Ele
alimenta o relatório da empresa e é o que responde "quanto a plataforma ganhou".
A regra do projeto desde o [ADR-0007](0007-comissao-sobre-o-bruto.md) é que ele
registre **o que aconteceu**, e não o que foi pedido.

Até 11/09/2026 esse valor era lido da resposta da cobrança. Funcionava nos dois
gateways anteriores:

- No **Asaas**, `payment.split[]` volta no `GET` com `status` e `totalValue`, e
  dá para somar o que foi para a carteira da plataforma.
- No **Mercado Pago**, `fee_details` diz o que foi cobrado.

No **Pagar.me** isso não funciona, e a falha é silenciosa na direção oposta à
que o projeto aprendeu a temer.

Medido em 11/09/2026, cobrança `ch_KME2JgJuJnT1XlX7`, R$ 100,00 com split de
25%:

```
charge.status .... paid
charge.splits .... null
```

E, ao mesmo tempo, no extrato:

```
vendedor ...... amount 7500 | fee 449 | liquido R$ 70,51
plataforma .... amount 2500 | fee   0 | liquido R$ 25,00
```

**O split aconteceu. A cobrança não conta.** Um `GET` na cobrança, de qualquer
ângulo — `charge.splits`, `order.splits`, `order.charges[0].splits` — devolve
`null`.

Isto é a quarta ocorrência do padrão "`2xx` que não quer dizer nada" neste
projeto, e é a mais perigosa das quatro:

| Gateway | O que fez com o campo de dinheiro |
|---|---|
| Mercado Pago, `preapproval` | aceitou `marketplace_fee` e **descartou** |
| Asaas, `PUT /subscriptions` | aceitou `creditCard` e **não guardou** |
| Pagar.me, recebedor inexistente | aceitou, e o `GET` **denunciou** |
| Pagar.me, split válido | **fez o trabalho e não contou** |

As três primeiras fazem acreditar que deu certo quando não deu — e o prejuízo é
comissão perdida. A quarta faz acreditar que deu errado quando deu, e a reação
natural — *"o split falhou, vou desligar"* — custaria a comissão de **todas** as
vendas.

## Decisão

**A comissão gravada vem do extrato do recebedor, não da cobrança.**

No Pagar.me isso é `GET /payables?recipient_id=<rp>`, filtrando pelo
`charge_id` que cada payable carrega, e somando `amount - fee` — o que a
plataforma de fato recebe.

Três consequências que a decisão arrasta:

**O filtro por cobrança não existe.** `?charge_id=` devolve lista vazia. A
separação é feita no nosso código, sobre a lista do recebedor.

**O extrato demora.** Medido: o payable nasce cerca de **16 segundos** depois do
pagamento, e já se viu passar de 100. O webhook chega antes. A venda entra com
`feeamount` zero e a `task\reconcile` corrige quando o extrato existir.

**Zero não vira o valor esperado.** Gravar a comissão calculada enquanto o
extrato não aparece produziria um número que parece certo e que ninguém
reconferiria — que é exatamente como o `marketplace_fee` do Mercado Pago passou
meses sem ser notado.

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| Continuar lendo `charge.splits` | Grava zero em toda venda, e dispara o alarme de "split ausente" sempre. Foi o que o código fazia até esta medição |
| Gravar a comissão calculada por nós | Registra dinheiro que ninguém viu. Se o split falhar, o número mente com confiança — o pior desfecho possível neste projeto |
| Gravar o esperado e corrigir depois | Mesma objeção, atenuada. Entre a venda e a reconciliação o relatório afirma algo não verificado, e é nessa janela que alguém decide |
| Esperar o payable dentro do webhook | Prende a resposta do webhook por 16 s ou mais. O Pagar.me reenvia por timeout, e o aluno fica esperando a liberação do curso |
| Não gravar comissão nenhuma até reconciliar | O relatório fica vazio logo após a venda, e "não sei" vira "não teve" na cabeça de quem lê |

## Consequências

**Fica mais fácil** confiar no número: ele é o que o recebedor tem a receber, e
não uma promessa da API. O estorno se resolve sozinho — ele nasce como payable
**negativo**, e a mesma soma devolve zero sem código novo.

**Fica mais difícil** ler a comissão de imediato. Existe uma janela — de
segundos a minutos — em que a venda está entregue e o `feeamount` é zero. Quem
abrir o relatório nesse intervalo vê zero, e zero ali quer dizer "ainda não",
não "não teve". O `debugging()` do `process_notification` diz isso em texto, e a
tarefa roda de hora em hora.

**Custa uma chamada a mais** por confirmação, na conta do vendedor.

**Amarra o gateway ao conceito de recebedor.** Um gateway futuro sem extrato por
recebedor não se encaixa aqui, e precisará de outra fonte — o que não é perda:
significa reabrir a pergunta em vez de presumir.

## Como saber que erramos

Se o `charge.splits` do Pagar.me passar a vir preenchido, esta decisão vira
complexidade sem motivo — e o sinal é o teste
`test_a_cobranca_paga_esconde_o_split_que_aconteceu` começar a falhar. Ele
existe para isso.

Se aparecer venda com `feeamount` zero e `paymentid` preenchido **horas** depois
da compra, a reconciliação não está alcançando, e a janela deixou de ser janela.
