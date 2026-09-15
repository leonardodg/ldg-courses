# ADR-0001 — Mais de um gateway, e o núcleo sem saber o nome de nenhum

**Situação:** Aceita · **Data:** 2026-08-27

## Contexto

O split é o coração deste modelo de negócio e **nunca tinha sido visto
funcionar**. No Mercado Pago vendedor e marketplace foram a mesma conta no
teste, então o `marketplace_fee` não transferiu nada — e não houve erro, o que é
pior: o código parecia certo.

Tentar provar o split no Mercado Pago esbarrou em limites do próprio produto:

- `POST /preapproval` não leva `marketplace_fee` — **não existe recorrência com
  split** (ver a correção abaixo: o mecanismo não é o que estava escrito aqui)
- o Transparente com cartão salvo exige CVV a cada cobrança
- o sandbox exige contas de teste dos dois lados, com regras de país

O levantamento em `../gateway-pay/levantamente.txt` apontou dois candidatos com
split nativo em Pix, cartão e boleto: **Pagar.me** e **Asaas**.

Havia também um problema estrutural, independente de fornecedor. O núcleo
conhecia o Mercado Pago pelo nome em quatro lugares:

| Onde | O quê |
|---|---|
| `api.php:135` | lia `defaultfeepercent` do namespace `paygw_mercadopago` |
| `company.php:134` | botão com `gateway=mercadopago` escrito no código |
| `report.php:115` | `SELECT` direto na tabela `paygw_mercadopago` |
| `mysubscriptions.php:160` | idem |

Com um segundo gateway, o relatório passaria a **mentir por omissão**: a venda
existiria, o aluno estaria matriculado, e o total simplesmente não contaria
aquele dinheiro. Sem erro e sem aviso.

## Decisão

Vamos ter **N gateways**, e o núcleo não vai saber o nome de nenhum.

Concretamente:

- a comissão padrão sai do namespace de um fornecedor e passa para as settings
  do `local_marketplace`
- os gateways passam a chamar `api::commission_for()` e `api::record_sale()` —
  pontos únicos, com o guarda de componente dentro, em vez de cada um repetir o
  mesmo bloco
- nasce `local_marketplace_sale`, neutra: guarda o que o `core_payment` não
  guarda — a comissão efetivamente retida e o id da transação — e o resto vem
  de `{payments}` por join
- a lista de meios de pagamento de um país é montada perguntando a cada gateway
  quais moedas e países ele atende, via `component_class_callback`

O primeiro gateway novo é o **Asaas**, e não o Pagar.me como se planejou. A razão
é técnica: no Asaas o split funciona em Pix e a cobrança devolve `invoiceUrl`,
uma página hospedada — o mesmo modelo de redirect que já existia. No Pagar.me o
split em Pix só existe no `POST /orders` transparente; o Link de Pagamento não
faz split em Pix nem em boleto, só em cartão. Começar por ele exigiria construir
página de QR Code e polling **antes** de qualquer split rodar.

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| Ficar só no Mercado Pago | Não tem recorrência com split, e o sandbox torna a prova cara. O modelo inteiro depende de algo que não conseguíamos exercitar |
| Pagar.me primeiro, como planejado | Exigiria QR Code próprio e polling antes de a primeira prova de split existir. O Asaas chega ao mesmo lugar por redirect |
| Adicionar os gateways e refatorar o núcleo depois | O relatório mentiria em silêncio no intervalo, e "depois" costuma chegar quando alguém reclama de um número errado |
| Uma tabela de vendas por gateway, e o relatório somando todas | O relatório passaria a conhecer o nome de cada gateway — o problema que se quer resolver, com mais passos |
| Ler só `{payments}` do core, sem tabela nova | Falta lá a comissão retida e o id da transação. Recalcular 25% do bruto diverge do extrato, porque cada gateway deduz as próprias taxas numa ordem diferente |

## Consequências

**Fica mais fácil:** um gateway novo entra declarando moedas e países, sem que
nada no núcleo mude. O relatório soma tudo por construção. Trocar de fornecedor
deixa de ser reescrita.

**Fica mais difícil:**

- são três plugins de pagamento para manter, cada um com o próprio cliente HTTP,
  webhook e ciclo de credencial
- o CI passa de 5 para 7 instalações completas do Moodle por execução
- a comissão exibida no relatório depende de o gateway ter gravado o valor certo
  em `record_sale()` — um gateway que erre ali produz relatório errado sem que o
  núcleo tenha como perceber
- o upgrade precisa adotar o que já existe: conta sem vínculo por país e venda
  que só existia na tabela do Mercado Pago

## Como saber que erramos

