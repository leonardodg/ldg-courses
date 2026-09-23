# Plano de implementação — frente de vídeo

> Ordem aprovada: **B+C** (Bunny multi-tenant com a trava de resolução
> dentro) e só depois **A** (BYOS). Este plano **não** redesenha produto nem
> requisitos: a intenção vive no [`prd.md`](prd.md), os requisitos técnicos no
> [`trd.md`](trd.md). Aqui ficam o gate antes de qualquer código de vídeo, a
> sequência de fases e o que encerra cada uma — **por ordem, sem calendário**.

## Pré-condições (gate antes de qualquer código de vídeo)

Nenhuma frente abaixo começa enquanto os três itens não forem **entregues e
aceitos pelo usuário**. São portões, não tarefas paralelas:

| # | Pré-condição | O que é |
|---|---|---|
| 1 | **Lista de contradições, faltas e pontos em aberto** — entregue e aceita pelo usuário | Documentada em [`gate-contradicoes.md`](gate-contradicoes.md): varredura do que PRD, TRD, ADR-0005 e o desenho Start/PRO implementado dizem **ao mesmo tempo** e não batem (rótulos de degrau, tier→resolução, mensalidade do degrau BYOS vs `pro`, ADR novo ainda não escrito, `hostingmodel` sem storage de chave). Aceite formal antes de abrir branch de vídeo. |
| 2 | **Números de degraus comerciais: a confirmar** | Comissões (~10% / ~5%) e mensalidades (~R$ 50–100 / ~R$ 300) do [`prd.md`](prd.md) permanecem **a confirmar**. Nenhum código assume percentual ou faixa fechada — o que se lê é o banco (`commissionpct` / `monthlyfee` / tiers), nunca hardcode. |
| 3 | **Cálculo de custo de banda vs mensalidade** | Antes de **qualquer degrau pago ser liberado**: banda Bunny (GB transferido; 4K custa várias vezes 720p) contra a mensalidade cobrada. É o gate de custo do PRD; sem o número na mão, degrau pago não abre. |

A lista do item 1 é **pré-condição de implementação**, não revisão de meio de
caminho: o que se descobre depois do primeiro commit já custou branch, review
e rework. O arquivo da lista é [`gate-contradicoes.md`](gate-contradicoes.md).

## Sequência aprovada

Uma fase por vez. **Não abrir worktrees paralelos em conflito** — a próxima
fase só nasce depois do critério de pronto da anterior.

### 1. Frente B+C — Bunny multi-tenant + trava de resolução

Hospedagem nativa no Bunny com **uma `library` por empresa**, e a **trava de
resolução por mensalidade do vendedor aplicada no player** no mesmo movimento
(a trava só tem o que travar quando a plataforma é a origem do stream).

Sub-passes, nesta ordem:

1. **Mapear `company` ↔ `library`** — onde o vínculo viverá e quem
   provisiona; é fronteira da B e pré-condição da C e de qualquer medição de
   custo por empresa.
2. **Integrar a base `amirtds/moodle-mod_bunnystream`** — fork + camada
   multi-tenant sobre o mapeamento do passo 1 (requisito no
   [`trd.md`](trd.md), § Frente B).
3. **Hook no `core_media_manager`** — consumir o teto de resolução do
   plano/tier da empresa (`plan::max_resolution_for()`), que **já existe no
   banco**; o seletor de qualidade não oferece trilha acima do plano.
4. **Consumir o teto de resolução na origem** — URL assinada com teto
   (ou controle equivalente) no Bunny: player sozinho é contornável e não
   protege a fatura quando o provedor cobra por volume.
5. **Testes** — unitário para a regra; **behat onde mede a tela** (o seletor
   de qualidade e a proporção do player), conforme o critério de teste do
   [`../dev/padrao-de-implementacao.md`](../dev/padrao-de-implementacao.md).

#### Critérios de pronto da frente B+C

| # | Critério | Tipo |
|---|---|---|
| 1 | **Gate de release:** trava aplicada no player — `core_media_manager` respeita o teto e o aluno não alcança a trilha acima do plano | técnico |
| 2 | **1 venda real em cada degrau** (Free, intermediário, BYOS) — dinheiro de verdade, não sandbox | comercial |
| 3 | **Sem regressão nos 740 testes existentes** (baseline dos 11 testsuites customizados) | regressão |

O gate de custo (pré-condição 3) também precisa estar **calculado** antes de o
degrau pago abrir — ver [`prd.md`](prd.md), critérios de sucesso.

### 2. Frente A — BYOS

O produtor conecta a **chave de API da própria conta** de streaming e hospeda
fora da plataforma. Só entra depois do fechamento da B+C:

1. **Storage de chave** — definir **onde** grava a chave por empresa (hoje
   `plan.hostingmodel` é rótulo: `native` \| `byos`, sem storage — gap
   documentado no [`trd.md`](trd.md)).
2. **Roteamento de upload/origem conforme o plano** — quem aplica a chave no
   upload/stream e troca o destino conforme `hostingmodel` + plano
   contratado.

Sem as duas peças, **BYOS é promessa comercial sem peça técnica** e não pode
ser vendida como entregue (mesma leitura do TRD sobre `hostingmodel`).

### 3. Fora da v1 — não planejar agora

**Não são "depois sim" e não entram nesta sequência** (não-objetivos do
[`prd.md`](prd.md)):

| Fora da v1 | Por quê |
|---|---|
| Cloudflare | A frente escolhida é Bunny |
| Troca do `mod_ldgvideo` | É a peça do plano Free (embed, banda zero) |
| Fase 5 — moodledata | Bloqueada por decisão de negócio de cobrança |
| Cota de banda por empresa | Controle fino fica **depois** da trava de resolução |

## Como já se trabalha aqui

O fluxo de código não muda por ser frente nova. **Não se repete aqui** — quem
executa segue os donos:

- Worktree via `moodev new`; PR para `dev`; CI `moodle-extra`.
- Ordem não negociável: **terminar → verificar → commit → push → PR → CI →
  merge**.
- Padrão de feature (ciclo, testes, behat que mede, code review):
  [`../dev/padrao-de-implementacao.md`](../dev/padrao-de-implementacao.md).
- Da worktree ao deploy (ordem que impede commit órfão, CI, merge):
  [`../dev/fluxo-de-contribuicao.md`](../dev/fluxo-de-contribuicao.md).

## Ver também

| Assunto | Dono |
|---|---|
| Intenção de produto, ordem das frentes, gates | [`prd.md`](prd.md) |
| Contradições, faltas e abertos (pré-condição 1) | [`gate-contradicoes.md`](gate-contradicoes.md) |
| Requisitos técnicos por frente, pré-condições, riscos | [`trd.md`](trd.md) |
| Estado atual e fases do marketplace | [`../architecture/estado-e-proximas-fases.md`](../architecture/estado-e-proximas-fases.md) |
| Mecânica da assinatura SaaS (paymentarea `'plan'`) | [`../ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md`](../ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md) |
| Trava de resolução (será superada pelo novo ADR) | [`../adr/0005-trava-de-resolucao-por-ticket.md`](../adr/0005-trava-de-resolucao-por-ticket.md) |
| Hub de produto | [`README.md`](README.md) |
