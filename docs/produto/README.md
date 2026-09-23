# Hub de produto

Lugar único da **intenção de produto**: o que vendemos, para quem, e com que
critério de sucesso. Os donos técnicos continuam onde estão — este hub só
aponta para eles, sem copiar conteúdo deles.

## Documentos do hub

| Documento | O que responde |
|---|---|
| [`prd.md`](prd.md) | Product Requirements Document — o que o produto precisa entregar |
| [`trd.md`](trd.md) | Technical Requirements Document — requisitos técnicos do produto |
| [`fluxo-do-app.md`](fluxo-do-app.md) | Fluxos do app, com vendedor/empresa como protagonista |
| [`briefing-ui-ux.md`](briefing-ui-ux.md) | Briefing de UI/UX; o sistema visual é [`../brand/DESIGN.md`](../brand/DESIGN.md), sem repetir tokens aqui |
| [`schema-backend.md`](schema-backend.md) | Visão de schema do backend; a fonte das tabelas é [`../data-model/marketplace.md`](../data-model/marketplace.md) — aqui fica só a intenção de nível de produto |
| [`plano-implementacao.md`](plano-implementacao.md) | Plano de implementação da frente de vídeo: Bunny multi-tenant + trava de resolução por mensalidade do vendedor, depois BYOS |

## O que NÃO vive aqui

| Assunto | Dono |
|---|---|
| Por que o sistema é assim | [`../architecture/decisoes-marketplace.md`](../architecture/decisoes-marketplace.md) |
| Estado atual e próximas fases | [`../architecture/estado-e-proximas-fases.md`](../architecture/estado-e-proximas-fases.md) |
| Tabelas e campos | [`../data-model/marketplace.md`](../data-model/marketplace.md) |
| Tokens visuais | [`../brand/DESIGN.md`](../brand/DESIGN.md) |
| Registros de decisão (um por arquivo) | [`../adr/`](../adr/) |

## Decisões de produto (referência)

A intenção em uma página; o detalhe vive no [`prd.md`](prd.md) e no
[`trd.md`](trd.md).

- **Ordem das frentes:** **B** (Bunny nativo multi-tenant, uma `library` por
  empresa) **com C dentro** (trava de resolução por mensalidade do vendedor),
  depois **A** (BYOS).
- **Planos** (**a confirmar**): Free ~**10%** (YouTube **ou** Bunny limitado);
  ~**50–100** remove o limite (o SaaS paga a banda — **calcular custos antes**);
  ~**300** = ~**5%** + BYOS. A assinatura usa a paymentarea `'plan'`
  existente.
- **Fora da v1:** Cloudflare, troca do `mod_ldgvideo`, Fase 5 moodledata, cota
  de banda por empresa.
- **Sucesso:** gate de release (trava de resolução) + 1 venda em cada degrau.
- **Base Bunny:** fork de `amirtds/moodle-mod_bunnystream` + multi-tenant.
- **Trava** no player (`core_media_manager`), não só na origem.
- **Protagonista:** vendedor/empresa.
