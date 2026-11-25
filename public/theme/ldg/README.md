# theme_ldg

Tema global da plataforma. É **filho do `theme_moove`**, e o Moove é plugin de
terceiro versionado no repositório.

Além da identidade visual, ele carrega duas funções: o **menu lateral de
navegação** recolhível em trilho de ícones, e a decisão de mostrar a **landing de
parceria** como home para visitante anônimo.

## Dependências

| Depende de | Versão | Por quê |
|---|---|---|
| `theme_moove` | 2026042100+ | é o pai; sem ele o filho não compila |
| `theme_boost` | 2026042000+ | avô, e precisa estar em `$THEME->parents` |

**Não declara `local_partners`**, de propósito. A landing é dependência
*opcional*, resolvida por `class_exists()` — sem o plugin, `/` cai no marketing
do próprio Moove e o site não fica sem home. É o que permite o `moodle-plugin-ci`
instalar o tema sozinho.

> **Armadilha de CI:** o `theme_moove` vive em
> `.github/moodle-plugins-thirdparty.txt`, que **não** entra no `--extra-plugins`
> do workflow. Acrescentar este tema à lista sem levar o pai junto instala um
> filho órfão, e a falha é dura.

## O que precisa configurar

`/admin/settings.php?section=themesettingldg`

| Aba | Settings |
|---|---|
| Marca | `logo`, `logodark`, `favicon`, `loginbg`, `brandcolor`, `secondarymenucolor`, `fontsite` |
| Cor | `defaultcolormode`, `enablecolormodetoggle` |
| Avançado | `preset`, `presetfiles`, `scsspre`, `scss`, `googleanalytics` |

**Depois de trocar qualquer asset, purgue os caches.** O SCSS e as URLs de
arquivo carregam `theme_get_revision()`; sem purgar, a logo antiga continua sendo
servida e parece que o upload não funcionou.

Para a landing virar a home, ver o README do `local_partners` — são duas
configurações, uma em cada plugin.

## O que NÃO fazer aqui

**Não edite `public/theme/moove/`.** É código de upstream. Toda customização
entra neste diretório; o dia em que o Moove for atualizado, o `git status` do
diretório dele tem que estar limpo.

**Não crie `scss/moove/`.** Os import paths do compilador são
`[ldg/scss, moove/scss, boost/scss]`. Um arquivo aqui com o nome de um parcial do
pai — `_variables.scss`, por exemplo — sombrearia o dele sem aviso nenhum, e o
`@import "moove/variables"` de dentro do `default.scss` do Moove passaria a
resolver no filho.

**Não remova `boost` de `$THEME->parents`.** A lista é plana: o `theme_config`
não expande os pais dos pais. Com `['moove']` apenas, o `mustache_template_finder`
e o `layout_file()` param de enxergar `theme/boost`, e todo override que o Boost
faz de template `core/*` some — sem erro, servindo o template do core.

**Não remova `$THEME->rendererfactory`.** Sem
`theme_overridden_renderer_factory` a classe `\theme_ldg\output\core_renderer`
nunca é instanciada. Também aqui não há erro: o site fica no ar lendo os
settings do **Moove**, que nunca configuramos. O sintoma é "sumiu o logo".

**Não escreva grade do portal em `_format.scss`.** A estrutura do miolo do portal
— colunas, breakpoints, sticky — mora em `course/format/ldg/styles.css`, para o
formato funcionar com qualquer tema. O CSS do tema entra **depois** do
`styles.css` do plugin no arquivo servido (medido: plugin em ~207k, tema em
~1,19M), então layout escrito aqui vence o de lá **em silêncio**. Foi o que
aconteceu em 03/09/2026: este arquivo tinha um grid de duas colunas antigo, e a
grade de três colunas do formato nunca chegou a valer na tela.

A exceção é o **chrome**: cabeçalho, menu de usuário e botão de fechar são
desenhados pelo layout `ldgportal`, que é deste tema — a estrutura deles é daqui
mesmo.

## O layout `ldgportal`

A página do curso em `format_ldg` é servida por um layout **sem navbar e sem
drawers**: o chrome inteiro é do portal. Quem **pede** a troca é
`\format_ldg\hook_callbacks`, no hook `before_http_headers`; quem **desenha** é
`layout/ldgportal.php` com `templates/ldgportal.mustache`.