Se um gateway novo exigir mudança em `local_marketplace` para funcionar, a
abstração não é a certa e este ADR precisa ser revisitado.

Se o total do relatório divergir da soma dos extratos dos gateways, o
`record_sale()` está sendo alimentado errado por alguém.

## Correção de 2026-09-08 — a conclusão está certa, o mecanismo não estava

O contexto acima dizia que `POST /preapproval` **não aceita** `marketplace_fee`.
Exercitado contra a API de produção, com o token do vendedor e a aplicação da
plataforma, o comportamento real é outro e é pior:

| Requisição | Resultado |
|---|---|
| `preapproval` **sem** `marketplace_fee` | `201`, assinatura criada |
| `preapproval` **com** `marketplace_fee: 1.25` | `201`, assinatura criada |

As duas respostas são indistinguíveis. Um `GET` em cada uma devolve o mesmo
conjunto de campos, e **nenhuma chave contendo "market"** — o campo é aceito na
requisição e **descartado em silêncio**.

A decisão de buscar um segundo gateway continua válida: não há recorrência com
split no Mercado Pago. O que muda é o sintoma, e ele importa mais que a
conclusão. "Não aceita" faz esperar um erro que segure o engano na porta. Não há
erro: haveria assinatura ativa cobrando todo mês, com comissão zero, e ninguém
saberia — a mesma forma exata do bug que originou este ADR, quando vendedor e
marketplace eram a mesma conta.

Por isso `preapproval` não aparece em linha nenhuma do `paygw_mercadopago`, e não
deve aparecer: aqui assinatura é acesso com prazo mais aviso de vencimento, e
cada ciclo é uma compra própria — que leva split, e isso foi provado em
`../data-validation/mercadopago-split.md`.

**Método:** esta afirmação sustentava sozinha a existência do segundo gateway e
estava registrada como fato, sem evidência de ter sido exercitada. No mesmo dia
descobriu-se que o tipo de uma conta também estava escrito como fato e nunca
tinha sido verificado (ver ADR-0010). Afirmação que decide arquitetura precisa
de uma chamada à API anexada, e não de uma leitura de documentação.

## Correção de 2026-09-15 — a suspeita era boa, e a conclusão sobreviveu

A medição de 08/09 foi feita com a aplicação de **Checkout Transparente**, e
neste projeto já está registrado que o modelo declarado da aplicação muda o
comportamento da API em silêncio. A dúvida era legítima: e se o `preapproval`
honrasse a comissão quando chamado por uma aplicação do tipo **Assinaturas**?

Em 15/09/2026 a pergunta foi refeita com a aplicação do tipo certo
(`6990306155285574`, conta CNPJ `3675841384`) e contas distintas. Cinco
formatos, um por vez, com `GET` logo depois:

| Campo enviado | Resposta | Eco no `GET` |
|---|---|---|
| `marketplace_fee` na raiz | `201` | nenhum |
| `application_fee` na raiz | `201` | nenhum |
| `marketplace` na raiz | `201` | nenhum |
| `marketplace_fee` em `auto_recurring` | `201` | nenhum |
| `application_fee` em `auto_recurring` | `201` | nenhum |

O `GET` completo devolve o recurso inteiro e **não há nenhum campo de taxa**. O
SDK oficial concorda: `Resources/PreApproval.php` e
`Resources/PreApproval/AutoRecurring.php` não declaram nada do gênero.

**A conclusão deste ADR continua valendo, e agora com a causa certa.** Não era o
tipo da aplicação: o recurso simplesmente não tem onde guardar comissão. Ids e
roteiro em [`../data-validation/mercadopago-assinatura.md`](../data-validation/mercadopago-assinatura.md).

Duas coisas mudam, e as duas viraram ADR próprio:

- **o `preapproval` deixa de ser proscrito no `paygw_mercadopago`.** O parágrafo
  acima dizia que ele "não aparece em linha nenhuma do plugin, e não deve
  aparecer". Isso valia quando "assinatura" era uma coisa só. Separadas as duas
  — a mensalidade que a empresa paga à plataforma **não tem terceiro, logo não
  tem split** —, o `preapproval` é a ferramenta certa para ela. Ver
  [ADR-0012](0012-duas-assinaturas-e-so-uma-tem-split.md);
- **a assinatura com comissão passa a sair por `/v1/payments`**, com
  `application_fee` em cada ciclo sobre um cartão guardado no próprio Mercado
  Pago. Ver [ADR-0013](0013-uma-aplicacao-por-tipo-de-integracao.md).

Um terceiro caminho foi medido e descartado: `/v1/advanced_payments`, que tem
`disbursements[]` com `application_fee` por recebedor, responde **403 de
política** em todos os tokens, teste e produção. Precisa de liberação comercial.
