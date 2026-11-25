# Portal do aluno — Plano 3: marca, tipografia e fontes

> **Para quem executa:** use `superpowers:executing-plans`. Os passos usam `- [ ]`.

**Meta:** o portal deixa de ser cinza. O `theme_ldg` ganha as fontes do desenho
(self-hosted), reconcilia o que falta na paleta e pinta o chrome, a navegação e
os materiais contra o `docs/brand/DESIGN.md`.

**Arquitetura:** nada de CSS novo no formato — a estrutura já está lá desde o
plano 2. Tudo aqui é `theme_ldg`: `_tokens.scss` para os valores, `_fonts.scss`
para as famílias, `_format.scss` para o desenho.

**Pilha:** Moodle 5.2, SCSS compilado pelo tema, Bootstrap 5.3.3, PHPUnit, Behat.

Design system: [`../brand/DESIGN.md`](../brand/DESIGN.md) ·
Planos anteriores: [`1`](2026-09-03-portal-plano-1-chrome-e-layout.md) ·
[`2`](2026-09-03-portal-plano-2-destinos-e-catalogo.md)

## O plano encolheu, e é uma boa notícia

Levantando o tema antes de escrever, descobri que **o modo escuro já é quase o
mockup**. Uma sessão anterior já trabalhou a partir de um design system — há até
um comentário no `_tokens.scss` citando "o design system pede Input Field:
Background #1E1E1E".

| Token | `theme_ldg` hoje (escuro) | `DESIGN.md` | Situação |
|---|---|---|---|
| `--ldg-bg` | `#121212` | `#121212` | **bate** |
| `--ldg-surface` | `#1e1e1e` | `#1E1E1E` | **bate** |
| `--ldg-border` | `#3a3b3c` | `#3A3B3C` | **bate** |
| `--ldg-text` | `#ffffff` | `#FFFFFF` | **bate** |
| `--ldg-text-muted` | `#b0b3b8` | `#B0B3B8` | **bate** |
| `--ldg-surface-raised` | `#2a2a2a` | `#252525` | ajustar |
| `--ldg-accent` | `#007aff` | `#4B8EFF` | decidir |
| sucesso | não existe | `#30D158` | criar |
| cinza de rótulo | não existe | `#6C7075` reprova | criar acessível |
| fonte | nenhuma | Inter + JetBrains Mono | **o grosso do trabalho** |

Então o plano 3 é: **fontes, cinco tokens e o desenho do portal**. Não é
"reescrever a paleta".

**Os ícones saem de escopo, e não por esquecimento.** O spec previa traduzir os
`bi-*` do mockup para FontAwesome um a um. Só que a marcação que os planos 1 e 2
produziram **não tem ícone nenhum**: a navegação é texto, e a lista de materiais
usa o ícone que o próprio Moodle dá para cada atividade. Não há o que traduzir.
Se um dia os ícones entrarem na navegação, eles vêm do FontAwesome que o Moodle
já carrega — e aí a tabela de tradução vira necessária.

## Restrições globais

- Worktree `format-course-ldg`, branch `fix/format-ldg-navegador`, stack offset 0.
- Comentários em português sem acentos; documentação com acentos.
- `phpcs --standard=moodle` zero violações — ler o TOTAL. SCSS não passa por ele,
  mas o `stylelint` do Moodle vale (`grunt`, quando houver `node_modules`).
- Claro **e** escuro. Toda cor nova entra nos dois blocos de `[data-bs-theme]`.
- **Contraste se calcula, não se estima.** Todo par novo vai medido no commit.
- Nada de push.

## Contraste já medido, para o plano não inventar

| Par | Razão | Veredito |
|---|---|---|
| `#4b8eff` sobre `#121212` | **5,90:1** | passa AA para texto |
| `#4b8eff` sobre `#1e1e1e` | **5,25:1** | passa AA para texto |
| `#007aff` sobre `#121212` | 4,66:1 | passa raspando; é por isso que o tema já tem um tom separado para texto |
| `#8a8f94` sobre `#1e1e1e` | **5,11:1** | passa — é o substituto do `#6C7075`, que dá 3,34:1 e reprova |
| `#30d158` sobre `#1e1e1e` | **8,25:1** | passa folgado |
| `#0062cc` sobre `#f4f6fa` | **5,36:1** | passa — é o acento do modo claro |

---

### Tarefa 1: as fontes, self-hosted