O nome `ldgportal` é o **contrato** entre os dois plugins — o formato só troca o
layout se encontrar essa chave em `$THEME->layouts`. Renomear aqui desliga o
portal em silêncio, e é assim que o formato continua instalável com outro tema.

O menu de usuário do core vai junto de propósito: tirar a navbar não pode tirar
perfil, notificações e **sair**, deixando o aluno preso no curso. Pelo mesmo
motivo o `output.edit_switch` está no cabeçalho — sem ele o professor não tem
como ligar a edição, e foi o Behat que descobriu.

## O portal para quem gerencia

O layout `ldgportal` não tem navbar — e a navegação do curso mora nela. Sem
tratamento, o professor tinha que **ligar a edição** para chegar em Notas ou
Participantes, o que ninguém espera.

O layout carrega a navegação secundária **do próprio core**, e só para quem tem
`moodle/course:update`. A trava de capacidade não é detalhe: a navegação
secundária do core **não é exclusiva de professor** — o aluno tem uma, com Curso
e Notas —, e sem a condição ela volta para ele, reintroduzindo no portal
exatamente o chrome que o portal existe para tirar. Dois cenários de Behat fixam
os dois lados, porque só o primeiro passaria com a trava errada.

> A classe do `<body>` no portal é **`ldg-portal-page`**, e não `ldg-portal`. O
> formato usa `.ldg-portal` na div raiz dele, e as duas iguais casavam com a mesma
> regra — o recuo lateral era aplicado duas vezes, 48px contra os 24 do desenho.

## Dois consertos de layout do core, e por que eles existem

Ambos em `_corefixes.scss`, medidos no navegador e não deduzidos do SCSS.

**O cabeçalho das páginas de administração encolhia até o conteúdo.** Em
`/admin/*`, título e busca apareciam espremidos no meio da tela: o
`<header class="header-maxwidth">` tinha **370px** de largura com 365px de margem
de cada lado. A causa não é o `max-width`, que é onde se olha primeiro — é o
`margin: 0 auto` que o Boost põe em `.header-maxwidth`, combinado com o
`#page-wrapper #page { display: flex; flex-direction: column }`, também dele:
margem automática no **eixo cruzado** de um flex cancela o `stretch` e encolhe o
item. Quem respondeu isso foi o `CSS.getMatchedStylesForNode` do Chrome, que
mostrou ser a única regra a casar.

**A navegação secundária vira faixa, antes do título.** É a ordem do tema pai. O
recuo do `#page.drawers` é **assimétrico** — 16px à esquerda, 48px à direita —,
e cancelar 48 dos dois lados joga a faixa **por baixo do trilho de ícones**. Ela
não passa por baixo dele de propósito: o trilho é coluna fixa, e "ponta a ponta"
aqui é a área de conteúdo. O tema pai não tem trilho, e é por isso que a barra
dele vai até a borda da janela.

## Fontes empacotadas

`fonts/` traz **Inter** e **JetBrains Mono**, em subconjuntos variáveis (peso 400
a 700 num arquivo só), `latin` e `latin-ext` — 176 KB no total. Declaradas em
`scss/ldg/_fonts.scss` com `[[font:theme|...]]`, que o `theme_config` reescreve
para uma URL do próprio site.

**Não troque por Google Fonts.** A tipografia de uma página que o aluno abre todo
dia passaria a depender de um terceiro estar no ar, e na VPS seria uma requisição
externa em cada carga.

O `latin-ext` não é opcional: é ele que traz `ã ç õ é ñ`. Sem ele o português e o
espanhol caem na fonte do sistema **no meio da frase**.

A licença é OFL 1.1, que permite empacotar e **exige** o aviso junto — está em
`fonts/OFL.txt`.

## Contraste: os pares medidos

Todo par de cor do tema foi calculado, não estimado. Os que exigiram valor
diferente do design system:

| Papel | Valor | Contraste | Por quê |
|---|---|---|---|
| `--ldg-text-label` (escuro) | `#8a8f94` | 5,11:1 | o `#6C7075` do desenho dá **3,34:1** e reprova em AA, e é onde ele põe rótulo de 12px |
| `--ldg-text-label` (claro) | `#5c6a80` | 5,49:1 | o primeiro palpite (`#6b7a90`) dava 4,36:1 |
| `--ldg-accent-fill` (claro) | `#0062cc` | 5,80:1 com branco | branco sobre `#007aff` dá **4,02:1** |
| `--ldg-accent` (escuro) | `#4b8eff` | 5,90:1 | o do design system, e vale para o site inteiro no escuro |

Cor nova entra medida, nos **dois** modos. `#30D158` sobre branco dá 1,8:1 — no
modo claro o sucesso é `#1a7f37`.

## Os dez overrides do renderer, e por que existem

> Esta seção já descreveu "os seis métodos em que o Moove faz
> `theme_config::load('moove')` fixo". **Isso deixou de valer** quando o tema
> saiu da herança dele: o pai passou a ser o `theme_boost`, que é core, e não há
> mais tema de terceiro para ler configuração errada. Hoje cada override existe
> por conta própria — e são dez, não seis.

`classes/output/core_renderer.php` sobrescreve, em três grupos:

**Marca** — o Boost não tem esses settings, então não há o que herdar.

| Método | Sem ele |
|---|---|
| `get_theme_logo_url()` | sem marca: não há logo configurada nem embarcada |
| `get_theme_logo_dark_url()` | idem, no modo escuro |
| `favicon()` | o favicon do Moodle |
| `should_display_logo()` | o `navbar.mustache` cai no ramo de "não há logo" |
| `should_display_theme_logo()` | idem |
| `get_logo()` | idem |
| `get_logo_dark()` | idem |

**Os quatro últimos andam juntos.** Remover um só já derruba o ramo do template:
a navbar passa a imprimir o **nome do site em texto**, e o sintoma parece "a logo
sumiu" — ninguém procura no renderer.

**Modo de cor** — o Boost decide por preferência de usuário, e visitante anônimo
não tem preferência.

| Método | Sem ele |
|---|---|
| `body_attributes()` | a landing e o login sairiam claros para todo o público de captação, que é quem o design escuro atende. Também é aqui que entra a classe `ldg-embedded` |
| `render_darkmode_controls()` | o Boost não tem esse botão; ele existe por conta própria |

**Cabeçalho**

| Método | Sem ele |
|---|---|
| `standard_head_html()` | sem Google Analytics e sem a fonte do design system |

**`standard_head_html()` chama o do Boost antes de acrescentar.** Trocar isso por
um `return` próprio faria o core perder o que ele põe no `<head>`, e em silêncio.
A chamada é explícita (`\theme_boost\output\core_renderer::...`) em vez de
`parent::` por herança da época em que era preciso **pular** um degrau; hoje as
duas formas seriam equivalentes.

`classes/util/settings.php` existe pela razão análoga: a classe equivalente do
tema de origem carregava `'moove'` fixo no construtor.

## Escuro é o padrão, claro é suportado

O design system da marca é dark-first, mas os **dois** modos são de primeira
classe. No claro, o azul escuro predomina — não é o tema claro do Moove com outra
cor de destaque.

O modo é resolvido em três estados por `\theme_ldg\util\settings::color_mode()`:

```
preferência do usuário (dark-mode-on)   ← se existir, vence
        ↓ ausente
theme_ldg/defaultcolormode              ← escuro por padrão
```

O terceiro estado é o que importa: **visitante anônimo não tem user preference**.
A landing de captação e a tela de login são as duas páginas anônimas, e sem esse
degrau elas sairiam claras para todo o público-alvo. Com ele, saem no padrão
configurado.

| Setting | Padrão | O que faz |
|---|---|---|
| `defaultcolormode` | `dark` | modo de quem ainda não escolheu |
| `enablecolormodetoggle` | ligado | mostra o botão de alternar na navbar |

**`enablecolormodetoggle` é `admin_setting_configcheckbox`, e não
`configselect`.** Com um select cuja primeira opção era "Não", salvar qualquer
outra coisa na mesma página gravava `0` junto — o `config_log` mostrou o toggle
indo de `[1]` para `[0]` no mesmo POST do upload de logo, e o sintoma foi "sumiu
a opção de modo escuro".

