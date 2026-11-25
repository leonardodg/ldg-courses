# Canonical, hreflang e título: as regras que o código implementa

Este documento existe porque as três coisas se sustentam mutuamente e quebram
juntas. Cada seção traz o ponto exato do core que sustenta a solução — foi o que
custou caro descobrir, e é o que se perde se ficar só na cabeça de quem
investigou.

## A regra central: cada versão de idioma se autocanonicaliza

Uma página que declara `hreflang` **precisa** apontar a própria `canonical` para
ela mesma. Não é preferência de estilo:

- `canonical` diz *"a versão boa deste conteúdo é aquela ali"*.
- `hreflang` diz *"estas são versões equivalentes, para públicos diferentes"*.

Quando `/?lang=pt_br` declara `canonical` para `/`, as duas afirmações se
contradizem, e **a canônica vence**. O buscador consolida as três versões numa só
e descarta o cluster inteiro — a versão em português nunca é indexada. O sintoma
no Search Console é `?lang=pt_br` aparecendo em *"Página alternativa com tag
canônica adequada"*, que parece um aviso benigno e não é.

Foi exatamente o estado do site até esta rodada: `canonical_url()` devolvia
sempre a URL sem parâmetro de idioma, e `alternate_links()` montava os alternates
a partir dela. As duas peças eram coerentes entre si e erradas juntas.

**Como fica:** a canônica é a URL *como foi pedida*, normalizada — com o `lang`
quando a requisição o traz, sem ele quando não traz. A versão sem parâmetro é o
`x-default`, e é a única que pode aparecer sem `lang`.

### Três superfícies precisam concordar

| Superfície | Onde | O que declara |
|---|---|---|
| `<head>` da página | `classes/seo.php` | `canonical` + `hreflang` de cada irmã |
| Sitemap | `sitemap.php` | uma entrada `<url>` **por idioma** |
| A URL servida | o próprio Moodle | precisa responder 200, não redirecionar |

Divergência entre elas não dá erro em lugar nenhum: o buscador simplesmente
escolhe sozinho, e normalmente escolhe errado.

## O sitemap precisa de uma entrada por idioma

O formato antigo listava **uma** entrada `<url>` com todos os alternates dentro.
Parece econômico e está errado: o protocolo exige que **cada URL do cluster tenha
a própria entrada `<url>`, repetindo o conjunto completo de alternates, inclusive
a que aponta para ela mesma**. Sem isso não existe link de retorno, e o Google
descarta o cluster — o mesmo efeito da canônica contraditória, por outro caminho.

Três idiomas mais o `x-default` viram quatro entradas por página, não uma.

## O título: o hook de `<head>` é tarde demais

O template do tema resolve nesta ordem
(`theme/boost/templates/head.mustache:23`):

```html
<title>{{{ output.page_title }}}</title>
{{{ output.standard_head_html }}}
```

`page_title` é avaliado **antes** de `standard_head_html`. Como o hook
`before_standard_head_html_generation` roda dentro do segundo, chamar
`$PAGE->set_title()` a partir dele não tem efeito nenhum — e o silêncio é total:
nenhum erro, nenhum aviso, só o título default do Moodle na página.

O ponto certo é o `before_http_headers`, disparado no início de
`core_renderer::header()` (`lib/classes/output/core_renderer.php:836`), antes de
qualquer template ser resolvido.

**Era por isso que a landing na raiz saía com `Home | LDG`** enquanto a mesma
página em `/local/partners/index.php` saía com o título bom: só o `index.php` do
plugin chamava `set_title()`, e a raiz nunca chamava ninguém.

### A marca entra na string, não no nome do site

`moodle_page::set_title()` tem um segundo parâmetro (`lib/pagelib.php:1421`):

```php
public function set_title($title, bool $appendsitename = true)
```

Com `false`, o Moodle **não** anexa o nome do site, e o título fica inteiramente
sob controle da string de idioma. É o que permite a marca acompanhar o idioma:

| Idioma | `seotitle` |
|---|---|
| en | `Sell your online courses \| LDG Technology` |
| pt_br | `Venda seus cursos online \| LDG Tecnologia` |
| es | `Venda sus cursos en línea \| LDG Tecnología` |

A alternativa — deixar o core anexar o nome curto do site — amarraria a marca a
um valor único no banco, e o título em português sairia com o sufixo em inglês.

## Escape: uma vez, e no lugar certo

`format_string()` escapa para HTML por padrão. Isso deu dois defeitos do mesmo
tipo, e nenhum dos dois quebra teste ou validador:

| Onde | Sintoma |
|---|---|
| Dentro de `<script type="application/ld+json">` | o conteúdo é **texto cru**, e o parser lê `&amp;` literalmente. O nome da empresa chega corrompido ao buscador |
| Em `content="…"` de meta tag | `format_string()` escapa e o `s()` escapa de novo: `&` vira `&amp;amp;`, e o compartilhamento mostra a entidade |

A regra: `seo::site_name()` devolve o nome **sem escape**, e quem escapa é o `s()`
do atributo — uma vez só. No JSON-LD ninguém escapa, porque `json_encode()` já
cuida do que precisa (e o `JSON_HEX_TAG` impede que um dado feche o `<script>`).

## `og:locale`: território só quando ele existe

O Open Graph pede `língua_TERRITÓRIO`, e há duas maneiras tentadoras de completar
o que falta. As duas estão erradas, e custaram uma ida e volta:

| Caminho | O que declara | Por que não |
|---|---|---|
| `en_US` fixo no código | inglês americano | ninguém configurou território nenhum |
| `locale` do `langconfig` | `en_AU` | é a convenção do pacote base do Moodle, não uma escolha do site |

**O pacote `en` do Moodle é inglês global.** O site em inglês não é americano nem
australiano, e declarar qualquer um dos dois é alegação falsa — a mesma regra que
faz `seo::brand()` omitir endereço e telefone em vez de preencher com
placeholder.

O território sai do **código do idioma**, e só quando existe ali:

| Idioma | `og:locale` |
|---|---|
| `en` | `en` |
| `es` | `es` |
| `pt_br` | `pt_BR` |
| `es_ar` | `es_AR` |

Sem território reconhecido, o consumidor cai no padrão dele — que é melhor que
uma alegação falsa.

## O hero: preload em vez de virar `<img>`

A foto do hero é o fundo de uma `<section>`, e **o preload scanner do navegador
não enxerga imagem de fundo**: ela só começa a baixar depois do CSSOM, e é a
candidata natural a maior elemento visível da página (LCP).

A correção óbvia seria virar `<img>` com `fetchpriority="high"`. Não foi o
caminho, por duas razões:

- O contraste daquele bloco foi **medido no pixel** sobre a composição
  foto+cortina. Trocar o fundo por um elemento no fluxo mexe no empilhamento e
  obrigaria a remedir tudo.
- A imagem é uma malha abstrata: **decorativa**. Viraria `<img alt="">`, então
  não há ganho de busca por imagem para compensar o risco — e um `alt`
  descritivo com palavra-chave numa imagem que não ilustra nada seria invenção.

`<link rel="preload" as="image" fetchpriority="high">` devolve a descoberta para
o início do documento com o mesmo efeito e sem tocar no layout. Sai **só na
landing**: a candidatura não mostra o hero, e preload de recurso que a página não
usa é banda jogada fora, com aviso no console.

## A armadilha que bloqueou tudo: `enablemyhome`

Com o Painel desligado, o `index.php` do core redireciona **todo visitante
anônimo** para o login, e o `forcelogin` não tem nada a ver com isso
(`public/index.php:79`):

```php
if (empty($CFG->enablemyhome)) {
    if (!isloggedin()) {
        // Non-logged-in users must log in first (forcelogin may be off, but the
        // page they are headed for is disabled, so send them to the login page).
        redirect(get_login_url());
    }
```

Em produção isso significou: a raiz devolvendo 303 para o login, e portanto
**todas** as canônicas, todos os `hreflang` e a entrada principal do sitemap
apontando para um redirecionamento — para uma página que ainda por cima é
`noindex`. Nenhuma configuração de login explica o sintoma, e é natural procurar
horas no lugar errado.

Procurar por: *Administração do site → Aparência → Navegação → Painel ativado*.

Quem for diagnosticar de novo, o teste é de uma linha:

```bash
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' https://courses.leodg.dev/
```

`200` e vazio: a landing está servindo. `303` para `/login/index.php`: é o
Painel, não o login.

## Como conferir a rodada inteira

```bash
# a canonica de cada idioma aponta para ela mesma
for u in "/" "/?lang=pt_br" "/?lang=es"; do
  curl -s "https://courses.leodg.dev$u" | grep -o '<link rel="canonical"[^>]*>'
done

# o sitemap tem uma entrada por idioma, e nao uma so
curl -s https://courses.leodg.dev/sitemap.xml | grep -c '<loc>'

# o titulo da raiz e o titulo bom, com a marca do idioma
curl -s "https://courses.leodg.dev/?lang=pt_br" | grep -o '<title>[^<]*</title>'
```