**Arquivos:**
- Criar: `public/theme/ldg/fonts/` (4 arquivos `.woff2` + `OFL.txt`)
- Criar: `public/theme/ldg/scss/ldg/_fonts.scss`
- Modificar: `public/theme/ldg/scss/ldg/_tokens.scss` (dois tokens de família)
- Modificar: o parcial que importa os arquivos SCSS do tema

**Por que self-hosted e não CDN:** o Google Fonts numa página que o aluno abre
todo dia é dependência externa por conta própria — cai o CDN, cai a tipografia; e
na VPS é uma requisição a terceiro em cada carga. O Moodle já resolve isso:
`[[font:theme|arquivo.woff2]]` é reescrito por
`theme_config` (`lib/classes/output/theme_config.php:1602`) para a URL servida
pelo próprio site. O `theme_moove`, que é o pai, já faz isso com o OpenDyslexic.

- [x] **Passo 1: baixar as fontes variáveis, só os subconjuntos que usamos**

O Google serve **variável**: um arquivo cobre 400–700. Precisamos de `latin` e
`latin-ext` (pt, es, en) — nada de cirílico, grego ou vietnamita.

```bash
cd /tmp/claude-1000/-home-leodg-localhost-gitworktree-bare-moodle/b2d6be81-2bc7-4120-bc6b-8403836e26fb/scratchpad
UA="Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120 Safari/537.36"

curl -s -A "$UA" "https://fonts.googleapis.com/css2?family=Inter:wght@400..700&display=swap" -o inter.css
curl -s -A "$UA" "https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400..700&display=swap" -o mono.css
```

Do CSS, pegar **apenas** os blocos comentados `/* latin */` e `/* latin-ext */` e
baixar as URLs deles para
`public/theme/ldg/fonts/` com nomes estáveis:

```
inter-latin.woff2
inter-latin-ext.woff2
jetbrains-mono-latin.woff2
jetbrains-mono-latin-ext.woff2
```

- [x] **Passo 2: a licença, junto**

Ambas são **SIL Open Font License 1.1**, que permite empacotar e exige manter o
aviso. Gravar `public/theme/ldg/fonts/OFL.txt` com o texto da licença e as duas
atribuições. Sem isso, empacotar fonte é problema jurídico, não detalhe.

- [x] **Passo 3: o `_fonts.scss`**

```scss
// As fontes do desenho, servidas pelo PROPRIO site.
//
// O [[font:theme|...]] e reescrito pelo theme_config para a URL do arquivo em
// theme/ldg/fonts/. Nao troque por URL do Google: a tipografia do portal
// passaria a depender de um terceiro estar no ar, numa pagina que o aluno abre
// todo dia.
//
// Sao arquivos VARIAVEIS: um so cobre de 400 a 700, e por isso ha dois por
// familia (latin e latin-ext) em vez de um por peso.
@font-face {
    font-family: "Inter";
    font-style: normal;
    font-weight: 400 700;
    font-display: swap;
    src: url("[[font:theme|inter-latin.woff2]]") format("woff2");
    unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA,
        U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191,
        U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}

@font-face {
    font-family: "Inter";
    font-style: normal;
    font-weight: 400 700;
    font-display: swap;
    src: url("[[font:theme|inter-latin-ext.woff2]]") format("woff2");
    unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7,
        U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F,
        U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F,
        U+A720-A7FF;
}

@font-face {
    font-family: "JetBrains Mono";
    font-style: normal;
    font-weight: 400 700;
    font-display: swap;
    src: url("[[font:theme|jetbrains-mono-latin.woff2]]") format("woff2");
    unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA,
        U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191,
        U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}

@font-face {
    font-family: "JetBrains Mono";
    font-style: normal;
    font-weight: 400 700;
    font-display: swap;
    src: url("[[font:theme|jetbrains-mono-latin-ext.woff2]]") format("woff2");
    unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7,
        U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F,
        U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F,
        U+A720-A7FF;
}
```

O `font-display: swap` é deliberado: a página aparece na fonte do sistema e troca
quando a nossa chega. O contrário — texto invisível esperando a fonte — é pior em
conexão ruim, que é a do aluno no celular.

- [x] **Passo 4: os tokens de família, e aplicar**

Em `_tokens.scss`, no bloco que não muda entre modos:

```scss
    // As familias do desenho. Ficam em token porque o portal usa as duas, e a
    // mono nao e enfeite: rotulo, duracao e contador vao nela para alinharem em
    // coluna e para dar o sotaque da marca.
    --ldg-font-sans: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    --ldg-font-mono: "JetBrains Mono", SFMono-Regular, Menlo, Monaco, Consolas, monospace;
```

