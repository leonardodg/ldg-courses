---
name: LeoDG Course Portal
colors:
  bg-canvas: "#121212"
  surface-base: "#1E1E1E"
  surface-active: "#252525"
  surface-lowest: "#0E0E0E"
  border-hairline: "#3A3B3C"
  primary: "#4B8EFF"
  primary-hover: "#65A1FF"
  primary-container-on: "#00285C"
  primary-light: "#ADC6FF"
  text-primary: "#FFFFFF"
  text-secondary: "#B0B3B8"
  text-muted: "#6C7075"
  status-success: "#30D158"
  status-warning: "#FFD60A"
  status-error: "#FF453A"
---

# Design System: LeoDG Course Portal

Extraído dos mockups do portal do aluno em 03/09/2026: o app React
(`src/index.css`, bloco `@theme` do Tailwind v4 — é ele a fonte autoritativa) e o
mockup Bootstrap 5.3.3 (`public/bootstrap-portal.html`), que traduz o mesmo
desenho para a mesma versão de Bootstrap que o Moodle 5.2 compila.

Este documento é o **contrato visual** do `theme_ldg` e do `format_ldg`. Quando a
tela e o documento discordarem, um dos dois está errado — e é isso que a
conferência no Chrome mede.

## O que a implementação provou diferente

Implementado e conferido no Chrome em 03/09/2026. Três pontos do desenho **não**
sobreviveram ao contato com a tela, e aqui o código venceu:

**O cinza de rótulo `#6C7075` reprova em acessibilidade.** Dá 3,34:1 sobre
`#1E1E1E` e 3,76:1 sobre `#121212` — abaixo de AA para texto pequeno, e é
exatamente onde o desenho o usa, em rótulo de 12px. No tema ele é `#8A8F94`
(5,11:1). No modo claro, `#5C6A80`.

**O azul precisou de um segundo papel.** Branco sobre `#4B8EFF` no modo claro dá
4,02:1 e reprova, então existe `--ldg-accent-fill` — o **preenchimento** de
superfície com texto por cima —, separado do acento: `#4B8EFF` no escuro (com
`#00285C` por cima, 4,54:1) e `#0062CC` no claro (com branco, 5,80:1).

**A proporção 16/9 do quadro ficou para depois.** Enquanto o miolo for o iframe
de uma atividade qualquer, proporção fixa briga com o `player.js` — que mede a
altura do conteúdo — e ainda corta um quiz, que não tem cara de vídeo. A moldura
implementada é o raio de 14px, o preto de poço e a sombra; os 16/9 chegam com o
`mod_video`.

**Os ícones não entraram**, e não por esquecimento: a navegação é texto e a lista
de materiais usa o ícone que o Moodle já dá para cada atividade. Não houve `bi-*`
para traduzir.

Medidas conferidas no navegador, nos dois viewports e nos dois modos: cabeçalho
56px, menu 280px, índice 360px, barra inferior 60px, viradas em `lg` e `xl`,
quadro estável e zero rolagem horizontal.

## 1. Visual Theme & Atmosphere

Escuro de estúdio, não escuro de moda. O fundo é um grafite quase neutro
(`#121212`) e as superfícies sobem em degraus curtos — `#1E1E1E` para o cartão,
`#252525` para o estado ativo — o que produz hierarquia sem sombra e sem borda
gritante. As divisões são fios de 1px em `#3A3B3C`, no limite do perceptível. A
temperatura é **fria e sóbria**: nada de creme, nada de calor; o único acento é
um azul elétrico (`#4B8EFF`) usado com parcimônia, quase sempre em uma coisa por
tela — o botão de play, a aula em reprodução, a próxima aula.

A densidade é de ferramenta de trabalho, não de vitrine: três colunas no desktop,
respiro de 16 a 24px, tipografia pequena e apertada em rótulos e generosa no
corpo da aula. A monoespaçada (JetBrains Mono) aparece o tempo todo em rótulos,
durações, contadores e código — é ela que dá o sotaque "dev" e diferencia o
produto de um LMS genérico. O conjunto comunica **sala de aula particular**, com
foco no conteúdo e o resto da interface recuando para o fundo.

