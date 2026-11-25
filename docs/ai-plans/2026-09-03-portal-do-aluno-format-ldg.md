# Portal do aluno: layout independente para o `format_ldg`

**Estado:** desenho aprovado, implementação não começou.
**Branch:** `fix/format-ldg-navegador` · **Worktree:** `format-course-ldg`

---

## Contexto

O `format_ldg` hoje desenha o curso **dentro** da página do Moodle: a aula em
foco num quadro embutido, a lista de aulas ao lado, e em volta o chrome do tema —
navbar, drawers, menus. Funciona, e foi conferido no navegador em 02/09/2026.

O pedido é outro: a página do curso deve parecer **um produto próprio**, não uma
página do Moodle. Nas palavras do usuário, "as navlinks, menus que fazem parte do
theme principal não devem parecer ou ter o mesmo estilo".

A referência veio pronta e em Bootstrap 5.3.3 — a mesma versão que o Moodle 5.2
já compila (`theme/boost/scss/bootstrap`, banner `v5.3.3`):

| Arquivo | O que é |
|---|---|
| `portal-aluno-desktop.png` (2560×2764) | tela de referência, desktop |
| `portal-aluno-mobile.png` (780×2938) | tela de referência, celular |
| `portal-aluno-version-bootstrip-responsive.html` | o mockup em Bootstrap 5.3.3, responsivo |
| `leodg-course-portal.zip` | app React do AI Studio que originou o desenho; dentro dele, `public/bootstrap-portal.html` é a versão mais recente do mockup |

O desenho tem: cabeçalho próprio ("LeoDG Course Portal", lua, Fechar Aula),
coluna esquerda (área do aluno, progresso, quatro destinos), miolo (player,
navegação entre aulas, abas, conteúdo da aula), coluna direita (acordeão de
módulos e aulas), e no celular abas no meio, barra de navegação embaixo e gaveta
lateral.

No meio da conversa o escopo cresceu: **o `theme_ldg` também adota a linguagem
visual do portal** — o portal deixa de ser uma ilha e passa a ser o visual da
plataforma.

---

## Decisões

### 1. Quem vê o portal: aluno, e só na página do curso

O portal substitui o chrome **apenas** para quem não está editando, **apenas** na
página do curso. Professor com edição ligada continua vendo o Moodle de sempre —
navbar, drawers, pilha de seções, arrastar atividade. As páginas de atividade
seguem como hoje, dentro do quadro embutido.

*Recusado:* portal para todo mundo, com a edição acontecendo dentro dele. Exigiria
recolocar no portal tudo que o chrome do Moodle dá ao professor (menu do curso,
blocos, ações de atividade), e os ganchos do editor reativo não funcionam dentro
de um iframe — o que o `content.php` do formato já documenta.

### 2. Como o chrome é trocado: hook no formato, layout no tema

O `course/view.php` do core crava `$PAGE->set_pagelayout('course')` (linha 116),
depois de o formato já ter sido consultado. **O formato não escolhe o próprio
layout sozinho.**

A saída é o hook `before_http_headers`, despachado na primeira linha de
`core_renderer::header()` (`lib/classes/output/core_renderer.php:836`) — 28 linhas
antes de `layout_file($this->page->pagelayout)` resolver o arquivo, e com a página
ainda em `STATE_BEFORE_HEADER`. Um listener no `format_ldg` troca o layout para
`ldgportal`; o `theme_ldg` fornece o arquivo.

Quem **decide** é o formato, que sabe que é portal e sabe se o usuário está
editando. Quem **desenha** é o tema, que é o dono do visual por decisão anterior
do projeto.

*Recusado:* ramificar dentro do `theme_ldg/layout/drawers.php`. Funcionaria hoje,
mas faz o tema conhecer o formato pelo nome e dá uma segunda responsabilidade ao
arquivo que já serve base, standard, course, incourse e admin.