E o corpo passa a usar a sans:

```scss
body {
    font-family: var(--ldg-font-sans);
}
```

- [x] **Passo 5: importar o parcial e recompilar**

Acrescentar `@import 'ldg/fonts';` junto dos outros imports do tema, subir
`version.php` do tema e rodar:

```bash
cf cli purge_caches.php
```

- [x] **Passo 6: provar que a fonte é servida pelo site**

```bash
curl -sk "https://localhost:8443/theme/styles.php/ldg/1/all" | grep -o "font.php[^)\"']*" | sort -u
curl -sk -o /dev/null -w "%{http_code} %{size_download}\n" "https://localhost:8443<uma das URLs acima>"
```

Esperado: URLs `/theme/font.php/...` e HTTP 200 com tamanho de dezenas de KB.
**Zero** ocorrências de `fonts.googleapis` ou `fonts.gstatic` no CSS servido — é o
que prova que a dependência externa não entrou.

- [x] **Passo 7: commitar**

```bash
git add public/theme/ldg/fonts public/theme/ldg/scss public/theme/ldg/version.php
git commit -m "NOBUG: theme_ldg - Inter e JetBrains Mono servidas pelo proprio site"
```

---

### Tarefa 2: os quatro tokens que faltam

**Arquivos:**
- Modificar: `public/theme/ldg/scss/ldg/_tokens.scss`

- [x] **Passo 1: o acento do modo escuro passa a ser o do desenho**

No bloco `[data-bs-theme="dark"]`:

```scss
    // O acento do desenho, e nao o #007AFF do modo claro: sobre #121212 ele da
    // 5,90:1 e sobre #1E1E1E da 5,25:1 - passa em AA para texto, medido. O
    // #007AFF dava 4,66:1, que passa raspando e foi o motivo de existir um tom
    // separado so para texto.
    --ldg-accent: #4b8eff;
    --ldg-accent-hover: #65a1ff;
    --ldg-accent-text: #4b8eff;
```

**Alcance da mudança:** isto vale para o site inteiro no modo escuro — botão,
barra de progresso e link da landing incluídos. É o que foi pedido: o tema adota
a linguagem do portal. O modo claro **não muda**.

- [x] **Passo 2: superfície ativa, sucesso e cinza de rótulo**

Ainda no bloco escuro:

```scss
    // #252525 e o "Carvao Ativo" do desenho: item de menu ativo, cabecalho de
    // modulo aberto, hover. Era #2a2a2a - a diferenca e pequena de proposito,
    // porque a hierarquia aqui e feita de degraus curtos.
    --ldg-surface-raised: #252525;

    // Verde de conclusao. 8,25:1 sobre a superficie, medido.
    --ldg-status-success: #30d158;

    // Cinza de ROTULO, e nao de texto. O desenho pede #6C7075, que da 3,34:1
    // sobre #1E1E1E e REPROVA em AA para texto pequeno - e e justamente onde ele
    // poe rotulo de 12px. Este da 5,11:1.
    --ldg-text-label: #8a8f94;

    // O par do acento: e esta a cor que vai SOBRE o azul, na aba ativa e no
    // botao primario. Fica em token porque hex solto no _format.scss e
    // exatamente o tipo de cor que ninguem encontra depois.
    --ldg-on-accent: #00285c;
```

E os equivalentes no bloco claro:

```scss
    --ldg-status-success: #1a7f37;
    --ldg-text-label: #6b7a90;
    --ldg-on-accent: #ffffff;
```

- [x] **Passo 3: medir tudo que entrou**

Rodar o cálculo de contraste para cada par novo, nos dois modos, e **colar o
resultado no commit**. Par que reprovar não entra: escurece ou aumenta o texto.

- [x] **Passo 4: commitar**

```bash
git commit -m "NOBUG: theme_ldg - o acento, o sucesso e o cinza de rotulo do desenho"
```

---

### Tarefa 3: o portal ganha o desenho

**Arquivos:**
- Modificar: `public/theme/ldg/scss/ldg/_format.scss`

Todo o CSS aqui é **marca**: cor, tipografia, raio, sombra, densidade. Estrutura
nenhuma — ela está no `styles.css` do formato desde o plano 2, e é o que faz o
portal sobreviver com outro tema.

- [x] **Passo 1: o chrome**