## 2. Color Palette & Roles

### Primary Foundation

| Nome | Hex | Papel |
|---|---|---|
| **Grafite de Estúdio** | `#121212` | fundo da tela, canvas do miolo |
| **Carvão Elevado** | `#1E1E1E` | superfície de cartão, cabeçalho, laterais, barra de baixo |
| **Carvão Ativo** | `#252525` | item de navegação ativo, cabeçalho de módulo aberto, hover |
| **Preto de Poço** | `#0E0E0E` | fundo do quadro de vídeo e do bloco de código — o mais fundo da pilha |
| **Fio de Divisão** | `#3A3B3C` | bordas de 1px, separadores, trilha da barra de progresso |

Escala completa de superfícies do `@theme` (para estados intermediários):
`#1B1B1C` · `#202020` · `#2A2A2A` · `#2E2E2E` · `#353535`.

### Accent & Interactive

| Nome | Hex | Papel |
|---|---|---|
| **Azul Elétrico** | `#4B8EFF` | ação primária, aula em reprodução, progresso, ícone ativo |
| **Azul Elétrico Claro** | `#65A1FF` | hover do botão primário |
| **Azul Profundo** | `#00285C` | texto/ícone **sobre** o azul elétrico — é o par de contraste |
| **Azul Névoa** | `#ADC6FF` | texto de acento em superfície escura, rótulos informativos |
| **Véu de Acento** | `rgba(0,122,255,.12)` | fundo de etiqueta e de estado selecionado |

### Typography & Text Hierarchy

| Nome | Hex | Papel |
|---|---|---|
| **Branco Puro** | `#FFFFFF` | títulos, texto ativo, corpo da aula |
| **Cinza de Leitura** | `#B0B3B8` | texto secundário, subtítulo, descrição |
| **Cinza de Rótulo** | `#6C7075` | rótulo mono, item inativo, metadado |

### Functional States

| Nome | Hex | Papel |
|---|---|---|
| **Verde Conclusão** | `#30D158` | aula concluída, "Concluir Aula" |
| **Âmbar Atenção** | `#FFD60A` | certificado pendente, aviso |
| **Vermelho Falha** | `#FF453A` | erro, acesso negado |

## 3. Typography Rules

**Inter** para tudo que é prosa e interface: humanista, neutra, legível em corpo
pequeno. **JetBrains Mono** para rótulo, duração, contador, nome de arquivo e
código — é o sotaque da marca, não decoração.

### Hierarchy & Weights

| Papel | Tamanho / entrelinha | Peso | Observação |
|---|---|---|---|
| Título da aula (desktop) | 36–40px / 1.15 | 700 | quebra em 3-4 linhas de propósito |
| Título da aula (celular) | 22px / 30px | 700 | `text-headline-lg-mobile` |
| Título de bloco | 19px / 26px | 600 | "Notas & Fundamentos" |
| Corpo | 15px / 24px | 400 | entrelinha 1.6 — leitura longa |
| Legenda | 14px / 20px | 400 / 600 | metadado e rótulo de aba |
| Mono | 13px / 20px | 400–600 | rótulo, duração, contador |
| Mono pequeno | 12px / 18px | 400–500 | código, nome de arquivo, `1080p 60fps` |
| Rótulo de seção | 12–13px | 700 | **caixa alta**, mono, com `letter-spacing` |

### Spacing Principles

Rótulo em caixa alta sempre com espaçamento de letra folgado (`0.05em`+) e cor
`#6C7075` — é o que separa "rótulo" de "conteúdo" sem precisar de linha. Corpo
com entrelinha generosa (1.6); títulos apertados (1.15) para virarem bloco visual.
Números — duração, progresso, contagem — **sempre em mono**, para alinharem em
coluna.

## 4. Component Stylings

### Buttons

Raio de **8px**, altura confortável (~38–44px), ícone à esquerda e texto em 500/600.

- **Primário:** fundo azul elétrico, texto azul profundo. Sem borda.
- **Fantasma:** transparente, texto cinza de leitura, borda 1px no fio de divisão;
  no hover ganha `#252525` e texto branco.
