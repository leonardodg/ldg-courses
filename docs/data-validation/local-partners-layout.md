# Provar o layout da landing e do cadastro nos três temas

Roteiro para repetir, sem mim, a conferência que fecha a reforma visual do
`local_partners`. Ele responde uma pergunta só: **a página que o visitante vê
corresponde ao mockup, e continua correspondendo sob `theme_boost`, `theme_moove`
e `theme_ldg`?**

> Desconfie de "está bonito na minha tela". O estilo deste plugin vem de um
> `styles.css` que entra na CSS compilada de **todos** os temas, e uma única
> regra sem o escopo `.ldgp` funciona sob Boost e desaparece sob o `ldg` — sem
> erro, sem log, e só na tela de quem usa o outro tema.

O método é o de [`../dev/portal-conferencia-visual.md`](../dev/portal-conferencia-visual.md),
generalizado em [`../dev/padrao-de-implementacao.md`](../dev/padrao-de-implementacao.md):
medir o DOM depois do JavaScript, número antes de opinião, e o mockup renderizado
no mesmo Chrome em vez de comparado com PNG.

## O que medir, e os alvos

| Medida | Alvo | Onde vale |
|---|---|---|
| Rolagem horizontal | **nunca** | todas as larguras |
| Cards de plano | 1 coluna | < 768px |
| Cards de plano | 3 colunas, mesmo `top` | ≥ 992px |
| Pilares | 1 coluna | < 768px |
| Pilares | 4 colunas | ≥ 1200px |
| Barra de seções, ao rolar | grudada abaixo do cabeçalho | todas |
| Alvo de toque na barra | ≥ 44px de altura | < 768px |
| Cadastro: coluna de valor / formulário | empilhado | < 992px |
| Cadastro: coluna de valor / formulário | 5/7, coluna esquerda pegajosa | ≥ 992px |
| `data-bs-theme` no wrapper | `dark` sem preferência | os três temas |
| Honeypot `fax` | fora da tela | os três temas |
| Contraste texto médio / fundo | ≥ 4,5:1 | os dois modos |

As larguras são **360, 390, 768, 1024 e 1440**.

## Preparar

Os planos da landing vêm do banco, não do template — sem plano público, a seção
de preços não aparece e a medição não tem o que medir.

```bash
# Confere que existem planos publicos; o seed do marketplace cria tres.
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  php public/local/marketplace/cli/status.php < /dev/null
```

```bash
# A landing precisa estar ligada. Os scripts de CLI ficam na RAIZ, em
# admin/cli/, e nao em public/admin/cli/ - no layout public/ do Moodle 5.x eles
# vivem de proposito fora do webroot.
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  php admin/cli/cfg.php --component=local_partners --name=enablelanding --set=1
```

**Editou o `styles.css`? Suba o `version.php` do plugin.** O `purge_caches` não
invalida CSS de plugin — foi medido em 03/09/2026, a revisão ficou parada antes e
depois do purge. Medir com CSS velho produz um relatório inteiro de conclusões
falsas.

O Chrome sobe **destacado**, senão morre junto com o comando que o lançou:

```bash
google-chrome --headless=new --disable-gpu --no-sandbox \
  --ignore-certificate-errors --remote-debugging-port=9222 \
  --user-data-dir=<scratchpad>/chrome-profile about:blank
```

## O roteiro

1. **Trocar o tema do site** para `boost` e carregar `/local/partners/index.php`
   como visitante anônimo. Repetir a tabela de medidas nas cinco larguras.
2. **Repetir sob `moove`**, e depois sob `ldg`. É o mesmo comando com o tema
   trocado — e é a única prova de que o estilo saiu do tema para o plugin.
3. **Alternar o modo de cor** pela barra de seções, medir de novo no claro, e
   **recarregar** para provar que a escolha ficou. Anônimo persiste em
   `localStorage`; logado, na preferência `dark-mode-on`.
4. **Rolar até o FAQ** e medir o `top` da barra de seções contra a altura do
   cabeçalho fixo. É o que pega `position: sticky` morto por um ancestral com
   `overflow: hidden` — falha silenciosa, sem erro e sem log.
5. **Abrir o cadastro** (`/local/partners/apply.php`) e repetir 1 a 3, mais a
   medida do `left` da caixa do honeypot.
6. **Renderizar o mockup no mesmo Chrome e no mesmo viewport** e capturar lado a
   lado. Os arquivos de referência são `Image 6.html` (landing, celular),
   `Image 2.html` (cadastro, desktop), e os PNGs `Image 3` e `Image 5`.
7. **Calcular o contraste** de cada par de cores da paleta clara, que foi
   derivada e não desenhada. O número vai como comentário no `styles.css`.

