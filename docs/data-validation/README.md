# Validação

Como se verifica que o sistema funciona — e o que ainda não foi visto funcionar.

| Documento | O que cobre |
|---|---|
| [`painel-de-testes.md`](painel-de-testes.md) | caminhos de gestão, CLI, cartões de teste e o que falta provar |
| [`asaas-sandbox.md`](asaas-sandbox.md) | provar o **split** no Asaas: contas, webhook, script e passo a passo com `curl` |
| [`asaas-assinatura.md`](asaas-assinatura.md) | provar o **ciclo** da assinatura no Asaas: cobranca automatica, corte por falta de pagamento e volta ao pagar a atrasada |
| [`mercadopago-split.md`](mercadopago-split.md) | provar o **split** no Mercado Pago: as três contas, painel, túnel e as três rodadas |
| [`pagarme-sandbox.md`](pagarme-sandbox.md) | provar o **split** no Pagar.me: os payables, e por que o `GET` da cobrança não serve de prova |
| [`local-partners-layout.md`](local-partners-layout.md) | provar o **layout** da landing e do cadastro nas cinco larguras e nos três temas |

Scripts em [`scripts/`](scripts/). Credenciais **nunca** entram aqui: ficam em
`.devcontainer/secrets/`, coberto pelo `.gitignore`. Este repositório está no
GitHub, e chave de API em markdown versionado é um caminho sem volta.

A distinção que importa neste projeto:

- **sem prova** — o código existe, ninguém viu funcionar
- **falta construir** — decidido, não feito
- **bloqueado** — parado por decisão de negócio

O split de 25% está **provado nos dois gateways**: Asaas em 2026-08-27, Mercado
Pago em 2026-09-08 (R$ 5,00 → R$ 1,25 de `application_fee`, com `collector_id`
diferente do dono da aplicação). Antes disso, no Mercado Pago vendedor e
marketplace eram a mesma conta e o `marketplace_fee` não transferia nada — sem
erro nenhum, que é o pior tipo de falso positivo.

No Mercado Pago o vendedor daquela rodada era **pessoa física** — o CNPJ é
exigido da plataforma, não de quem vende.

A **compra pelo Moodle com comissão maior que zero** foi provada em 2026-09-08,
pela vitrine e com o webhook chegando sozinho: R$ 5,00 → R$ 1,25 de comissão,
`feesource = company`, direito de 30 dias e matrícula.

No **Pagar.me** o split foi provado em 2026-09-11: R$ 100,00 com comissão de
25% entregaram **R$ 25,00 exatos** à plataforma e R$ 70,51 ao vendedor, com a
taxa de R$ 4,49 saindo inteira dele. O percentual lá incide sobre o **bruto**,
ao contrário do Asaas. Em 2026-09-14 a prova foi repetida **em produção, com
Pix e dinheiro real** — R$ 5,00 divididos 99/1, e os R$ 0,05 de taxa saindo de
quem foi declarado responsável por ela.

A armadilha daquele gateway merece ser sabida antes de abrir o roteiro: o `GET`
da cobrança devolve `splits: null` **mesmo quando o split aconteceu**. A prova
está em `GET /payables?recipient_id=`. Quem olhar só a cobrança conclui que
falhou quando funcionou — e desligar o split por causa disso custaria a
comissão de todas as vendas.

Continuam sem prova no Pagar.me: **Pix** (a conta responde "Sem ambiente
configurado para este tipo de transação") e **assinatura com split** (recusada
em todos os formatos). Por isso o plugin recusa oferta recorrente na porta —
cobrar sem split renderia comissão zero em silêncio.

Continua **sem prova**: o vendedor pessoa jurídica no Mercado Pago.