- **Sucesso:** contorno verde sobre fundo escuro, para "Concluir Aula".
- Transição de `0.2s ease` em cor e transformação.

### Cards & Painéis

Raio **12px** (bloco de código, painéis internos) e **14px** no quadro de vídeo.
Fundo `#1E1E1E`, borda de 1px no fio de divisão, e sombra só onde há elevação de
verdade: `0 10px 30px rgba(0,0,0,.6)` no quadro. Painel interno "afundado" usa
preto a 40% de opacidade sobre o cartão, em vez de mais uma borda.

### Navigation

**Lateral esquerda (280px):** itens empilhados, raio 8px, 12px de padding
vertical, texto 13px/500 em cinza de leitura. O ativo recebe fundo `#252525`,
texto branco, peso 600 e uma **barra de 4px em azul elétrico na borda esquerda** —
é o marcador de estado do sistema, e ele se repete no item de aula.

**Lateral direita (360px):** acordeão sem bordas de caixa; cada módulo é
separado por um fio a 40% de opacidade. Cabeçalho de módulo com 12px/16px de
padding, 0.85rem; aberto, muda para `#252525`.

**Item de aula:** 10px/16px, 0.8rem, ícone de estado à esquerda e duração em mono
à direita. Ativo repete a barra de 4px azul. Hover só troca o fundo para
`#1E1E1E`.

**Barra inferior (celular):** altura **60px**, fundo `rgba(30,30,30,.96)` com
`backdrop-filter: blur(10px)`, borda superior de 1px, ícone sobre rótulo de
0.65rem. Ativo em azul elétrico e 600.

**Abas (celular e miolo):** `nav-pills` dentro de uma caixa de 1.5 de padding com
borda e raio 12px — a aba ativa é uma pílula preenchida, não um sublinhado.

### Inputs & Forms

Campo escuro (`#0E0E0E`), borda no fio de divisão, raio 8px, ícone dentro do
grupo à esquerda. Foco: borda azul elétrico, sem `box-shadow` largo — o portal
evita halo.

### Componentes próprios do domínio

**Quadro de vídeo:** proporção 16/9, raio 14px, poster com
`brightness(.75) contrast(1.05)`, véu em gradiente do preto 92% (base) ao 30%
(meio). Botão de play central de 64px, círculo azul, borda branca a 40% e
**brilho `0 0 25px rgba(75,142,255,.6)`**. Barra de busca de 6px que cresce para
8px no hover, com alça branca de 10px.

**Bloco de código:** raio 12px, cabeçalho `#1E1E1E` com três pontos de 11px e o
nome do arquivo em mono; corpo em `#0E0E0E`.

**Etiqueta de módulo:** caixa alta, mono, azul, sobre véu de acento — usada acima
do título da aula.

## 5. Layout Principles

### Grid & Structure

Três faixas fixas no desktop: **280px** (navegação) · fluida (miolo) · **360px**
(conteúdo do curso). Cabeçalho de **56px**, colado no topo (`sticky`), e as duas
laterais coladas logo abaixo dele, cada uma rolando por dentro
(`height: calc(100vh - 56px)`). O miolo tem `max-width` de leitura confortável e
padding de 24px.

Colapsar uma lateral é `margin-left/-right` negativo com transição de `0.25s` —
ela sai da tela sem reflow do miolo.

### Whitespace Strategy

Base de **4px**, usada em 4 · 8 · 12 · 16 · 24 · 32. Padding de cartão: 16px no
celular, 24–32px no desktop. Espaço entre blocos do miolo: 24px (`mb-4`).

### Alignment & Visual Balance

Tudo alinhado à esquerda — não há centralização, nem no título. O peso visual
fica no quadro de vídeo (o maior objeto da tela) e desce em degraus: quadro →
navegação entre aulas → título → corpo. A coluna direita é deliberadamente mais
clara em conteúdo e mais densa em linhas, para ler como índice.

### Responsive Behavior & Touch

Mobile-first. `lg` (992px) traz a lateral esquerda; `xl` (1200px) traz a direita.
Abaixo de `lg`: coluna única com `max-width: 500px` centralizada, padding lateral
zerado, **5rem de padding inferior** para a barra fixa não cobrir conteúdo, e a
navegação vira abas + barra inferior + gaveta (`offcanvas`). Alvos de toque
mínimos de 44px; itens da barra inferior ocupam a altura inteira dos 60px.

