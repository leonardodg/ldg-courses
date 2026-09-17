# Ativação de Empresa Parceira — Telas (m3e-canvas)

Desenho de **11 telas** do fluxo de ativação de uma empresa parceira no LDG
Courses, criadas para a ferramenta **M3E Canvas**
(https://lnkiai.github.io/m3e-canvas/).

> **Fase 1 do plano — desenho primeiro, implementação depois.** Este documento e
> o `design.json` são o *contrato visual* que será aprovado. Nenhuma
> funcionalidade do `block_marketplace` é implementada antes de as telas
> passarem por revisão.

---

## Abrir o desenho

Há dois arquivos nesta pasta:

| Arquivo | Para quê |
|---|---|
| `design.json` | O documento de design (formato do m3e-canvas). Abra com **Open project**. |
| `design.link` | O mesmo desenho como link compartilhável `#docz=` (carrega o desenho no canvas). |

**Como abrir o link:** cole a URL de `design.link` no navegador. Nada é
armazenado em servidor — o desenho inteiro vai na própria URL (por isso ela é
longa).

**Como abrir o JSON:** na tela do m3e-canvas, use **Open project** e selecione o
`design.json`.

---

## O que está desenhado

O desenho tem **11 telas**, cada uma em duas larguras (celular 412×892 e
desktop 1280×800). Telas com o mesmo nome são *uma* tela em duas larguras.

| # | Nome | Contexto | O que mostra |
|---|---|---|---|
| D1 | Ativar empresa | Dashboard — empresa em ativação | Wizard: checklist das 6 etapas, barra de progresso, botão Continuar por etapa, bloco "Como funciona" |
| D2 | Painel da empresa | Dashboard — empresa ativa | 3 cards (alunos, assinaturas ativas, cursos vendidos) + links relatório/ofertas/cursos |
| D3 | Conta de pagamento | Dashboard — etapa 4 | Seção por país, botão por gateway (MP/Asaas/Pagar.me), estado vinculado/habilitado |
| D4 | Plano de assinatura | Dashboard — etapa 5 | Cards: Free, nativa R$50 (1024), nativa R$100 (4k), Profissional BYOS ("em breve", desabilitado) |
| D5 | Documento CNPJ ou CPF | Dashboard — etapa 3 | Pessoa física ou jurídica, campo do documento, validação |
| D6 | Termos de contrato | Dashboard — etapa 2 | Aceite registrando o momento do consentimento |
| D7 | Aprovações de parceiros | Admin — fila | Empresas a aprovar, prioridade plano pago > free, aprovar/recusar com nota, modo manual/auto |
| C1 | Bloco do curso — admin | Bloco no curso | Estado da empresa, configurações, relatórios do curso |
| C2 | Bloco do curso — cadastro | Bloco no curso | Compacto: etapa pendente + link para o wizard |
| C3 | Bloco do curso — ativa | Bloco no curso | Números do painel, escopados ao curso |
| C4 | Bloco do curso — aluno | Bloco no curso | Assinatura + vencimento, histórico, próximas faturas, prazo do curso avulso |

### Navegação entre telas

O desenho conecta as etapas do wizard: D1 → D6 (termos) → D5 (documento) → D3
(pagamento) → D4 (plano) → D7 (aprovação). Os botões **Voltar ao checklist**
retornam a D1. Os blocos de curso apontam para o wizard quando há etapa pendente.

---

## Tema (paleta LDG)

O `design.json` usa a paleta **blue** do m3e-canvas. Ao refinar no painel de
tema, alinhe ao design system do projeto (`docs/brand/design_system_leodg.md` e
`DESIGN.md`):

| Token | Valor LDG |
|---|---|
| Fundo | `#121212` (grafite de estúdio) |
| Superfície | `#1E1E1E` (carvão elevado) |
| Ativo | `#252525` (carvão ativo) |
| Acento primário | `#007AFF` / `#4B8EFF` (azul elétrico) |
| Texto alto | `#FFFFFF` |
| Texto secundário | `#B0B3B8` |
| Fonte | Inter (corpo) + JetBrains Mono (rótulos) |

---

## Como gerar o prompt de implementação

1. No canvas, use **Export → Copy prompt** (ou o painel de prompt).
2. Escolha **Web** como alvo (não Android).
3. O prompt vira a base para os templates Mustache e o `styles.css` do
   `block_marketplace` no projeto — renderizado no **mesmo Chrome e mesmo
   viewport** para conferência visual (regra do `docs/dev/padrao-de-implementacao.md`).

---

## O que validar na revisão

- Cada etapa do checklist tem os 3 estados possíveis: **pronta** (✓), **pendente**
  (▶) e **bloqueada** (cadeado).
- Os botões "Continuar" levam à tela certa (D1 → D6 → D5 → D3 → D4 → D7).
- Responsividade: no celular as etapas empilham em coluna única; no desktop usam
  rail lateral + cards.
- Contraste AA e alvo de toque ≥ 44px.
- Texto em **pt_BR** em todas as telas.