Os cenários `@javascript` cobrem os itens 3, 4 e as duas primeiras linhas da
tabela; este roteiro existe para o que sobra — a comparação com o mockup e o
julgamento de tipografia, respiro e hierarquia, que número não decide.

```bash
moodev up --full
# 0.0.0.0, e nao 127.0.0.1: o Chrome roda em OUTRO container.
docker exec -d -u 1000:33 ldg-courses-moodle-1 \
  sh -c 'cd /var/www/html/public && php -S 0.0.0.0:8000 >/tmp/behatweb.log 2>&1'
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml \
  --profile=chrome --tags "@local_partners&&@javascript"
```

Nove cenários, e o último é um `Scenario Outline` que repete a conferência sob
`boost`, `moove` e `ldg`.

## Resultado — 10/09/2026

Chrome 152 headless, medindo o DOM depois do JavaScript, com o mockup renderizado
no mesmo navegador e no mesmo viewport.

### Os três temas dão o mesmo número

Landing em 1440, com o tema do site trocado entre uma medição e a seguinte:

| Tema | Pilares | Planos | Fundo | Fonte | Rolagem horizontal |
|---|---|---|---|---|---|
| `boost` | 4 col, 272px | 3 col, 365px | `rgb(18,18,18)` | Inter | não |
| `moove` | 4 col, 272px | 3 col, 365px | `rgb(18,18,18)` | Inter | não |
| `ldg` | 4 col, 272px | 3 col, 365px | `rgb(18,18,18)` | Inter | não |

Idênticos. É a prova de que o estilo saiu do tema — antes desta reforma, as duas
primeiras linhas sairiam sem estilo nenhum.

### Landing contra o mockup

`Image 6.html` é a variante **de celular** do desenho: não tem uma classe `lg:`
sequer, e por isso continua em uma coluna em qualquer largura. Ele só vale como
referência **até 767px**; de 768 para cima a referência é o `Image 3.png`, que
mostra 4 pilares e 3 planos lado a lado.

| Largura | Fonte | Fundo | h1 | Peso do h1 | Planos | Rolagem |
|---|---|---|---|---|---|---|
| 360 nosso | Inter | `rgb(18,18,18)` | 32px | 800 | 1 coluna | não |
| 360 mockup | Inter | `rgb(18,18,18)` | 30px | 800 | 1 coluna | não |
| 390 nosso | Inter | `rgb(18,18,18)` | 33px | 800 | 1 coluna | não |
| 390 mockup | Inter | `rgb(18,18,18)` | 30px | 800 | 1 coluna | não |
| 768 nosso | Inter | `rgb(18,18,18)` | 47px | 800 | 2 colunas | não |
| 1024 nosso | Inter | `rgb(18,18,18)` | 56px | 800 | 3 colunas | não |
| 1440 nosso | Inter | `rgb(18,18,18)` | 60px | 800 | 3 colunas | não |

Fonte, fundo e peso batem exatamente. O h1 fica 2–3px acima do mockup no celular
porque o nosso é fluido (`clamp`) e o do desenho é fixo em `text-3xl`; a diferença
some a olho e evita o degrau de tamanho que um valor fixo cria entre 767 e 768px.

### Cadastro contra o mockup

`Image 2.html` é a variante **de desktop**, com `lg:col-span-5` e `lg:col-span-7`.

| Largura | Coluna de valor | Formulário | Proporção da esquerda | Empilhado |
|---|---|---|---|---|
| 390 nosso | 343px | 343px | 0,50 | sim |
| 390 mockup | 343px | 343px | 0,50 | sim |
| 1024 nosso | 374px | 523px | 0,42 | não |
| 1024 mockup | 366px | 531px | 0,41 | não |
| 1440 nosso | 487px | 681px | 0,42 | não |
| 1440 mockup | 479px | 689px | 0,41 | não |

A proporção bate. As larguras absolutas ficavam 26px estreitas até a medição
mostrar que o contêiner do desenho é `max-w-7xl`, ou seja **1280px**, e não os
1200 que eu havia escolhido — corrigido, e a diferença caiu para 8px, que é a
sobra da aritmética entre um grid de `5fr 7fr` e um de 12 colunas.

### Contrastes calculados

| Modo | Par | Medido | AA |
|---|---|---|---|
| escuro | texto branco sobre card | 16,67:1 | passa |
| escuro | texto médio `#b0b3b8` | 7,93:1 | passa |
| escuro | texto baixo `#868b93` | 4,86:1 | passa |
| escuro | link `#3d97ff` | 5,61:1 | passa |
| claro | texto `#101214` | 18,77:1 | passa |
| claro | médio `#4a5057` | 8,15:1 | passa |
| claro | link `#0062cc` | 5,80:1 | passa |
| ambos | branco sobre `#0062cc` | 5,80:1 | passa |