*Recusado:* o formato desenhar a página inteira em `format.php` com o layout
`embedded`. Em `format.php` o cabeçalho já saiu — tarde demais — e o `embedded`
do core não tem blocos nem menu de usuário.

### 3. Estrutura mora no formato, marca mora no tema

**Outras empresas podem usar o `format_ldg` com o tema delas.** Isso muda a
promessa antiga do README ("instalado com outro tema, aparece sem estilo"): sem
CSS nenhum, as três colunas do portal viram uma pilha de listas.

- **Estrutura, em `format_ldg/styles.css`:** grade das colunas, coluna direita
  grudada, proporção 16/9 do quadro, barra de baixo no celular, colapso das
  laterais. O Moodle carrega o `styles.css` do plugin para qualquer tema.
- **Marca, em `theme_ldg/scss/ldg/_format.scss`:** cor, tipografia, raio, sombra,
  densidade.

Com outro tema o portal fica **cinza mas usável**. Com o `theme_ldg`, fica o
mockup.

### 4. O player: moldura agora, player quando existir vídeo

O miolo continua sendo o **iframe da `view.php` da atividade** — é isso que faz a
visita ser real: log, "visualizado", restrição de acesso e nota, sem o formato
saber uma linha sobre quiz, page ou url.

O quadro ganha a moldura do mockup (16/9, cantos, sombra, faixa de título por
cima). Os **controles de vídeo não aparecem**, porque não há vídeo para
controlar. Quando o `mod_video` existir, ele traz o player de verdade dentro da
mesma moldura.

*Recusado:* detectar vídeo no formato e desenhar player próprio agora. Antecipa o
`mod_video` e cria duas implementações de player para conciliar depois.

### 5. As abas saem do tipo de atividade, sem configuração

O formato varre o `modinfo` e classifica: `forum` → Fórum, `customcert` →
Certificado, `resource`/`folder`/`url` → Materiais, o resto → aula. Todos esses
módulos já estão instalados nesta base.

**Destino sem conteúdo não aparece no menu.** É o que desarma o risco da
classificação automática: curso sem fórum não mostra aba de fórum vazia.

*Recusado:* o professor escolher nas opções do curso. A tela do aluno passaria a
depender de a configuração estar certa — e o projeto já tem a regra de não
aceitar solução que dependa disso.

*Ressalva conhecida:* uma aula que hoje seja um `mod_url` (link de vídeo) cai em
Materiais até o `mod_video` existir.

### 6. Uma navegação, três superfícies

Quatro destinos (Aulas, Materiais, Certificado, Fórum) e um estado corrente. No
desktop ele aparece como menu à esquerda; no celular como abas no meio e barra
embaixo. Trocar de destino troca o **miolo**; a coluna direita continua visível no
desktop.

O estado viaja na **URL**, como a aula já viaja hoje (`?lesson=<cmid>`, lido por
`get_selected_cm()` em `lib.php:156`): `?ldgview=lessons|materials|forum|certificate`.
Links de verdade, sem estado no cliente — botão voltar, favorito e Ctrl+clique
funcionam de graça.

### 7. O aluno não perde o menu de usuário

Tirar a navbar tira junto notificações, mensagens, perfil, idioma e **sair**. O
cabeçalho do portal ganha o avatar à direita, abrindo o menu de usuário do próprio
Moodle, desenhado com o estilo do portal.

### 8. O tema adota cor e tipografia do portal, mantendo claro e escuro

Os valores do portal viram o **escuro** do `theme_ldg`, e o **claro** é
re-afinado para o mesmo desenho. Inter e JetBrains Mono passam a ser
**self-hosted** no tema — nada de Google Fonts CDN, que na VPS vira dependência
externa numa página que o aluno abre todo dia.

Ícones continuam **FontAwesome 6**, que é o que o Moodle traz; os `bi-*` do
mockup são traduzidos um a um.

*Recusado:* embarcar também a fonte Bootstrap Icons (~120 KB) para bater ícone a
ícone. Duas famílias de ícone convivendo, pelo ganho de fidelidade em símbolos que
o FA já tem equivalente.