### Movimento

Discreto e curto: `0.15s` para fundo, `0.2s` para botão, `0.25s` para colapso de
lateral, `0.4s` para o zoom de 1.02 do poster no hover. Nada de mola, nada de
entrada animada.

## 6. Design System Notes for Stitch Generation

### Language to Use

"Portal de curso escuro, de estúdio: grafite neutro, superfícies em degraus
curtos, fios de 1px, um único acento azul elétrico. Tipografia Inter com rótulos
e números em JetBrains Mono maiúsculo. Três colunas: navegação estreita,
conteúdo, índice de módulos. Denso, alinhado à esquerda, sem ornamento."

### Color References

Grafite de Estúdio `#121212` · Carvão Elevado `#1E1E1E` · Carvão Ativo `#252525` ·
Fio de Divisão `#3A3B3C` · Azul Elétrico `#4B8EFF` sobre Azul Profundo `#00285C` ·
Cinza de Leitura `#B0B3B8` · Verde Conclusão `#30D158`.

### Component Prompts

- "Item de navegação lateral com ícone, rótulo 13px e barra de 4px azul elétrico
  à esquerda quando ativo, fundo `#252525`, raio 8px."
- "Cartão de aula em acordeão: número ou ícone de estado, título em duas linhas,
  duração em mono à direita, separador de 1px a 40%."
- "Quadro de vídeo 16/9, raio 14px, véu em gradiente e botão de play circular de
  64px com brilho azul."

### Incremental Iteration

Mudar **um** eixo por vez: primeiro a paleta, depois a tipografia, depois a
densidade. O desenho é frágil a mudanças de densidade — apertar o padding do
cartão quebra a hierarquia mais rápido que trocar cor.

## 7. Tradução para o `theme_ldg` (Moodle)

O tema hoje tem `--ldg-*` com claro e escuro, accent `#007aff` e navy `#0e192b`.
A adoção mantém os dois modos:

| Token do portal | Token do tema | Escuro | Claro (a re-afinar) |
|---|---|---|---|
| `bg-canvas` | `--ldg-bg` | `#121212` | manter `#f4f6fa` |
| `surface-base` | `--ldg-surface` | `#1E1E1E` | `#ffffff` |
| `surface-active` | `--ldg-surface-raised` | `#252525` | `#eef2f8` |
| `border-hairline` | `--ldg-border` | `#3A3B3C` | `#d5dce6` |
| `primary` | `--ldg-accent` | `#4B8EFF` | `#0062cc` (contraste AA em fundo claro) |
| `text-secondary` | `--ldg-text-muted` | `#B0B3B8` | `#5a6b82` |
| `status-success` | novo, `--ldg-success` | `#30D158` | verde escurecido |

**Três avisos para a implementação:**

1. **Contraste** (medido, não estimado). `#B0B3B8` sobre `#1E1E1E` dá **7,93:1** —
   passa folgado. Mas o cinza de rótulo `#6C7075` dá **3,76:1** sobre `#121212` e
   **3,34:1** sobre `#1E1E1E`: **abaixo de AA para texto pequeno**, e é justamente
   nele que o mockup põe rótulo de 12px. No tema o rótulo sobe para `#8A8F94`
   (**5,74:1**) ou cresce para 14px/600, que muda a régua para 3:1.
   O par do botão primário — `#00285C` sobre `#4B8EFF` — dá **4,54:1**: passa em
   AA para texto normal, mas sem margem, então não escureça o azul sem refazer a
   conta.
2. **O azul elétrico não é o accent do tema.** `#4B8EFF` sobre branco dá
   **3,18:1** — reprova para texto. No claro fica `#0062CC` (**5,80:1**); só o
   escuro recebe o valor do mockup, onde ele dá 5,90:1 sobre o grafite.
3. **Ícones.** O mockup é Bootstrap Icons; o tema usa FontAwesome 6. A tradução é
   um-a-um e vive numa tabela no README do tema, não espalhada nos templates.
