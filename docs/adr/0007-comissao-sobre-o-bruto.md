# ADR-0007 — Base de cálculo da comissão: configurável, e fotografada na venda

**Situação:** Aceita · **Data:** 2026-09-01

## Contexto

Até aqui o `CLAUDE.md` registrava o oposto, como se fosse restrição externa:

> A comissão incide sobre o líquido, não sobre o bruto. Cada gateway deduz as
> próprias taxas antes de dividir.

Isso descrevia com precisão o que o **código fazia**, mas não era uma imposição
dos gateways — era consequência de uma escolha de implementação que ninguém
tinha tomado conscientemente.

O `asaas_client::build_split()` mandava o split como `percentualValue`. Esse
campo é aplicado pelo Asaas sobre o `netValue`, ou seja, **depois** de ele
descontar a própria taxa. O efeito em números reais, medidos no sandbox em
2026-08-27:

```
bruto ............ R$ 100,00
taxa do Asaas .... R$   2,48
liquido .......... R$  97,52
comissao a 25% ... R$  24,38   <- 25% de 97,52, e nao de 100,00
```

Com a comissão de 9,9% do plano Starter, a diferença por venda de R$ 100 é de
R$ 0,25 — a plataforma pagando 2,5% da própria receita para cobrir uma taxa que
não é dela, em toda venda, sem nunca ter decidido isso.

O Mercado Pago já não tinha esse problema: o `marketplace_fee` é **valor
absoluto**, calculado no processador a partir do valor cheio. Os dois gateways
estavam entregando bases de cálculo diferentes para a mesma regra de negócio.

## Decisão

Três partes.

### 1. A base é configurável, e o padrão é o bruto

Não é uma constante no código. Cada degrau da cadeia de comissão pode declarar a
sua, e o padrão do site é **bruto**.

### 2. A base sai do MESMO degrau que deu a taxa

```
1. course_policy    → pct + base
2. company          → pct + base     (negociada com esta empresa)
3. plan             → pct + base     (contratado pela empresa)
4. padrão do site   →       base
```

`api::resolve_commission()` devolve um objeto `commission` com **taxa, base e
origem juntas**. Resolver taxa e base em cadeias independentes produziria
combinações que ninguém contratou — "taxa do plano com base do site" — e o
parceiro veria um número que não corresponde a acordo nenhum.

Coluna `commissionbase` **nula** significa "este contrato não define a base,
herda a do site". É diferente de escolher bruto: gravar `gross` em toda linha
congelaria a base ali para sempre, e depois não haveria como distinguir quem
escolheu de quem herdou.

### 3. Os termos aplicados são fotografados na venda

`local_marketplace_sale` ganhou `feepercent`, `feebase` e `feesource`, e as
tabelas dos dois gateways ganharam o mesmo. O gateway grava **o que aplicou**, e
o webhook lê da linha em vez de resolver de novo — entre criar a cobrança e
receber a notificação, a configuração pode ter mudado.

Sem a foto, uma venda de seis meses atrás só poderia ser explicada relendo a
configuração de hoje. O relatório passaria a contar uma história diferente da do
extrato do gateway, e a que muda seria a nossa.

## Como cada gateway aplica

| Base | Asaas | Mercado Pago |
|---|---|---|
| **bruto** | `fixedValue = bruto × pct` | `marketplace_fee = bruto × pct` |
| **líquido** | `percentualValue = pct` | **não é possível** |

No Asaas, `percentualValue` é aplicado sobre o `netValue`. Isso o torna **errado
para a base bruta** e **certo para a líquida** — o líquido depende da taxa da
forma de pagamento, que só o Asaas conhece, e na criação da cobrança ainda não
existe.