*Recusado:* abandonar o modo claro. Perderia a escolha do usuário e mexeria na
barra de acessibilidade que o tema já tem.

Os valores exatos, com o papel de cada cor, a escala tipográfica e os componentes,
estão em [`docs/brand/DESIGN.md`](../brand/DESIGN.md) — extraído do bloco `@theme`
do mockup React, que é a fonte autoritativa. **Aquele documento é o contrato; esta
tabela é só o resumo.**

| Token | Portal (escuro) | Tema hoje |
|---|---|---|
| fundo | `#121212` | `--ldg-bg: #f4f6fa` (claro) / navy (escuro) |
| superfície | `#1E1E1E` | `--ldg-surface` |
| borda | `#3A3B3C` | `--ldg-border: #d5dce6` |
| primário | `#4b8eff` | `--ldg-accent: #007aff` |
| sucesso | `#30D158` | — |
| texto secundário | `#B0B3B8` | `--ldg-text-muted: #5a6b82` |

---

## Desenho

### Peças, e de quem é cada uma

| Peça | Onde | Responsabilidade |
|---|---|---|
| Listener do hook | `format_ldg/classes/hook_callbacks.php` + `db/hooks.php` | Em `before_http_headers`: se é a página deste curso, o formato é ldg, o usuário não está editando e o tema ativo declara `ldgportal` → `set_pagelayout('ldgportal')`. Qualquer condição falhando, não faz nada. |
| Decisão isolada | mesma classe, função pura | Recebe formato, edição e lista de layouts do tema; devolve booleano. É o que o teste unitário exercita. |
| Catálogo | `format_ldg/classes/catalog.php` | Varre o `modinfo` **uma vez** e classifica em aulas (por módulo), materiais, fórum, certificado. Fonte única dos destinos. |
| Navegação | `format_ldg/classes/portalnav.php` | Lê `?ldgview=` junto do `?lesson=`, resolve o destino corrente e monta os destinos **existentes** com URL. |
| Aula em foco | `format_ldg/classes/output/courseformat/content/lessonviewer.php` | Já existe. Ganha a moldura 16/9; continua sendo o iframe da atividade. |
| Lista de aulas | `.../content/lessonlist.php` | Já existe. Remarcada como acordeão da coluna direita; progresso por módulo, aula corrente, concluída e bloqueada já vêm dela. |
| Estrutura | `format_ldg/styles.css` | Grade, colunas, proporções, breakpoints. Vale com qualquer tema. |
| Chrome | `theme_ldg/layout/ldgportal.php` + `templates/ldgportal.mustache` | Cabeçalho do portal, avatar com menu de usuário, alternador de tema, Fechar Aula. Sem navbar, sem drawers. |
| Marca | `theme_ldg/scss/ldg/_tokens.scss`, `_format.scss`, `theme/ldg/fonts/` | Paleta, tipografia, densidade. |

### Fluxo

```
clique num destino ou numa aula
  → link normal para course/view.php?id=N&ldgview=…&lesson=…
  → hook before_http_headers troca o layout para ldgportal
  → content.php pergunta ao catalog e ao portalnav o que mostrar
  → mustache
```

JavaScript só onde é interação de verdade: colapsar as laterais, abrir a gaveta
no celular, alternar o tema, e o `player.js` que já mede a altura do quadro.

### As quatro telas

**Aulas** (padrão). Miolo: moldura 16/9 com o quadro da atividade, faixa de
título, barra anterior/próxima e a descrição. Coluna direita: acordeão de módulos.
Aula bloqueada mantém cadeado e `lockinfo`, sem navegar.

**Materiais.** Agrupados por módulo, na ordem do curso. Material com página
própria (`folder`, `url`, `page`) abre no quadro; **arquivo com download forçado
não pode abrir no iframe** — dispara o download e deixa a tela em branco —, então
vira link de download. A regra é decidida pelo `mod`, não por adivinhação.

