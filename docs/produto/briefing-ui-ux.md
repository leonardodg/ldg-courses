# Briefing de UI/UX — superfícies de produto

> Intenção de UX nas superfícies que o **vendedor/empresa** (e, como
> consequência, o aluno) toca. **Não é redesenho do sistema visual**: cores,
> tipografia, tokens e o contrato visual do `theme_ldg`/`format_ldg` vivem em
> [`../brand/DESIGN.md`](../brand/DESIGN.md), que é a fonte única — este
> documento só aponta para lá e **não copia nenhum valor** de lá.

Acompanha o [`prd.md`](prd.md), o [`trd.md`](trd.md) e o
[`fluxo-do-app.md`](fluxo-do-app.md): aqui fica a **intenção de UX** das telas
de produto; arquitetura, tabelas e decisões de cobrança têm dono próprio.

## Escopo — superfícies de produto

Protagonista, como no PRD: **quem publica, opera e monetiza**. Cada superfície
tem o papel dela no fluxo do vendedor:

| Superfície | Papel de UX |
|---|---|
| **Vitrine** | Oferta mostrada ao aluno: país, preço e o que a oferta entrega — construída só com **campos**, nunca com HTML do vendedor (ver princípios) |
| **Checkout** | O **aluno** escolhe o gateway; o valor e a moeda vêm da oferta — a tela não recalcula nem esconde o degrau |
| **Painel da empresa** (`company.php`) | Meio de pagamento, plano e estado da assinatura SaaS — sem tela nova para nada disso |
| **Onboarding checklist** (Dashboard) | O que falta para a empresa vender; some quando a empresa está completa |
| **Player com trava de resolução** (**planejado**, pós-gate) | O seletor de qualidade **não oferece** trilha acima do plano — o limite se apresenta como degrau de plano, não como defeito |

Fluxos ponta a ponta de cada uma: [`fluxo-do-app.md`](fluxo-do-app.md). Aqui,
só o que a tela precisa comunicar.

## Sistema visual — dono é o brand

Todo valor de cor, tipografia, raio, espaçamento e estado vem de
[`../brand/DESIGN.md`](../brand/DESIGN.md). Este briefing:

- **não** repete paleta, fontes nem tokens;
- **não** introduz novos valores de cor/tipografia;
- quando discordar do brand em alguma tela de produto, o brand vence até que
  o próprio brand seja revisado — não se ajusta token por tela.

Rodapé e barras de controle **escuros nos dois modos** são decisão de produto
já tomada (abaixo), não um token novo: aplicam o que o brand define para o
cromo, sem inventar segunda paleta.

## Princípios já decididos (não reverter)

Estes seis pontos estão fechados — viraram código, teste ou revisão de tela.
Nenhum desenho futuro os reabre por preferência estética:

1. **Campos, não HTML livre, na vitrine.** HTML do vendedor A rodando no
   navegador do aluno da empresa B é XSS entre inquilinos. Quem quer página
   própria usa a API. Detalhe em
   [`../architecture/decisoes-marketplace.md`](../architecture/decisoes-marketplace.md).
2. **Home e landing são a mesma página.** Quem serve a landing na raiz **não
   monta cromo extra** — duas barras e dois rodapés na mesma tela é bug, já
   aconteceu, e nenhum teste de servidor pega: conta os elementos.
3. **Rodapé e barras de controle escuros nos dois modos**, como a navbar —
   consistência de cromo entre claro e escuro, decidida na revisão tela a
   tela de 10/09/2026.
4. **Quem já entrou vê SAIR; anônimo vê ENTRAR**, com `sesskey` no
   endereço. O botão não "some" para usuário logado.
5. **Checklist no Dashboard para empresa incompleta; widget de aluno quando
   completa.** Quem tem `local/marketplace:managecompany` vê o resumo do que
   falta em vez do widget; empresa completa devolve o widget. O bloco não
   tem página própria — o link leva a `company.php`. Ver seção 2 do
   [`fluxo-do-app.md`](fluxo-do-app.md).
6. **A trava de resolução deve parecer limite de plano, não defeito.** O
   seletor do player **só mostra as trilhas permitidas** — o aluno não
   escolhe 4K e leva erro: 4K simplesmente não aparece no menu do que o
   plano dele inclui. Sem isso, o aluno lê a trava como player quebrado.
   Fronteira técnica (player + origem): [`trd.md`](trd.md), Frente C.

## Acessibilidade e medição

O projeto **já mede layout** — cenários behat `@javascript` que medem a tela
depois do JavaScript, número antes de opinião. Não há métrica nova inventada
aqui: **UI de produto nova segue a mesma barra**.

| Referência | O que cita |
|---|---|
| [`../dev/padrao-de-implementacao.md`](../dev/padrao-de-implementacao.md) | Onde "a tela parece o que foi desenhado?" se responde com behat que **mede** — o mesmo roteiro de qualquer feature |
| [`../data-validation/local-partners-layout.md`](../data-validation/local-partners-layout.md) | Roteiro de medição de tela já em uso (larguras, colunas, rolagem, contraste) — alvos e método existem; não se redefine aqui |
| [`../dev/portal-conferencia-visual.md`](../dev/portal-conferencia-visual.md) | Conferência no Chrome contra o design system, o que behat e phpunit não pegam |

Barra prática: **toda superfície nova do escopo acima entra com cenário que
mede** o que ela promete (checklist some quando completo; seletor do player
não oferece trilha acima do plano; ENTRAR/SAIR por estado de sessão), mais a
conferência manual no navegador quando o cenário não cobrir. Critérios de
sucesso do produto como um todo continuam no [`prd.md`](prd.md) — este
briefing não define gate próprio.

## Fora deste briefing

| Assunto | Por quê / dono |
|---|---|
| Propriedade do `theme_ldg` | É decisão de tema, não de UX de produto — não se revisita aqui |
| `m3e-canvas` | Tentado e abandonado duas vezes; ferramenta errada para wireframe de página inteira (histórico em `CLAUDE.md` / planos `ai-plans`) |
| Conjunto completo de mockups | Não se refaz: as telas de onboarding já têm referência aprovada em [`../design/block-marketplace-onboarding/html-mockups/`](../design/block-marketplace-onboarding/html-mockups/) |
| Tokens, paleta, tipografia | [`../brand/DESIGN.md`](../brand/DESIGN.md) |
| Desenho de telas do onboarding (original) | [`../ai-plans/2026-09-17-ativacao-empresa-desenho-telas.md`](../ai-plans/2026-09-17-ativacao-empresa-desenho-telas.md) e o plano de implementação do `block_marketplace` |
| Regras de segurança da vitrine (por quê) | [`../architecture/decisoes-marketplace.md`](../architecture/decisoes-marketplace.md) |

## Onde viver o resto (não duplicar)

| Assunto | Dono |
|---|---|
| Tokens e contrato visual | [`../brand/DESIGN.md`](../brand/DESIGN.md) |
| Intenção de produto e gates | [`prd.md`](prd.md) |
| Requisitos técnicos (inclui trava no player) | [`trd.md`](trd.md) |
| Fluxos ponta a ponta | [`fluxo-do-app.md`](fluxo-do-app.md) |
| Por que a arquitetura é assim | [`../architecture/decisoes-marketplace.md`](../architecture/decisoes-marketplace.md) |
| Estado e fases | [`../architecture/estado-e-proximas-fases.md`](../architecture/estado-e-proximas-fases.md) |