Custo aceito: a área de edição do TinyMCE (`$THEME->editor_scss`) continua clara.
É tela de autoria, não pública.

### O modo claro precisou de `_corefixes.scss`

Vinte e dois seletores do core do Moodle chegam com `$body-bg` embutido no CSS
compilado, o que deixava telas inteiras ilegíveis no escuro — a de gestão de
cursos foi a primeira a aparecer. Ver a seção sobre esse arquivo abaixo.

## `loginbg`, e não `loginbgimg`

O setting da imagem de fundo do login tem nome diferente do Moove **de
propósito**. Os callbacks de SCSS do pai rodam junto com os do filho, recebendo a
config do filho: se o nome fosse igual, `theme_moove_get_extra_scss` emitiria o
CSS do fundo e o SCSS do admin, e o nosso callback emitiria tudo de novo.

Com o nome diferente, o do Moove sai no `return ''` da linha 77 do `lib.php`
dele, e o nosso faz o trabalho inteiro uma vez só — de quebra contornando o bug
em que aquele `return` descarta o setting `scss` do administrador.

**Regra geral:** toda vez que este tema declarar um setting com nome igual a um
do Moove, pergunte "o callback dele vai reagir a isso?".

## O menu lateral e o course index dividem um drawer só

O Boost já usa o drawer esquerdo para o **course index**, e só dentro de curso.
O menu de navegação da plataforma foi para o **mesmo** drawer, empilhado acima
dele, em vez de ganhar um drawer próprio. Um botão, uma preferência, e toda a
mecânica de foco, teclado e fechar-no-resize continua sendo a do Boost.

O course index é renderizado **dentro** do `templates/ldg/navmenu.mustache`, e
não como irmão dele. Se fosse irmão, o rodapé do menu — avatar, preferências e
sair — cairia no meio da coluna, antes das seções do curso.

Peças envolvidas:

| Arquivo | Papel |
|---|---|
| `classes/output/navmenu.php` | monta os grupos a partir da configuração do site |
| `templates/ldg/navmenu.mustache` | o menu, com o course index no meio |
| `layout/drawers.php` | substitui o do Boost; o drawer esquerdo deixa de exigir curso |
| `templates/drawers.mustache` | override: troca a condição do drawer |
| `amd/src/navmenu.js` | recolher/expandir, e grava a preferência |
| `scss/ldg/_navmenu.scss` | trilho no desktop, off-canvas no mobile |

**O conteúdo do menu não é escrito aqui.** Vem de `enablemyhome`,
`enabledashboard`, `enablemycourses` e `defaulthomepage` (Aparência →
Navegação), e de `customusermenuitems`, lido pela mesma
`user_get_user_navigation_info()` que alimenta o menu do usuário na navbar —
escrever um parser próprio criaria duas verdades para a mesma configuração.

**Dois comportamentos, fronteira no breakpoint `md`.** No desktop o drawer é
permanente e recolher o estreita para um trilho de ícones. No mobile não há
trilho: volta a ser o off-canvas do Boost, porque uma faixa fixa comeria largura
onde ela já falta.

Duas armadilhas que custaram tempo aqui:

- **O Boost esconde o drawer com `left: calc(-285px + -10px)`**, não com
  `transform`. Sobrescrever só o `transform` deixa a página deslocada e o menu
  invisível. O `max-width: 285px` também precisa ir junto, ou o trilho não
  estreita.
- **Não escreva chaves duplas de mustache dentro de um comentário `{{! }}`.**
  O comentário termina no primeiro `}}`, e o resto vira tag de verdade — foi
  assim que o `drawers.mustache` quebrou com `Missing closing tag`.

## Os seis overrides do Moove que NÃO foram trazidos

Ao sair da herança do Moove, seis templates dele ficaram para trás. Foram
avaliados um a um contra o **original do core**, e a decisão foi **não trazer
nenhum**. Fica registrado para a pergunta não voltar.