**Fórum.** Um fórum → abre no quadro. Mais de um → lista primeiro. Nenhum →
destino some.

**Certificado.** `customcert` → abre no quadro. Nenhum → destino some. Regra de
emissão continua sendo do `customcert`; o formato não a replica.

### Casos de borda

| Situação | Comportamento |
|---|---|
| `?ldgview=` desconhecido ou vazio | cai em Aulas, sem erro |
| `?lesson=` inválido, de outro curso ou invisível | cai na primeira aula visível (`get_selected_cm()` já faz) |
| Atividade fora do `uservisible` | não listada em lugar nenhum, nem contada no progresso |
| Curso sem atividade visível | moldura vazia com uma frase; nada de quadro branco |
| Sem JavaScript | tudo navega por link; laterais abertas, gaveta vira âncora |
| Edição ligada | chrome do Moodle e pilha de seções do core |
| Outro tema | layout padrão do tema + `styles.css` estrutural do formato |

### Acessibilidade

Requisito, não polimento: `aria-current` no destino ativo, foco visível nas
laterais colapsáveis, o `h2.accesshide` que já existe, e contraste AA nos dois
modos. A barra de acessibilidade do tema não pode regredir.

---

## Verificação

### TDD — o teste nasce antes do código

| Teste | Cobre |
|---|---|
| `catalog_test.php` | classificação por tipo; atividade invisível fora de tudo; curso vazio |
| `portalnav_test.php` | destino corrente; valor desconhecido caindo em Aulas; destino sem conteúdo ausente; URL preservando a aula |
| `hook_callbacks_test.php` | a decisão de trocar o layout: ldg + aluno + tema com `ldgportal` → verdadeiro; edição, outro formato ou tema sem o layout → falso |
| `lesson_test.php`, `section_progress_test.php`, `set_duration_test.php` | já existem, continuam passando |

### Behat

Estendendo `tests/behat/format_ldg.feature`: aluno abre o curso e **não** vê a
navbar; professor **sem editar** também vê o portal (a regra é a edição, não o
papel); com a edição ligada volta o Moodle de sempre; destino sem conteúdo não
aparece; trocar de destino preserva a aula.

Tudo isso **sem `@javascript`**, que é a regra do arquivo: este ambiente não tem
navegador, e a troca de chrome é decidida no servidor. O passo do axe-core existe
no 5.2 (`lib/tests/behat/behat_accessibility.php:42`) mas **exige** `@javascript`,
então a acessibilidade é medida na conferência com o Chrome, abaixo — prometê-la
no Behat seria prometer um teste que não roda aqui.

### Portões de qualidade

- **`public/course/format/ldg` entra em `.github/moodle-plugins.txt`.** Hoje o
  formato está fora da lista, então não passa pelo `moodle-plugin-ci` que bloqueia
  o deploy — a lista tem marketplace, gateways, enrol, availability, block,
  partners e `theme_ldg`, mas não ele.
- `moodle-plugin-ci phplint`, `phpcs --max-warnings 0` e `phpunit` nos dois
  plugins, **lendo o total do phpcs**, não as últimas linhas.
- `npx grunt` para o AMD e o stylelint. JS novo nasce em `amd/src` com o
  `amd/build` gerado — nunca editado à mão.
- `mustache lint` nos templates novos.

### Conferência visual no Chrome

Portão, não impressão — é o mesmo caminho que pegou os quatro bugs de 02/09.
Chrome headless pelo DevTools Protocol, medindo o DOM **depois** do JS rodar.

**Referência:** o `portal-aluno-version-bootstrip-responsive.html` renderizado no
mesmo Chrome, no mesmo viewport. Mesma fonte, mesmo motor, mesma escala — a
diferença que sobrar é do nosso código. Viewports: **1280 CSS** no desktop e
**390 CSS** no celular, que são os PNGs a 2×.