Três cores do mockup **reprovaram** e foram trocadas: `#71767b` dava 3,64:1,
`#007aff` como texto dava 4,15:1, e branco sobre `#007aff` dá 4,02:1 — que só
passa em texto grande, e o botão do desenho é de 14px.

## O que a medição encontrou

Sete defeitos, e nenhum quebrou um teste de servidor:

**O docblock do template virou parágrafo na página pública.** Um comentário de
mustache termina no **primeiro** `}}`, e o docblock citava uma tag para explicar
de onde vinham os `id=` das seções. Todo o texto seguinte, incluindo o *Example
context*, saiu na tela do visitante.

**O miolo com 720px dentro de um viewport de 1440.** Não é o `limitedwidth` do
body: é o `.pagelayout-standard` do Boost.

**Sobravam 70px de fundo do tema em volta.** O padding estava no
`#page.drawers div[role="main"]`, encontrado percorrendo a cadeia de ancestrais.

**O texto do botão primário saía azul.** `.ldgp a` (0,1,1) vencia
`.ldgp-btn--primary` (0,1,0).

**Botão com 110px de altura**, porque o SVG sem tamanho declarado assume a caixa
do `viewBox`.

**Oito campos numa coluna só.** Sem `addElement('header', …)` o Moodle não
envolve os campos em fieldset, e os `.fitem` são filhos diretos do `<form>`.

**A coluna com 644px num viewport de 390.** `1fr` é `minmax(auto, 1fr)` e não
encolhe abaixo do conteúdo; um input com `size="50"` tem largura intrínseca
grande.

E um oitavo que o `stylelint` pegou antes do navegador: `clamp()` com soma
precisa de `calc()` dentro — os quatro títulos fluidos estavam inválidos.

## Resultado — segunda rodada, 10/09/2026

A rodada saiu de uma conferência tela a tela do dono do projeto. Sete ajustes, e
os quatro primeiros eram **defeito**, não preferência:

**A página inicial mostrava tudo em duplicata.** A raiz do domínio serve a mesma
landing de `/local/partners/index.php`, mas o layout de frontpage do tema montava
a navbar e o rodapé **do tema** por cima — e a landing já traz os próprios. O
visitante via duas barras e dois rodapés, um dentro do outro, e a página inteira
espremida no container de leitura do Boost, com faixa de fundo dos dois lados.
Medido depois: **1 barra, 1 rodapé, 1425px de largura em 1440**, sem rolagem
lateral. Cenário behat novo segura os dois lados.

**A marca sumia na página de cadastro.** `landing_page::brand()` recebe o
renderer da página, e o cadastro renderiza pelo renderer **do plugin**, que não
tem `get_logo`. A mesma chamada devolvia imagem na landing e palavra no cadastro.
Agora o renderer recebido é apenas o primeiro candidato: o `$OUTPUT` do tema vem
atrás.

**O "Apply" aparecia duas vezes na barra**, como âncora no meio das seções e como
botão azul na ponta, a dois centímetros um do outro.

**A tela de entrar tinha metade branca.** O `#page` do layout de login sai `#fff`
do Boost, e o branco vencia tudo dentro dele: painel escuro de um lado, retângulo
branco com um cartão escuro no outro. A regra que existia pintava a *coluna*, que
é transparente por desenho — o branco estava um degrau acima.

E três decisões de desenho:

**Entrar vira SAIR para quem já entrou.** O botão simplesmente sumia, e a barra
ficava sem nenhuma saída para a sessão aberta. O endereço leva `sesskey` — o
`logout.php` recusa sem ela, e com razão.

**O rodapé virou três colunas com a primeira valendo por duas**, com a logo no
lugar do nome em negrito, e a última linha com dois itens: razão social com CNPJ
à esquerda, `© 2026 · Built by LeoDG` à direita. Saiu o `You are not logged in.
(Log in)`, que empurrava os outros dois para fora do lugar. O bloco de depuração
— `Purge all caches`, contagem de instâncias reativas — passou para **antes** da
linha legal: ele só existe para quem tem debug ligado, e estava fechando o
rodapé.

**Rodapé e barra de controles do cadastro ficam escuros nos dois modos.** São
superfícies de moldura, como a navbar. Medido pela luminância, e não por
hexadecimal fixo, para o teste sobreviver a ajuste de paleta.

### Números da segunda rodada

| Superfície | 1440 | 390 |
|---|---|---|
| Home (= landing) | 1 barra, 1 rodapé, 0 rolagem lateral | idem, barra de 101px |
| Landing | barra em `t=1`, marca + 4 âncoras + 2 botões | 2 linhas |
| Cadastro | rodapé simples de 93px, 3 itens na linha | 143px |
| Entrar / criar conta | sem navbar, sem rodapé | idem |
| Rodapé do tema | 3 colunas: 559 / 267 / 267 | empilha |
| Rodapé da landing | 3 colunas: 584 / 268 / 268 | empilha |

