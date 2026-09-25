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
| 1 | **Lista de contradições, faltas e pontos em aberto** — entregue e aceita pelo usuário | **FECHADA em 2026-09-25.** Documentada em [`gate-contradicoes.md`](gate-contradicoes.md), com aceite item a item: ADR-0014 promovido a Aceita, provisionamento automático da `library` no `create_company`, Free com os dois modos (YouTube ou Bunny limitado), correções editoriais (C1/C3/C4/C5/F6) aplicadas. Rótulos de degrau/mensalidade (C2) e números comerciais (A1) seguem a confirmar, sem bloquear código — código lê o banco, nunca hardcoda. |
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

1. **Mapear `company` ↔ `library` — feito em 25/09/2026.** Vínculo em
   `local_marketplace_library` (padrão de `local_marketplace_account`), com a
   `library` provisionada **por API, automaticamente no `create_company`**,
   numa **conta Bunny única da plataforma** (não é a conta do vendedor — essa
   é a Frente A). Provado ao vivo contra a conta Bunny real: cria e apaga
   library de verdade, com rollback da empresa quando a chamada falha. Achado
   que mudou o desenho original: a Bunny só devolve `Id`/`ApiKey` na criação —
   `securitykey` e `cdnhostname` nascem nulos, e ficam para o sub-passo 2/3.
   Detalhe, achados e testes:
   [`../ai-plans/2026-09-25-bunny-multi-tenant-trava-resolucao.md`](../ai-plans/2026-09-25-bunny-multi-tenant-trava-resolucao.md).
2. **Integrar a base `amirtds/moodle-mod_bunnystream` — feito em 25/09/2026.**
   Fork completo (`mod_bunnystream`), sem o singleton de credencial do
   original: cada endpoint resolve a library pela empresa dona do **curso**
   (`config::for_course()`). Webhook por library (segredo próprio, gerado na
   criação), não por instalação. 19 testes novos, phpcs limpo, build AMD sem
   erro. Detalhe no ai-plan de hoje (link acima).
3+4. **Trava de resolução no player e na origem — feito em 25/09/2026, num só
   mecanismo.** Achado que mudou o desenho: o player do `mod_bunnystream` é o
   iframe hospedado da própria Bunny — o seletor de qualidade é dela,
   client-side, a partir do manifesto HLS. Travar a origem (o
   `EnabledResolutions` da `library`) **é** travar o player: o aluno nunca
   vê a opção acima do teto, não é "oferece e recusa". Implementado:
   `plan::max_resolution()` (teto pela mensalidade, não por ticket),
   `bunny_platform_client::enabled_resolutions_for_cap()`/
   `update_library_resolutions()`, e `api::sync_video_library_resolution()`
   chamado em todo lugar onde `company.planid` muda de verdade. Provado ao
   vivo: empresa com plano 1080p nasce com `EnabledResolutions` até 1080p;
   upgrade para 4k atualiza a Bunny na hora. Detalhe no ai-plan de hoje.
5. **Testes** — 10 testes novos (unitário + integração com cliente Bunny
   mockado), todos verdes. Behat que mede a tela do seletor de qualidade
   fica para quando houver UI própria de autoria a medir — hoje o player é
   o iframe da Bunny, fora do DOM que o Behat deste projeto mede.

#### Critérios de pronto da frente B+C

| # | Critério | Tipo |
|---|---|---|
| 1 | **Gate de release:** trava aplicada no player — **fechado em 25/09/2026**. O player do `mod_bunnystream` não é o `core_media_manager` (é o iframe hospedado da Bunny — decisão registrada no ai-plan de hoje); a trava é o `EnabledResolutions` da `library`, sincronizado com `plan::max_resolution()`. Provado ao vivo, o aluno não alcança trilha acima do plano | técnico |
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
| Trava de resolução (vigente: ADR-0014, Aceita) | [`../adr/0014-trava-de-resolucao-por-mensalidade-do-vendedor.md`](../adr/0014-trava-de-resolucao-por-mensalidade-do-vendedor.md) · [`../adr/0005-trava-de-resolucao-por-ticket.md`](../adr/0005-trava-de-resolucao-por-ticket.md) (superado) |
| Hub de produto | [`README.md`](README.md) |
