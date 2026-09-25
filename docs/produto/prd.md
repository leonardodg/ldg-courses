# PRD — Hub de produto

> Produto: marketplace Moodle onde qualquer pessoa publica curso gratuito ou
> pago, com split de pagamento. Este documento responde **o que o produto precisa
> entregar, para quem, e com que critério de sucesso**. Arquitetura, tabelas,
> tokens visuais e registros de decisão (ADR) têm dono próprio — aqui ficam só
> a intenção e os links.

## Visão e protagonista

Uma plataforma Moodle 5.2 onde **qualquer um publica um curso gratuito ou
pago**, e a venda é dividida automaticamente entre o vendedor e a plataforma
(três gateways: Mercado Pago, Asaas e Pagar.me).

**O protagonista é o vendedor/empresa**, não o aluno. O aluno é o
**beneficiário** — quem assiste, compra e consome —, mas o produto é desenhado
para quem publica, monetiza e opera o catálogo. Toda decisão de fluxo, plano e
frente técnica se pergunta primeiro: *isso serve a quem vende?*

| Papel | O que é |
|---|---|
| **Vendedor/empresa** | Protagonista. Publica, precifica, escolhe plano, hospeda e recebe com split |
| **Aluno** | Beneficiário. Compra e assiste; não é o alvo do desenho de produto |
| **Plataforma** | Infraestrutura + comissão; cobra a mensalidade SaaS da empresa |

## Frentes de vídeo (ordem aprovada)

A ordem não é cronograma de conveniência: cada frente destrava a próxima.

### 1. Frente B — Bunny nativo multi-tenant (com a C dentro)

Hospedagem nativa no Bunny, com **uma `library` por empresa** — isolamento de
vídeo no mesmo nível do isolamento de empresa que já existe para cursos e
contas. A **frente C (trava de resolução) entra dentro da B**, porque a trava
só tem o que travar quando a plataforma é a origem do stream.

### 2. Frente C — trava de resolução por mensalidade do vendedor

A trava passa a ser definida pela **mensalidade do vendedor** (o plano pago
pela empresa), e é aplicada **no player** (`core_media_manager`) — não apenas
por URLs de origem assinadas como controle único. O aluno vê um seletor de
qualidade que não oferece o que o plano dele não inclui.

O ADR-0005 (player + origem) foi **superado pelo [ADR-0014](../adr/0014-trava-de-resolucao-por-mensalidade-do-vendedor.md)**
("trava por mensalidade do vendedor + player"), **Aceito em 2026-09-25**.

### 3. Frente A — BYOS

O produtor conecta a **chave de API da própria conta** de streaming (Bring Your
Own Streaming) e hospeda fora da plataforma. É a frente que fecha a oferta do
degrau mais alto (comissão reduzida, sem custo de banda para a plataforma).

| Ordem | Frente | Em uma linha |
|---|---|---|
| 1 | **B** | Bunny nativo, uma `library` por empresa — **com C dentro** |
| 2 | **C** | Trava de resolução por **mensalidade do vendedor**, aplicada no **player** |
| 3 | **A** | BYOS: o produtor conecta a própria chave de API |

## Planos comerciais

Degraus de entrada da empresa (mensalidade SaaS paga **à plataforma**). Todos os
números abaixo são **a confirmar** — são a intenção comercial de hoje, não
contrato fechado.

| Degrau | Comissão (quase) | Infra | Observação |
|---|---|---|---|
| Free | ~**10%** | YouTube **ou** Bunny limitado | |
| ~**R$ 50–100** | remove o limite | SaaS paga a banda | **calcular custos de banda antes** de liberar |
| ~**R$ 300** | ~**5%** | BYOS | |

A assinatura SaaS (a empresa pagando a plataforma) usa a paymentarea **`'plan'`**
já existente — o `itemid` é um `companyid`, e a plataforma fica com 100% (sem
split). O desenho completo dessa cobrança é de
[`../ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md`](../ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md)
— **não redesenhar aqui**.

### Tensão com o desenho Start/PRO anterior

O desenho mais antigo de planos Start/PRO (hospedagem nativa vs BYOS, comissões
10%/5%) e estes degraus **ainda não estão reconciliados**. O que precisa de
reconciliação fica como **item em aberto**, e não é decidido neste documento:

- Os rótulos e as faixas de mensalidade (Free / ~50–100 / ~300) versus os
  `start_free` / `start_50` / `start_100` / `pro` do desenho implementado.
- O que "remove o limite" significa em resolução por tier — o mapeamento exato
  tier → resolução continua aberto no próprio desenho Start/PRO.
- A mensalidade do degrau BYOS (~300, **a confirmar**) versus a mensalidade do
  `pro`, que lá está em aberto.

## Fora da v1 (não-objetivos)

Fora do escopo desta versão — não é "depois sim", é **não agora**:

| Fora da v1 | Por quê |
|---|---|
| Cloudflare | A frente escolhida é Bunny; trocar de provedor depois é reversível só se a travar existir, mas **não é esta versão** |
| Substituir o `mod_ldgvideo` | É a peça do plano Free (embed, custo de banda zero); continua em serviço |
| Fase 5 — moodledata (vídeo hospedado no Moodle) | Bloqueada por decisão de negócio de cobrança, não por técnica |
| Cota de banda por empresa | Controle fino de consumo fica para depois da trava de resolução |

## Critérios de sucesso (gate de release)

O produto só considera esta versão **entregue** quando os três forem verdadeiros:

1. **Trava de resolução ponta a ponta no player** — o `core_media_manager`
   respeita o teto definido pela mensalidade do vendedor, e o aluno não
   alcança a trilha acima do plano.
2. **Uma venda real em cada degrau comercial** — Free, degrau intermediário e
   degrau BYOS: cada um com ao menos **1** venda de verdade, não sandbox.
3. **Gate de custo** — o custo de banda versus a mensalidade cobrada está
   **calculado** antes de qualquer degrau pago ser liberado.

## Números e taxas

Comissão, base de cálculo, regras de split e particularidades de cada gateway
**não vivem aqui**. A fonte:

| Assunto | Dono |
|---|---|
| Base da comissão (bruto/líquido, configurável, fotografada na venda) | [`../adr/0007-comissao-sobre-o-bruto.md`](../adr/0007-comissao-sobre-o-bruto.md) |
| Duas assinaturas (B2B sem split, B2C com split) | [`../adr/0012-duas-assinaturas-e-so-uma-tem-split.md`](../adr/0012-duas-assinaturas-e-so-uma-tem-split.md) |
| Por que o sistema de pagamento é assim | [`../architecture/decisoes-marketplace.md`](../architecture/decisoes-marketplace.md) |
| Estado atual e próximas fases | [`../architecture/estado-e-proximas-fases.md`](../architecture/estado-e-proximas-fases.md) |
| Trava de resolução (vigente: ADR-0014, Aceita) | [`../adr/0014-trava-de-resolucao-por-mensalidade-do-vendedor.md`](../adr/0014-trava-de-resolucao-por-mensalidade-do-vendedor.md) · [`../adr/0005-trava-de-resolucao-por-ticket.md`](../adr/0005-trava-de-resolucao-por-ticket.md) (superado) |

Todos os percentuais e valores "quase" deste documento (comissões ~10% / ~5%,
degraus ~R$ 50–100 / ~R$ 300) permanecem **a confirmar** até fechamento
comercial.