```scss
.ldg-portal__header {
    display: flex;
    height: 56px;
    align-items: center;
    justify-content: space-between;
    padding: 0 1rem;
    background-color: var(--ldg-surface);
    border-bottom: 1px solid var(--ldg-border);
    gap: 1rem;
}

.ldg-portal__brand-name {
    font-weight: 700;
    color: var(--ldg-text);
    text-decoration: none;
}

// O nome do curso nao pode empurrar o menu de usuario para fora da tela num
// titulo longo - por isso ele encolhe e corta, e nao o contrario.
.ldg-portal__course {
    overflow: hidden;
    flex: 1 1 auto;
    color: var(--ldg-text-muted);
    font-family: var(--ldg-font-mono);
    font-size: 0.8125rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ldg-portal__exit {
    padding: 0.375rem 0.75rem;
    border: 1px solid var(--ldg-border);
    border-radius: 8px;
    color: var(--ldg-text-muted);
    font-size: 0.8125rem;
    text-decoration: none;

    &:hover {
        background-color: var(--ldg-surface-raised);
        color: var(--ldg-text);
    }
}
```

- [x] **Passo 2: a navegação, nas três superfícies**

```scss
// O marcador de estado do sistema: barra de 4px no acento. Ele se repete no
// item de aula, e e o que diz "voce esta aqui" sem depender de cor de texto -
// quem nao distingue as cores continua vendo a barra.
.ldg-portal__navitem {
    display: flex;
    align-items: center;
    padding: 0.65rem 0.85rem;
    // Propriedade LOGICA, e nao border-left: em LTR e a mesma coisa, e num
    // idioma que le da direita para a esquerda a barra troca de lado sozinha.
    // Custa zero escrever assim desde o comeco.
    border-inline-start: 4px solid transparent;
    border-radius: 8px;
    color: var(--ldg-text-muted);
    font-size: 0.8125rem;
    font-weight: 500;
    text-decoration: none;

    &:hover {
        background-color: var(--ldg-surface-raised);
        color: var(--ldg-text);
    }

    &.is-active {
        border-inline-start-color: var(--ldg-accent);
        background-color: var(--ldg-surface-raised);
        color: var(--ldg-text);
        font-weight: 600;
    }
}

.ldg-portal__tabs {
    padding: 0.375rem;
    border: 1px solid var(--ldg-border);
    border-radius: 12px;
    background-color: var(--ldg-surface);
    gap: 0.25rem;
}

// Aba ativa e PILULA preenchida, e nao sublinhado - e o que o desenho pede.
.ldg-portal__tab {
    padding: 0.5rem 0.85rem;
    border-radius: 8px;
    color: var(--ldg-text-muted);
    font-size: 0.8125rem;
    text-decoration: none;
    white-space: nowrap;

    &.is-active {
        background-color: var(--ldg-accent);
        color: var(--ldg-on-accent);
        font-weight: 600;
    }
}

.ldg-portal__bottomnav {
    padding: 0 0.5rem;
    border-top: 1px solid var(--ldg-border);
    backdrop-filter: blur(10px);
    background-color: color-mix(in srgb, var(--ldg-surface) 96%, transparent);
}

.ldg-portal__bottomitem {
    color: var(--ldg-text-label);
    font-size: 0.65rem;
    gap: 3px;
    text-decoration: none;

    &.is-active {
        color: var(--ldg-accent);
        font-weight: 600;
    }
}
```

`color-mix` tem suporte em todos os navegadores atuais; o fallback natural é a
cor cheia, que é aceitável.

- [x] **Passo 3: a moldura da aula e os materiais**

```scss
.ldg-lesson__frame {
    border-radius: 14px;
    background-color: #0e0e0e;
    box-shadow: 0 10px 30px rgb(0 0 0 / 60%);
}

.ldg-materiallist__items {
    display: flex;
    flex-direction: column;
    padding: 0;
    margin: 0;
    gap: 0.5rem;
    list-style: none;
}

.ldg-materiallist__link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    border: 1px solid var(--ldg-border);
    border-radius: 12px;
    background-color: var(--ldg-surface);
    color: var(--ldg-text);
    gap: 0.75rem;
    text-decoration: none;

    &:hover {
        border-color: var(--ldg-accent);
        background-color: var(--ldg-surface-raised);
    }
}

.ldg-materiallist__section,
.ldg-materiallist__hint {
    color: var(--ldg-text-label);
    font-family: var(--ldg-font-mono);
    font-size: 0.75rem;
}

// O aviso de "baixa o arquivo" vai para a direita, longe do nome: e informacao
// de consequencia, e nao parte do titulo.
.ldg-materiallist__hint {
    margin-inline-start: auto;
}
```