No Mercado Pago o `marketplace_fee` é sempre valor absoluto, e a taxa deles só é
conhecida depois do pagamento. **Não há como cobrar um percentual de um número
que ainda não existe.** Com `net` configurado, a venda pelo MP sai sobre o bruto
e **grava `gross`** — a configuração diz a intenção, a venda diz o fato. Expor a
divergência é melhor que gravar a intenção e deixar o relatório mentir.

O relatório mostra os termos aplicados em cada linha, com a origem embaixo.

## Consequências

**Com a base bruta, quem absorve a taxa do gateway é o vendedor.** É coerente
com o ADR-0003: a cobrança nasce na conta dele, ele emite a nota, o líquido é
dele. A taxa é custo de operação do negócio dele, como a maquininha de cartão de
uma loja. Com a base líquida, os dois lados dividem — e é por isso que a escolha
existe.

**A taxa não pode variar por meio de pagamento, e isso é proposital.**
`commission_for()` recebe componente e item, nunca o gateway. Quem escolhe a
forma de pagamento é o **aluno**, no checkout: taxa por gateway faria a receita
da plataforma depender de qual botão o comprador clicou, e o vendedor não
conseguiria prever quanto recebe pela mesma venda.

**Com base bruta, ticket muito baixo pode não fechar.** A taxa fixa do Pix e do boleto não
acompanha o valor da venda. Num curso de R$ 2,00, a taxa consome quase tudo e o
líquido pode não cobrir a comissão — e o Asaas então **recusa a cobrança
inteira**, não só o split. Definir preço mínimo é regra de negócio e não entrou
no código: `build_split()` só garante que a comissão nunca ultrapasse o valor da
própria cobrança, que é o limite que ele consegue verificar sem conhecer as
taxas do gateway.

**O `docs/data-validation/asaas-sandbox.md` guarda números da regra antiga.**
Ele registra uma execução real de 2026-08-27, feita com `percentualValue`. Os
números não foram reescritos — observação não se reescreve. O documento ganhou
uma nota dizendo o que mudou depois e por quê.

**Provado em sandbox em 2026-09-01.** Mesma cobrança de R$ 100,00 a 25% nas duas
bases, com cartão fictício para liquidar. O split foi montado por
`build_split()`, e a conferência feita do lado que **recebe**:

| Base | Campo | A plataforma recebe |
|---|---|---|
| bruto | `fixedValue` | **R$ 25,00** `AWAITING_CREDIT` |
| líquido | `percentualValue` | **R$ 24,38** `AWAITING_CREDIT` |

O R$ 24,38 reproduz o número de 2026-08-27 na casa do centavo. Falta apenas ver
o split chegar a `DONE` com o saldo se movendo — cartão liquida em D+30 no
sandbox, e `AWAITING_CREDIT` significa literalmente "esperando ser creditado".
Roteiro em `docs/data-validation/asaas-sandbox.md`.

**As linhas antigas receberam os defaults das colunas** (`gross`, `site`). Isso é
aproximação, e não fato sobre elas: foram criadas quando a base não era
configurável nem registrada.

## Alternativas consideradas

**Aumentar o percentual para compensar a taxa.** Cobrar 10,2% para receber o
equivalente a 9,9% do bruto. Rejeitada: a taxa varia por forma de pagamento
(Pix, boleto e cartão têm taxas diferentes), então nenhum percentual único
acerta, e o contrato com o parceiro passaria a mostrar um número que não é o
combinado.

**Deixar como estava e ajustar a comunicação.** Documentar que "9,9%" significa
9,9% do líquido. Rejeitada pelo dono do projeto: o número que aparece na landing
e no contrato é sobre a venda, que é o que o parceiro entende por "minha venda".

**Fixar o bruto no código, sem configuração.** Foi a primeira versão desta
decisão. Rejeitada pelo dono do projeto: a base é termo comercial, e termo
comercial que só muda com deploy não é negociável na prática.

**Configurar a base por meio de pagamento.** Rejeitada pelo motivo em
Consequências: quem escolhe o gateway é o comprador, não a empresa.
