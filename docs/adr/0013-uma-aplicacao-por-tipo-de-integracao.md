# ADR-0013 — Uma aplicação do Mercado Pago por tipo de integração

**Situação:** Aceita · **Data:** 2026-09-15

## Contexto

Até 15/09/2026 o `paygw_mercadopago` conhecia **uma** aplicação: um par
`clientid`/`clientsecret` nas settings do site, e **um** `accesstoken` por conta
de pagamento, obtido por OAuth.

Isso bastava enquanto o plugin fazia uma coisa só — Checkout Pro com
`marketplace_fee` na preferência. Deixou de bastar quando se descobriu que os
produtos do Mercado Pago não são intercambiáveis:

| Produto | Endpoint | Campo da comissão |
|---|---|---|
| Checkout Pro | `POST /checkout/preferences` | `marketplace_fee`, na preferência |
| Checkout API / Bricks | `POST /v1/payments` | `application_fee`, no pagamento |
| Assinaturas | `POST /preapproval` | **não existe** ([ADR-0012](0012-duas-assinaturas-e-so-uma-tem-split.md)) |

E no Mercado Pago **"aplicação" não é detalhe de cadastro**: o modelo de
integração declarado no painel muda o comportamento da API em silêncio. Já
custou uma rodada aqui — declarar "Checkout Transparente" faz o
`marketplace_fee` da preferência ser ignorado **sem erro nenhum**, o que aparece
como venda sem comissão que não acusa nada.

Some-se o fato decisivo: **cada aplicação exige o seu próprio OAuth**. O token
que um vendedor emite autorizando a aplicação de Preferências não carrega a
aplicação de Bricks. Uma credencial só, portanto, torna impossível a mesma
empresa vender avulso por um produto e assinatura por outro.

## Decisão

**A credencial deixa de ser única e vira um registro tipado, com conjunto
fechado.**

```php
const TYPES = ['preferences', 'subscriptions', 'bricks'];
```

Cada tipo tem `client_id`, `client_secret` e assinatura secreta de webhook
próprios nas settings do site, e cada conta de pagamento guarda **um conjunto de
campos de OAuth por tipo**.

Três decisões dentro desta, cada uma com a sua razão:

**1. O conjunto é fechado, e não uma lista configurável.** Uma lista aberta
fingiria uma generalidade que não existe: o código ramifica por tipo, porque
cada produto tem endpoint e campo de comissão diferentes. Tipo novo aqui é
mudança de código, e tem que ser.

**2. O tipo `preferences` continua lendo os nomes ANTIGOS** — `clientid`,
`clientsecret`, `accesstoken`. Um esquema uniforme (`clientid_preferences`)
seria mais bonito e faria o site que já está no ar **perder o vínculo de cada
vendedor em silêncio**, com o sintoma aparecendo no checkout, diante do aluno.
Feio e correto ganha de bonito e arriscado. Não há passo de migração de
configuração, e a ausência é deliberada: um passo que renomeia config é risco
puro por zero ganho.

**3. O tipo viaja na SESSÃO durante o OAuth, e não no `redirect_uri`.** O
Mercado Pago exige que o `redirect_uri` case exatamente com o cadastrado no
painel. Como parâmetro de URL, cada aplicação precisaria de um endereço próprio
cadastrado — três chances de errar uma letra, e o erro que volta é
`invalid redirect_uri` **sem dizer qual aplicação foi consultada**. Na sessão, o
endereço é um só nas três, e o tipo não pode ser adulterado no caminho de volta.

O que **não** é por tipo: o país do marketplace (`platformsite`) e o modo de
teste. São propriedades do conjunto — comprador, vendedor e aplicação precisam
estar no mesmo país e no mesmo ambiente —, e tê-las por aplicação permitiria
justamente as misturas que o Mercado Pago recusa.

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| Continuar com uma aplicação | Impossível: cada produto exige o seu OAuth, e uma empresa não poderia vender avulso e assinatura ao mesmo tempo |
| Uma aplicação por conta de pagamento, escolhida pelo admin | Simplifica a tela e impede a mesma empresa de usar dois produtos — que é exatamente o caso de uso |
| Lista de aplicações configurável, sem tipo fixo | O código precisa saber qual endpoint chamar. Sem tipo, essa escolha viraria adivinhação |
| Renomear tudo para um esquema uniforme, com migração | Ganho estético contra risco de perder vínculo em produção. Ver decisão 2 |
| `apptype` como parâmetro do `redirect_uri` | Três URIs para cadastrar, e o erro de digitação não diz qual aplicação falhou |

## Consequências

**Fica mais fácil:** a mesma empresa vende avulso por Preferências e assinatura
por Bricks. Produto novo do Mercado Pago entra como tipo novo, sem tocar no que
já funciona.

**Fica mais difícil:**

- o vendedor autoriza **uma vez por aplicação**, o que é mais fricção no
  cadastro. A tela mostra uma linha por aplicação configurada, com o que falta;
- o `refresh_tokens` renova por tipo, e os vencimentos não andam juntos: um
  vendedor pode ter autorizado Preferências em março e Bricks em setembro;
- desvincular passou a ser por tipo, e o gateway só é desabilitado quando **não
  sobra nenhuma** aplicação vinculada — desligar com outra ainda válida seria
  desligar uma venda que continua possível;
- o formulário precisa declarar os campos escondidos de **todos** os tipos.
  Campo ausente do formulário é **apagado** ao salvar, então esquecer um faria
  salvar a tela apagar um vínculo que ninguém tocou.

## Como saber que erramos

Se um produto novo do Mercado Pago não couber como tipo — por precisar de mais
de um par de credenciais, ou de país próprio —, a modelagem está estreita demais.

Se alguém precisar renomear `clientid` para `clientid_preferences` para fazer
algo funcionar, a decisão 2 está cobrando caro demais e merece uma migração de
verdade, com passo de upgrade e prova.