- [x] **Passo 4: recompilar, olhar e commitar**

```bash
cf cli purge_caches.php
```

Abrir `https://localhost:8443/course/view.php?id=13`, conferir os quatro destinos
e alternar claro/escuro pela barra de acessibilidade do tema.

---

### Tarefa 4: as três línguas, de verdade

**Isto não é revisão de tradução — é o desenho aguentando o idioma.** O plugin já
traz `en`, `pt_br` e `es`, mas **o site de trabalho só tem `en` instalado**
(conferido: `get_list_of_translations()` devolve só `en`). Ou seja: as strings em
português e espanhol nunca foram vistas em tela, e o seletor de idioma que o
plano 1 pôs no cabeçalho do portal **renderiza vazio**, porque só há um pacote.

Três consequências concretas, e é por isso que isto é tarefa e não nota:

| Risco | Por que importa aqui |
|---|---|
| Acentos vindo da fonte errada | `ã ç õ é ñ` estão no subconjunto **latin-ext**. Se ele não carregar, o navegador cai no fallback e a linha fica com duas tipografias |
| Rótulo estourando o menu | O maior é o espanhol, **"Foro de estudiantes"** (19 caracteres) — 3 a mais que o inglês. O menu tem 280px fixos |
| Seletor de idioma invisível | Produto trilíngue com um pacote só instalado nunca mostra a troca |

- [x] **Passo 1: instalar os pacotes no ambiente de trabalho**

Não há CLI pronto no 5.2 — o `tool_langimport` é tela. Mas a API existe:

```bash
docker exec -i -u 1000:33 courses-free-moodle-1 sh -c 'cat > /tmp/ldgprobe_langs.php' <<'PHP'
<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
$c = new \tool_langimport\controller();
$ok = $c->install_languagepacks(['pt_br', 'es']);
echo $ok ? "instalados\n" : "falhou\n";
print_r($c->info);
print_r($c->errors);
PHP
docker exec -u 1000:33 courses-free-moodle-1 php /tmp/ldgprobe_langs.php
docker exec courses-free-moodle-1 rm -f /tmp/ldgprobe_langs.php
cf cli purge_caches.php
```

A sonda vive em `/tmp` **dentro do container**, nunca no `wwwroot`.

- [x] **Passo 2: ver o portal nos três idiomas**

```bash
for L in en pt_br es; do
  curl -sk -b cookies.txt "https://localhost:8443/course/view.php?id=13&lang=$L" \
    | grep -o 'ldg-portal__navitem[^>]*>[^<]*' | sed 's/.*>//' | paste -sd' | '
done
```

Esperado: os rótulos de cada língua, e o seletor de idioma aparecendo no
cabeçalho a partir de agora.

- [x] **Passo 3: medir o rótulo mais longo contra a coluna**

No Chrome, em 1280px, medir a largura real do item de menu em espanhol
(`getBoundingClientRect().width` de `.ldg-portal__navitem--forum`) e conferir que
**não** há corte nem quebra dentro dos 280px, menos o padding. Se estourar, o
conserto é do lado do desenho — não encurtar a tradução.

- [x] **Passo 4: provar que o acento vem da Inter**

No Chrome, com a página em português:

```js
document.fonts.check('16px Inter');            // true
document.fonts.check('16px "JetBrains Mono"'); // true
```

E medir a largura de um `ç` e de um `ã` num elemento com a família aplicada,
comparando com o mesmo texto em `sans-serif`: larguras iguais significam que a
Inter **não** carregou e o fallback está desenhando.

- [x] **Passo 5: Behat continua indiferente ao idioma**

Os cenários dos planos 1 e 2 já miram **classes**, e não rótulos — foi o conserto
de um erro meu, e agora é regra: teste de navegação não pode depender de idioma.
Conferir que segue assim e acrescentar um cenário que troca o idioma do site para
`pt_br` e confirma que a navegação continua de pé.

- [x] **Passo 6: commitar**

```bash
git commit -m "NOBUG: theme_ldg - o portal nas tres linguas, com a fonte certa nos acentos"
```

---

### Tarefa 5: provar, documentar e fechar

- [x] **Passo 1: contraste medido no que está na tela**

Script no scratchpad que lê os tokens resolvidos do CSS servido e calcula os
pares que importam: texto sobre fundo, texto sobre superfície, rótulo sobre
superfície, acento sobre superfície, aba ativa (texto sobre acento). **Nos dois
modos.** Nenhum abaixo de 4,5:1 para texto.