| Medida | Alvo |
|---|---|
| Altura do cabeçalho | 56 px |
| Coluna esquerda / direita | 280 px / 360 px |
| Quadro da aula | 16/9, estável entre ciclos do `ResizeObserver` |
| Virada das colunas | `lg` (992) à esquerda, `xl` (1200) à direita |
| Barra de baixo | presente abaixo de `lg`, ausente acima |
| Fundo, superfície e primário | tokens resolvidos batendo com `docs/brand/DESIGN.md` |
| Contraste dos pares de texto | AA, conferido nos dois modos (o cinza de rótulo do mockup **reprova** e sobe para `#8A8F94`) |

Cada uma é `getComputedStyle`/`getBoundingClientRect`: número, não impressão. Em
cima disso, screenshot lado a lado nos dois viewports e nos dois modos de cor,
para tipografia, respiro e hierarquia — e o **axe-core**, que precisa de
navegador e por isso mora aqui, e não no Behat.

**Dados de teste:** estender `cli/make_testdata.php` para montar um curso
parecido com o do mockup — aulas concluídas, uma em andamento, outras disponíveis
e um módulo bloqueado. Comparar tela cheia com tela vazia não prova nada.

**O script da sonda mora no scratchpad da sessão, nunca dentro do `public/`** —
arquivo de nome curto na raiz do Moodle sobrescreve arquivo do core, e isso já
aconteceu aqui. Versionado fica só o roteiro, em `docs/`.

Fora do CI de propósito: Chrome com diff de imagem em runner é caro e quebradiço,
e o valor está na conferência antes do PR.

### Documentação

- `format_ldg/README.md` — o portal, os quatro destinos, a regra de classificação
  e a dependência **opcional** do tema, com a promessa corrigida: com outro tema o
  layout continua de pé, só sem a marca.
- `theme_ldg/README.md` — o layout `ldgportal`, os tokens novos, as fontes
  embarcadas.
- `docs/dev/` — documento do portal, ligado pelo `docs/README.md`.
- Strings em `en`, `pt_br` e `es`, em ordem alfabética (inserir por âncora quebra
  o phpcs).
- `version.php` dos dois plugins, e nota de upgrade se mudar comportamento de
  curso existente.

---

## Descobertas

Levantadas ao desenhar, e caras de redescobrir:

- **O Moodle 5.2 traz Bootstrap 5.3.3** — exatamente a versão do mockup. Tudo que
  ele usa (`nav-pills`, `accordion`, `offcanvas`, `modal`, `progress`, `card`) já
  está compilado no tema. Nada de CDN.
- **`before_http_headers` roda antes da escolha do layout.**
  `core_renderer::header()` despacha o hook na linha 836 e só resolve
  `layout_file()` ~28 linhas depois, com a página ainda em `STATE_BEFORE_HEADER`.
- **Layout desconhecido não explode:** `theme_config::layout_info_for_page()` cai
  no `standard` com um `debugging()`. Por isso o guard — trocar o layout só se o
  tema declarar `ldgportal` — para não sujar o log de quem usa outro tema.
- **O `format_ldg` está fora do `moodle-plugin-ci`.** Não estava na lista de
  plugins validados.
- **`customcert`, `forum`, `resource`, `folder` e `url` estão instalados**, então
  os quatro destinos apontam para coisas reais, sem plugin novo.

---

## Em aberto

- **`mod_video`.** O player de verdade depende dele. Enquanto não existir, aula em
  vídeo é `mod_url` e cai em Materiais.
- **Domínio próprio por empresa** (Fase 3) não muda nada aqui, mas o cabeçalho do
  portal é o lugar natural da marca do vendedor quando chegar.
- **Fórum com mais de um fórum no curso:** a lista intermediária é o mínimo
  aceitável; se virar caso comum, vale destino por fórum.
- **Materiais por aula**, e não só por módulo, ficou de fora — o mockup não pede,
  e o `cm` já carrega os anexos dele.