| Template | Por que ficou fora |
|---|---|
| `mod_quiz/timer` | usa `ml-auto`, `mr-2`, `ml-3` — Bootstrap **4**. Não estão no `bs4-compat.scss` do Boost, então o cronômetro perderia a margem automática e os espaçamentos. Ainda troca `btn-secondary` por `btn-light` (o botão cinza que já foi reclamado) e **apaga o rótulo** `timeleft` em favor de um ícone sozinho |
| `mod_quiz/list_of_attempts` | troca a `<ul>` semântica por `<div>`, perde a grade responsiva `row-cols-1 row-cols-md-2` do core e depende de `.moove-attempts-list`, que não existe mais |
| `mod_quiz/attempt_summary_information` | troca uma `<table>` com `<caption class="visually-hidden">` e `<th scope="row">` por `<div>` e `<h5>`. É dado tabular: isso quebra a associação rótulo/valor no leitor de tela |
| `core_enrol/enrol_page` | só acrescenta ganchos `enrol-card__*` que nenhum SCSS nosso consome, e **remove** `fs-6 fw-bold` do título. Sem o CSS do Moove, fica estritamente pior que o core |
| `core_enrol/enrolment_options` | remove o `id="notice"` e envolve a mensagem num `alert alert-secondary` — cinza outra vez |
| `core/full_header` | troca `header-maxwidth d-print-none` por `moove-container-fluid py-4`. Além da classe órfã que já causou a pior quebra visual da migração, perde o `d-print-none` (o cabeçalho passa a sair na impressão) e o `data-for="page-heading"`, que é gancho de JS e de Behat |

O padrão é o mesmo nos seis: são de uma era anterior do Bootstrap, trocam
marcação semântica por `div`, e dependem de CSS que saiu junto com o Moove. O
template do core, no 5.2, já é a versão melhor — e o que nos faltava era só a
**cor**, que já vem dos tokens: `.card` e `.btn` em
`scss/ldg/_components.scss`, `.table` em `scss/ldg/_surfaces.scss`.

**Uma lacuna real ficou:** `.alert` não é estilizado em lugar nenhum do tema.
Nas telas de inscrição o aviso do core sai no cinza do Bootstrap. Isso é um
argumento para escrever a regra de `.alert` — não para trazer o template do
Moove, que resolvia a mesma coisa quebrando outras cinco.

**Antes de reabrir qualquer um destes, compare com o core do momento, não com o
Moove.** O core andou; o Moove, para estes arquivos, não.

## `scss/ldg/_corefixes.scss` precisa ser revisado a cada upgrade do Moodle

O core e o Boost pintam fundo com a variável SCSS `$body-bg` em vários lugares.
`$body-bg` é resolvido em tempo de compilação e **não tem variante dark** — o
Bootstrap tem `$body-bg-dark` para a custom property, mas quem escreve
`background: $body-bg` direto congela o valor do modo claro. No modo escuro isso
vira painel claro com texto branco por cima.

Não dá para consertar na origem: trocar `$body-bg` por uma custom property
quebra a compilação, porque o `_root.scss` do Bootstrap chama `to-rgb($body-bg)`.

Então a correção é por seletor, com os mesmos seletores do core. Para refazer a
lista depois de um upgrade:

```bash
curl -sk "https://<site>/theme/styles.php/ldg/<rev>/all" -o /tmp/t.css
grep -o '[^{}]*{[^}]*background[^}]*#f4f6fa[^}]*}' /tmp/t.css
```

`#f4f6fa` é o `$body-bg` do modo claro definido em `theme_ldg_get_pre_scss`. Se
aparecer seletor que não está no `_corefixes.scss`, o core ganhou um lugar novo.

## Assets

`pix/` guarda cópias otimizadas; a fonte fica em `docs/brand/`. Trocar a arte lá
não muda o site até a cópia ser refeita **e os caches, purgados** — o
`setting_file_url()` embute o `theme_get_revision()` na URL do arquivo.

`pix/login_hero.jpg` é um recorte de `docs/brand/hero.png`. O recorte não é
estética: o arquivo original é um preview de banco de imagens com texto
chapado ("High Resolution / E-learning Dashboard tackground"), e só a faixa de
baixo está limpa.

## Testar

Depois de qualquer mudança de SCSS ou de arquivo de setting, **purgue os
caches** — o CSS é cacheado por `theme_get_revision()`.

Para iterar rápido, `$CFG->themedesignermode = true` no `config-local.php`.
**Só na máquina local**: na VPS isso recompila o SCSS inteiro a cada requisição.