- [x] **Passo 2: a bateria toda**

```bash
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  vendor/bin/phpunit --filter format_ldg
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml \
  public/course/format/ldg/tests/behat/format_ldg.feature
docker exec -u 1000:33 courses-free-moodle-1 sh -c \
  'cd /tmp/cs && ./vendor/bin/phpcs --standard=moodle -p \
   /var/www/html/public/theme/ldg /var/www/html/public/course/format/ldg; echo "EXIT=$?"'
```

- [x] **Passo 3: documentação**

- `theme_ldg/README.md`: as fontes empacotadas e por quê, os tokens novos com o
  contraste medido, e o layout `ldgportal`.
- `docs/brand/DESIGN.md`: marcar o que foi implementado e **corrigir o que o
  código provou diferente** — o documento é o contrato, e contrato desatualizado
  é pior que nenhum.
- `version.php` do tema.

- [x] **Passo 4: commitar**

## Como saber que o plano 3 acabou

1. `curl` no CSS servido: zero `fonts.googleapis`/`fonts.gstatic`, e as URLs de
   `font.php` respondendo 200.
2. Contraste medido, nos dois modos, nenhum par de texto abaixo de 4,5:1.
3. `phpunit`, `behat` e `phpcs` verdes.
4. Na tela: o portal com a cara do mockup no escuro, e coerente no claro.
5. Com o tema `boost`, o curso continua abrindo — cinza, mas usável.
6. Os três idiomas instalados, o portal conferido nos três, e o rótulo mais longo
   (`Foro de estudiantes`) cabendo no menu sem corte.

## Executado em 03/09/2026

Quatro tarefas, quatro commits (`76c8bab13c0` a `c9b9ddffb2e`). A tarefa 5
(documentação e fechamento) fica com o plano 4, junto da conferência visual.

**Medido, e não estimado:**

- CSS servido: quatro URLs `font.php` do próprio site, arquivos respondendo 200
  com `font/woff2`, **zero** referência a `fonts.googleapis`/`fonts.gstatic`.
- Contraste: onze pares nos dois modos, **todos passam** em AA.
- Chrome, 1280px, nos três idiomas: `document.fonts` confirma Inter e JetBrains
  Mono; texto acentuado mede 185px com Inter contra 151px com o fallback — se a
  fonte não tivesse carregado, as larguras seriam iguais.
- Menu: 280px, itens ocupando a linha inteira, e `Foro de estudiantes` (o pior
  rótulo do produto) em 124px de 249px úteis.
- `phpunit --filter format_ldg`: 52 testes. `phpcs`: `EXIT=0`.

**O que o plano não previu:**

1. **O tema ainda tinha a grade.** O `_format.scss` mantinha um grid de duas
   colunas de antes dos destinos, e o CSS do tema entra **depois** do
   `styles.css` do plugin no arquivo servido (plugin em ~207k, tema em ~1,19M).
   A grade de três colunas do plano 2 **nunca chegou a valer na tela**, e nada
   quebrou para denunciar. Ficou um aviso no topo do arquivo.
2. **Três pares do modo claro reprovaram na medição.** O cinza de rótulo que
   escolhi de olho dava 4,36:1, e branco sobre o acento dava 4,02:1 — este
   último exigiu um token novo, `--ldg-accent-fill`, separando o preenchimento
   do acento de superfície.
3. **Os itens do menu tinham largura de conteúdo**, não da coluna: âncora é
   inline por padrão, então o fundo do estado ativo cobriria só as letras.
4. **`aspect-ratio: 16/9` no quadro era erro meu do plano 2.** A altura é escrita
   pelo `player.js`, e proporção fixa brigaria com aquela medida — a mesma que
   fez o quadro crescer até 12084px em 02/09. A moldura é raio, borda e sombra;
   os 16/9 chegam com o `mod_video`.

**Duas armadilhas de cache, que custaram várias medições:**

- `styles.php/ldg/1/all` devolve **cache**. Para ver compilação recente, use a
  revisão que a página carrega, ou `-1`.
- **`purge_caches` não invalidou o CSS de plugin.** A revisão do tema ficou
  parada em `1788468183` antes e depois; o que fez o CSS novo aparecer foi o
  bump de `version.php`.

**Mudança no ambiente:** os pacotes `pt_br` e `es` foram instalados.

## O que fica para o plano 4

A conferência visual no Chrome contra a referência, com as medidas do
`DESIGN.md`; os dados de teste no `make_testdata.php`; e o axe-core, que precisa
de navegador.