A barra do celular saiu de **137px para 101px**: em 390 a marca ocupa 73px e o
bloco de ações 274, num miolo útil de 358 — faltavam **5px** para as duas
caberem lado a lado. Tirar o globo do seletor de idioma devolve 22px, e a sigla
sozinha já diz o que ele faz. Abaixo de 390 ela volta a três linhas, sem estouro.

### O que a segunda rodada rodou

- **68 testes** no `local_partners`, **15** no `theme_ldg`, **129** no núcleo
- **25 cenários behat** de captação e **5** do tema, todos com `--profile=chrome`
- `phpcs --standard=moodle` limpo nos dois plugins, lido por inteiro
- `stylelint` limpo no `styles.css` e nos parciais SCSS tocados
- medição e captura em 1440 e 390, claro e escuro, anônimo e autenticado

## O que continua sem prova

**Tipografia, respiro e hierarquia.** Número resolve "3 colunas de 365px";
"chegou perto do desenho" é julgamento humano, com as capturas lado a lado.

**A paleta clara contra uma referência.** Ela foi derivada e medida, mas não
existe mockup claro para comparar.

**Navegador que não seja Chromium.** Toda a medição saiu do Chrome.

## Armadilhas

**`purge_caches` não invalida CSS de plugin.** Suba o `version.php`. Sem isso, a
medição descreve o estilo anterior — aconteceu três vezes nesta rodada, sempre
com a mesma cara: a correção "não funcionou".

**A primeira medição depois de um purge mede a página errada.** O primeiro
acesso recompila a CSS de todos os temas, e a sonda encontra a página a meio
caminho. Descarte a primeira leitura, ou espere mais.

**Revisão de CSS em cache engana entre temas.** Pedir
`styles.php/boost/<revisão do ldg>/all` devolve um artefato antigo, e a conclusão
foi "a regra não chegou ao Boost" quando ela tinha chegado. Use `-1` para forçar
a compilação.

**O `.d-flex` do Bootstrap é `display: flex !important`.** Uma grade declarada
por cima é ignorada, e o `getComputedStyle` mostra as duas coisas ao mesmo
tempo — `grid-template-columns` definido e `display: flex`. Ou se trabalha com o
flex, ou se usa `!important`.

**O Moodle ESCALA o viewport ao redimensionar.** `I change viewport size to
"mobile"` sem `without runtime scaling` faz os 425px se comportarem como uns
600. Uma asserção sobre celular medida assim afirma outra coisa.

**O `grunt` não roda dentro do container.** O node de lá é v20 e o Moodle 5.2
exige v22. Rode no host, de dentro do diretório do plugin.

**Com `cachejs` ligado o Moodle serve o AMD compilado.** Sem `amd/build/`, o
módulo simplesmente não roda — botão que não faz nada, sem erro no console.
`npx grunt amd` gera, e os arquivos vão versionados.

**Sem sessão, a sonda mede a tela de login** e conclui que está tudo bem.

**`evaluate_script` avalia expressão, não bloco.** Envolva numa IIFE, senão o
Chrome devolve `Unexpected token 'const'` longe da causa.

**Feature nova não é coletada sozinha.** Depois de criar um `.feature`, rode
`php public/admin/tool/behat/cli/util.php --enable`, senão o behat responde
`No scenarios` e parece que o arquivo está errado.

**O primeiro `Runtime.evaluate` depois de um `Page.navigate` pode cair no
contexto velho do `about:blank`.** `document.body` volta nulo e o erro parece da
página. A sonda tenta de novo, e a medição só vale quando o seletor âncora já
existe — por isso ela começa lançando se não achar.

**A revisão da CSS muda a cada `purge_caches`, e a aba pode reusar a anterior.**
Duas correções desta rodada foram dadas como "não pegou" por isso, e as duas
estavam certas: o `#page` branco da tela de entrar e a barra do cadastro no
celular. Antes de concluir que uma regra falhou, confira se ela está no artefato
que a página realmente carregou:

```bash
url=$(curl -sk https://localhost:8443/login/index.php | grep -oE 'styles.php/[^"]*' | head -1)
curl -sk "https://localhost:8443/theme/$url" | grep -o 'SEU_SELETOR{[^}]*}'
```

**O site de teste do behat nasce sem Dashboard.** Com `enablemyhome` vazio, o
`public/index.php` do CORE manda todo visitante anônimo para a tela de entrar
**antes** de escolher qualquer layout. Um cenário sobre a página inicial pública
precisa de `enablemyhome`, `forcelogin` e `theme` na mesa — sem eles ele mede o
formulário de login e falha dizendo que o elemento não existe.
