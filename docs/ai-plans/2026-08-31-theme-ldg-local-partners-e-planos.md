> **Situação:** executado · **Início:** 2026-08-31
> **Origem:** `~/.claude/plans/shiny-dreaming-popcorn.md`, **sobrescrito em 02/09** por outro plano.
> Recuperado da transcrição da sessão `85804314-…`, do `ExitPlanMode` de 31/08 às 19:31 — é o
> texto aprovado, literal, sem uma linha reescrita.
> **Resultado:** as sete fases entraram pelo **PR #61**. Conferido no código: `local_partners`
> existe, o `theme_ldg` sobrescreve `layout/frontpage.php`, as tabelas `local_marketplace_plan` e
> `local_marketplace_plan_tier` existem, e a ADR-0006 registra a aprovação de parceiro.
> **Uma ponta ficou aberta quatro dias:** o item 3 da Fase 0 — versionar os assets de marca de
> `docs/brand/` — só foi fechado em 04/09, pelo PR #68. Os arquivos ficaram esse tempo todo no
> índice do git da worktree `docs-organize-and-improve`, sem commit.
>
> Os PRs #63 (Behat) e #64 (`theme_ldg` sem Moove) **não** saíram deste plano: ele não menciona
> nenhum dos dois.

# Tema global `theme_ldg` + landing de captação `local_partners` + planos no banco

## Contexto

A plataforma (ex-"courses-free") deixou de ser só um marketplace de cursos grátis: o
objetivo agora é captar empresas parceiras que vendem cursos e pagam comissão, mantendo
a modalidade gratuita. Falta a camada que o cliente-empresa vê antes de fechar a
parceria — hoje o site usa o `theme_moove` cru e não tem home de captação.

Três lacunas, nesta ordem de dependência:

1. **Identidade visual.** Existe design system pronto (`docs/brand/design_system_leodg.md`
   + `design_system_moove.md`): dark-first, Inter, `#007AFF` sobre `#121212`/`#1E1E1E`.
   Nada disso está no Moodle. A marca é a band **LDG/LeoDG** — decisão consciente de
   usar a marca do desenvolvedor até existir nome fantasia, quando tudo será refeito.
2. **Home de captação.** Não existe. Visitante anônimo cai na frontpage de marketing
   genérica do Moove ("Lorem ipsum", números de curso).
3. **Planos comerciais.** Starter (R$ 0/mês, 9,9%, vídeo nativo com trava de resolução
   por ticket) e Pro/Scale (R$ 97–197/mês, 0–3,9%, BYOS) existem só em `docs/planos.md`.
   O banco não tem conceito de plano; `company.commissionpct` é negociada uma a uma.

Resultado pretendido: site com a cara da marca, `/` mostrando a landing de parceria para
quem não está logado, planos **editáveis por tela de admin** (nascem com valores
fictícios ajustáveis), e um funil candidatura → aprovação → empresa provisionada que
respeita a decisão fechada de que ninguém cria empresa sozinho.

## Decisões tomadas com o usuário

| Decisão | Escolha |
|---|---|
| Tema pai | **Filho do `theme_moove`** (nunca editar o Moove direto) |
| Componente | **`theme_ldg`** em `public/theme/ldg` (aposenta o placeholder `coursesfree`) |
| Cadastro na landing | **Candidatura + aprovação do admin** — respeita "sem auto-atendimento para criar empresa" do CLAUDE.md |
| Planos | **No banco agora**, com CRUD de admin; seed inicial com valores fictícios ajustáveis pela tela |
| Page builder | **Não.** Mustache fixo + settings tipados (ver "Por que não um page builder") |

## Achados que moldam o desenho (verificados no código)

- **`$THEME->parents` é lista plana** (`public/lib/classes/output/theme_config.php:517`).
  Precisa ser `['moove', 'boost']`; só `['moove']` derruba silenciosamente os overrides
  que o Boost faz de templates `core/*`.
- **Os callbacks SCSS do pai rodam junto com os do filho**, recebendo a config do filho
  (`theme_config.php:1345-1400`, conferido). Ou seja, `theme_moove_get_pre_scss($theme)`
  vai ler `theme_ldg/brandcolor`. Como as duas funções emitem `$var: valor;` sem
  `!default`, a última vence — e a última é a do filho.
- **`theme_moove_get_extra_scss` tem `return '';` na linha 76** (`public/theme/moove/lib.php`),
  antes de concatenar `$theme->settings->scss`. Bug real: sem imagem de login, o SCSS
  extra do admin é descartado.
- **`theme_config::load('moove')` está hardcoded** em `classes/util/settings.php:55` e em
  4 pontos de `classes/output/core_renderer.php` (`:65, :198, :209, :311`). Um filho que
  herde esses métodos lê os settings do *Moove*, não os dele.
- **O template finder já resolve os `theme_moove/...` hardcoded**: basta o filho pôr o
  arquivo em `public/theme/ldg/templates/theme_moove/<nome>.mustache`
  (`mustache_template_finder.php:69-80`). Não é preciso reescrever `layout/login.php`.
- **Import path do SCSS é `[ldg/scss, moove/scss, boost/scss]`** (`theme_config.php:1416`).
  Criar `public/theme/ldg/scss/moove/` sombrearia o do pai sem aviso. Proibido.
- **Risco de CI verificado**: `.github/workflows/deploy.yml:204-212` monta `--extra-plugins`
  **só** com os plugins de `.github/moodle-plugins.txt`. O `theme_moove` está em
  `moodle-plugins-thirdparty.txt`, que não entra. Adicionar `public/theme/ldg` à lista sem
  ajustar o workflow instala um filho sem o pai → falha dura no CI.
- **`docs/planos.md` está untracked e NÃO gitignored.** Um `git add .` publica margem e
  custo no GitHub, e o rsync do deploy leva para a VPS.

## Por que não um page builder

Analisamos o `local_edwiserpagebuilder`: 25k linhas de PHP + 139k de JS (VvvebJs forkado),
catálogo de blocos atrás de CDN + licença EDD, telemetria na instalação. E o decisivo:
**ele nem resolve a home** — delega ao tema pago RemUI. Para uma landing única, escrita
por quem também escreve o código, mustache versionado em git é melhor: revisável em PR,
HTML semântico, e elimina a classe inteira de risco de guardar HTML arbitrário no banco.
Vale imitar dele só o hook de meta tags SEO.

---

## Fases

Cada fase deixa o site no ar e vira um PR para `dev`. Trabalhar numa worktree própria:
`cf new theme-ldg` (ramifica de `origin/dev`).

### Fase 0 — Higiene (antes de qualquer código)

1. Mover `docs/planos.md` e `docs/rascunho.md` para `docs/private/`.
2. `/docs/private/` no `.gitignore` **versionado** (não em `.git/info/exclude`: um exclude
   invisível é exatamente a "solução que depende de a configuração estar certa para ser
   segura" que o usuário rejeita).
3. Versionar os assets de marca de `docs/brand/` (hoje untracked) — são insumo do tema.
4. Regra em `docs/README.md`: os números que podem entrar em código versionado são os que
   a landing **exibe** (0/97/197, 9,9%/3,9%/0%, 720p/1080p/4K, R$ 49,90, R$ 200). Margem,
   custo de banda e comparação com concorrente, nunca.

Verificar: `git check-ignore -v docs/private/planos.md` responde; `git status --short` não
lista nada de `docs/private/`.

### Fase 1 — `theme_ldg` instalável, visualmente idêntico ao Moove

Isola "o filho está bem plugado?" de "o design ficou bom?".

```
public/theme/ldg/
  version.php    theme_ldg, requires 2026042000, supported [502,502], MATURITY_ALPHA,
                 dependencies ['theme_moove' => 2026042100, 'theme_boost' => 2026042000]
  config.php     parents ['moove','boost'], rendererfactory overridden, callbacks proprios
  lib.php        theme_ldg_get_{main_scss_content,pre_scss,extra_scss,precompiled_css}
                 + theme_ldg_pluginfile
  settings.php   abas Marca / Advanced / Footer
  classes/output/core_renderer.php   extends \theme_moove\output\core_renderer
  classes/util/settings.php          theme_config::load('ldg'), so o metodo footer()
  scss/default.scss + scss/ldg/_tokens.scss   (vazios nesta fase)
  pix/screenshot.png
  lang/{en,pt_br,es}/theme_ldg.php
  README.md      diz que e filho do Moove e o que NAO fazer
```

Pontos não óbvios:

```php
// config.php — lista PLANA, do mais especifico ao mais generico.
// theme_config nao expande pais dos pais (theme_config.php:517).
$THEME->parents = ['moove', 'boost'];
// Sem isto o \theme_ldg\output\core_renderer NUNCA e instanciado, e sem erro nenhum.
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
```

```php
// lib.php — o setting de fundo do login chama-se 'loginbg', e NAO 'loginbgimg',
// de proposito: o theme_moove_get_extra_scss roda antes deste com a config do
// FILHO; com nome diferente ele encontra vazio, sai no return '' da linha 76 e
// nao emite nada duplicado. De quebra contorna o bug em que aquele return
// descarta o setting 'scss' do admin.
function theme_ldg_get_extra_scss($theme) { /* fundo do login + settings->scss por ultimo */ }

// Reaproveita o SCSS do Moove e acrescenta os tokens DEPOIS, para vencerem.
function theme_ldg_get_main_scss_content($theme) {
    return theme_moove_get_main_scss_content($theme)
         . file_get_contents($CFG->dirroot . '/theme/ldg/scss/ldg/_tokens.scss')
         . file_get_contents($CFG->dirroot . '/theme/ldg/scss/default.scss');
}
```

`core_renderer` sobrescreve os 4 métodos com `theme_config::load('moove')` hardcoded:
`standard_head_html()`, `get_theme_logo_url()`, `get_theme_logo_dark_url()`,
`body_attributes()`. **Sintoma de esquecer um:** o site fica no ar servindo logo/analytics
do Moove — que nunca configuramos, então parece "sem logo", não "logo errado".

`settings.php` declara **só** o que o filho lê: `logo`, `logodark`, `favicon`, `loginbg`,
`brandcolor` (`#007AFF`), `secondarymenucolor` (`#8E24AA`), `fontsite` (`Inter`),
`forcedarkmode`, `preset`, `presetfiles`, `scsspre`, `scss`, `googleanalytics`, e os 12
campos de footer que o `templates/footer.mustache` do Moove consome. **Não** declara
slider, marketing boxes, numbersfrontpage nem faq — a frontpage vira a landing.

**Edição obrigatória do CI:** substituir o placeholder `# public/theme/coursesfree` por
`public/theme/ldg` em `.github/moodle-plugins.txt` **e** acrescentar em
`.github/workflows/deploy.yml` (no laço do `$EXTRA`, linha ~209) a cópia de
`public/theme/moove` — senão o install do CI monta um filho sem pai. Este é o item com
maior chance de virar deploy vermelho.

### Fase 2 — Design system dark-first e hero no login

Só SCSS, templates e assets.

```
scss/ldg/_navbar.scss       64/50px, #1E1E1E, backdrop blur 12px @85%
scss/ldg/_cards.scss        #1E1E1E, r8, p24, shadow 0 4px 12px rgba(0,0,0,.5)
scss/ldg/_forms.scss        input #121212 / borda #3A3B3C / focus #007AFF + glow 2px
scss/ldg/_courseindex.scss  ativo com borda-esquerda 4px #007AFF
scss/ldg/_progress.scss     trilho #3A3B3C, fill #007AFF
scss/ldg/_login.scss        painel direito legivel sobre o hero
scss/ldg/_landing.scss      grid 12col, gutter 24, max-width 1440
pix/  logo.svg, logo_dark.svg, favicon.ico, login_hero.jpg (bg-hero-medium),
      hero.png (para a landing e og:image)
templates/theme_moove/moove/conectime_*.mustache   dois arquivos vazios
```

`_tokens.scss` define custom properties `--ldg-*` (mesmo padrão do
`scss/moove/darkandlightvariables.scss` do pai). As variáveis Bootstrap (`$brand-primary`,
`$font-family-sans-serif`) são trabalho do `theme_ldg_get_pre_scss`, que roda antes.

**Dark forçado, sem toggle na v1.** O motivo não é estético: o dark do Moove é uma *user
preference* (`dark-mode-on`), e **visitante anônimo não tem user preference** — a landing
e o login, feitos para o design dark, renderizariam claros para 100% do público-alvo.
Então `enabledarkmode = 0`, `data-bs-theme='dark'` fixo no `body_attributes()` do filho, e
um setting de escape `forcedarkmode` (default 1). Efeito colateral bom: o bug do toggle de
dois cliques do Moove fica inalcançável. Custo aceito: o TinyMCE (`editor_scss`) continua
claro — é tela de autoria, não pública.

Os dois `.mustache` vazios em `templates/theme_moove/moove/` matam os banners "Conectime"
do admin renderer do Moove, aproveitando o mecanismo de override do template finder.

Verificar: purgar caches (obrigatório — o SCSS é cacheado por `theme_get_revision()`, que
também entra na URL de `setting_file_url()`). Para iterar, `$CFG->themedesignermode = true`
**só local** — na VPS é compilação inteira por requisição.

### Fase 3 — Planos no banco (`local_marketplace`)

**Onde moram: no núcleo, não no plugin da landing.** O `local_marketplace` já é dono de
`company`, de `commissionpct` e da cadeia de resolução em `classes/api.php:116-136`. Plano é
mais um degrau da mesma cadeia; pôr o degrau num satélite obrigaria o núcleo a
`class_exists()` para calcular dinheiro, e desinstalar a landing deixaria `company.planid`
órfão. A landing só **lê** planos.

```
public/local/marketplace/
  db/install.xml     + local_marketplace_plan, + local_marketplace_plan_tier,
                     + campo planid (nullable) em local_marketplace_company
  db/upgrade.php     tabelas antes do campo que as referencia, + seed idempotente
  db/install.php     local_marketplace_seed_plans()
  classes/plan.php, classes/plan_tier.php     core\persistent, no padrao de company.php
  classes/api.php    resolve_commission_percent ganha o degrau do plano
  classes/company.php  + planid, + get_plan(), + validate_planid()
  classes/form/plan_form.php   moodleform, com as faixas repetidas
  admin/plans.php, admin/plan_edit.php    CRUD, html_table (padrao de admin/companies.php)
  settings.php       + admin_externalpage 'local_marketplace_plans'
  lang/{en,pt_br,es}  strings novas, arquivo REORDENADO alfabeticamente
  tests/plan_test.php, tests/commission_test.php
```

Campos de `local_marketplace_plan`: `shortname` (UNIQUE, chave estável — `starter|pro|scale`;
o `name` muda com o marketing, este não), `name`, `description`, `monthlyfee` (number 10,2),
`country`/`currency` (mesma razão da oferta: valor tem país; plano em outro país é outra
linha), `commissionpct` (number 5,2 **NOT NULL** — ao contrário da empresa: um plano sem
comissão não é um plano), `hostingmodel` (`native|byos`), `ispublic` (aparece na landing —
plano sob medida existe sem estar na vitrine), `status` (`active|archived`; plano nunca é
apagado, há empresa apontando e histórico de comissão dependendo), `sortorder`, campos
padrão de persistent.

`local_marketplace_plan_tier` (trava de resolução por faixa de ticket): `planid`,
`maxprice` (**NULL = última faixa, a sem teto** — guardar um número enorme transformaria
"sem limite" em "limite que alguém escolheu"), `maxresolution` (`720p|1080p|1440p|4k`),
`sortorder`. É tabela e não JSON porque a base não usa JSON em coluna nenhuma, a landing
precisa iterar ordenado, e um dia alguém vai perguntar ao banco qual plano libera 4K.

**Planos são configuráveis, e o seed é só semente.** `local_marketplace_seed_plans()` é
idempotente por `shortname` e **só insere o que não existe — nunca faz update**. Preço muda
por decisão comercial, não por deploy: depois da instalação, tudo se ajusta em
`/local/marketplace/admin/plan_edit.php`. Os valores iniciais podem ser fictícios de
propósito; a tela existe justamente para corrigi-los sem tocar em código.

**Cadeia de comissão nova** (`api::resolve_commission_percent()`):

```
1. course_policy (oferta single, 1 curso)      inalterado
2. company.commissionpct (NULL = nao definido) inalterado, AINDA ganha do plano
3. company.planid -> plan.commissionpct        NOVO
4. defaultfeepercent do site                   inalterado
```

A empresa fica **acima** do plano porque a semântica documentada da coluna é "comissão
negociada com esta empresa", e negociação individual é mais específica que padrão de
classe. O ganho decisivo: toda empresa existente sai do upgrade com `planid = NULL`, o
degrau 3 nunca dispara, e **nenhuma venda de produção muda de valor no dia do deploy** —
o que importa, dado que o split só foi provado uma vez. Consequência a aceitar: pôr uma
empresa no Starter não troca a comissão dela se já havia valor negociado. Mitigação:
coluna "Comissão efetiva" em `admin/companies.php` mostrando **de onde o valor veio**
(`negociada` / `plano Starter` / `padrão do site`).

**Mensalidade não é cobrada agora** (Pagar.me bloqueado por CNPJ). O gancho fica documentado
no docblock de `classes/payment/service_provider.php`: quando destravar, nasce a paymentarea
`'plan'` e uma tabela de assinatura da empresa com `timeend` + aviso de vencimento — sem
débito automático, decisão já fechada.

Verificar: os **63 testes do núcleo têm que passar sem alteração**. Se `commission_test.php`
precisou mudar, a migração não foi neutra e o desenho está errado. Depois,
`/admin/index.php?cache=0` para o `check_database_schema` não reclamar de tabela inesperada.

### Fase 4 — `local_partners`: landing + candidatura, ainda fora da home

A landing existe em `/local/partners/index.php` e funciona; a home do site **não muda**.
Separa "a landing está boa?" de "acoplar a home quebrou algo?".

```
public/local/partners/
  version.php   dependencies ['local_marketplace' => ...]. NAO declara theme_ldg:
                a dependencia e do TEMA para ca, e e opcional
  index.php     landing publica, sem require_login
  apply.php, thanks.php
  db/install.xml   local_partners_application
  db/access.php    local/partners:review (archetypes VAZIO, com o porque escrito)
  db/messages.php  provider 'newapplication'
  db/hooks.php     before_standard_head_html_generation -> SEO (Fase 5)
  classes/{application,api,landing,hook_callbacks}.php
  classes/form/{application_form,approval_form}.php
  classes/output/{landing_page,renderer}.php    renderable + templatable
  classes/privacy/provider.php    guarda nome, e-mail, telefone e IP
  admin/applications.php, admin/application_view.php    html_table
  templates/landing.mustache + templates/landing/{hero,value,plans,how,faq,cta,footer}.mustache
  lang/{en,pt_br,es}/local_partners.php
  tests/{application,approval,cnpj}_test.php
```

Campos de `local_partners_application`: `companyname`, `cnpj`, `contactname`,
`contactemail`, `contactphone`, `website`, `planid` (**intenção, não contrato** — quem grava
o plano na empresa é a aprovação), `message`, `status` (`pending|approved|rejected`; nunca
volta a pending — reenvio é linha nova, para o histórico não sumir), `reviewnote`,
`reviewerid`, `timereviewed`, `companyid` (**é o que torna aprovar idempotente**),
`submitterip` (45 chars, cabe IPv6; serve ao limite de taxa e sai no privacy provider).
Índice `submitterip-timecreated` — sem ele a checagem de rate limit varre a tabela.

`planid` **sem FK declarada**: a FK do XMLDB é documental e apontar para tabela de outro
plugin faz o `check_database_schema` reclamar. A integridade vem do `validate_planid()` do
persistent.

**`classes/cnpj.php` vai para `local_marketplace`, não para cá.** A coluna
`company.cnpj` existe desde sempre e **não tem validador** (`company.php` declara
`PARAM_ALPHANUM` e para por aí). O dono do campo é quem valida:
`\local_marketplace\cnpj::is_valid()` (módulo 11), `company::validate_cnpj()` passa a usá-lo,
e `local_partners` reusa. Fecha um buraco real de graça.

**Anti-spam em três camadas**, da mais confiável à mais frágil — porque o usuário não gosta
de proteção que dependa de configuração estar certa:

1. **Sempre ligada** — limite de taxa por IP/hora (`local_partners/maxperhour`, default 3)
   e recusa de `pending` duplicada com mesmo CNPJ/e-mail. Não depende de nada externo.
2. **Sempre ligado** — honeypot: campo escondido por CSS; preenchido = grava nada e mostra
   a página de obrigado (não dá pista ao bot).
3. **Quando configurado** — reCAPTCHA, no idioma exato do core (`public/login/signup_form.php:97`),
   ativo só com `$CFG->recaptchapublickey`. O `settings.php` diz explicitamente que sem
   chave só as camadas 1 e 2 valem.

`db/messages.php` notifica quem tem `local/partners:review`. **Armadilha:**
`MESSAGE_DEFAULT_LOGGEDIN` não existe no 5.2 e derruba o upgrade — usar
`MESSAGE_DEFAULT_ENABLED`.

**Mustache aqui é a primeira vez no projeto e precisa de justificativa.** As telas
existentes são tabela ou formulário, e para isso `html_table` é genuinamente melhor. A
landing são 7 seções de marketing com repetição aninhada (planos × features, FAQ, cards) —
em `html_writer` vira PHP ilegível. Mitigação: templates **burros** (só seções e loops, zero
lógica, strings via `{{#str}}`), todo dado preparado pelo `templatable`. A **fila de
aprovação continua em `html_table`**, consistente com `admin/companies.php`. A exceção é a
landing, não o plugin.

### Fase 5 — Landing vira a home + SEO

Duas coisas pequenas e de risco alto.

**Como a home é trocada.** Rejeitadas: `$CFG->defaulthomepage` (só escolhe destino de
usuário **logado**, não afeta anônimo) e redirect por hook (302 na URL canônica em toda
visita anônima, home da marca passa a viver em `/local/partners/index.php`, risco de loop,
e **sequestraria o `/` dos domínios de vendedor**). Escolhida: `theme_ldg` sobrescreve
`layout/frontpage.php` — `/` continua `/`, uma requisição, HTTP 200, e a decisão "quem é
anônimo" fica onde o Moove já a toma (`layout/frontpage.php:117-121`).

```php
// public/theme/ldg/layout/frontpage.php — copia do Moove ate a montagem do contexto.
if (!isloggedin() || isguestuser()) {
    // A landing e da PLATAFORMA. Num dominio de vendedor seria o pitch errado
    // para o publico errado. $CFG->marketplacecompany e a mesma marca que o
    // local_marketplace\hook_callbacks::after_config ja usa.
    $isplatform = empty($CFG->marketplacecompany);

    // Dependencia OPCIONAL, por class_exists e nunca por get_config ou $DB:
    // o moodle-plugin-ci instala este tema SEM o local_partners.
    $haslanding = class_exists('\local_partners\landing') && \local_partners\landing::is_enabled();

    if ($isplatform && $haslanding) {
        $templatecontext['landing'] = \local_partners\landing::render($OUTPUT);
        $template = 'theme_ldg/landing';
    } else {
        // Sem o plugin, cai no marketing do proprio Moove: o site nao fica sem home.
        $templatecontext = array_merge($templatecontext, (new \theme_moove\util\settings())->frontpage());
        $template = 'theme_moove/frontpage';
    }
}
```

Contrato de acoplamento: `local_partners` expõe **uma** superfície pública —
`landing::is_enabled()`, `landing::render()`, `landing::head_html()`. O tema é dono do
chrome (`templates/landing.mustache` = navbar + `{{{landing}}}` + footer), o plugin é dono
do miolo, e nenhum dos dois conhece as tabelas do outro. O tema **não** declara
`local_partners` em `$plugin->dependencies`.

**SEO.** Hook `\core\hook\output\before_standard_head_html_generation` no `local_partners`.
Ele dispara em **toda** página do site, então as saídas antecipadas vêm primeiro e baratas
(logado → sai; domínio de vendedor → sai; path diferente de `/` e da URL da landing →
sai). Sem esse filtro, compartilhar um curso no Facebook mostraria o pitch de parceria.
Emite `og:*`, `twitter:card`, `canonical` e `meta description`, tudo por `s()`/`format_string()`.

Verificar: `/` deslogado mostra a landing e logado mostra o dashboard; `view-source:` na raiz
tem as `og:` e **uma** canonical, e num curso **não** tem; `curl` com `Host:` de domínio de
vendedor na raiz não mostra a landing; desinstalar `local_partners` num ambiente de teste e
conferir que `/` volta ao marketing do Moove sem erro.

### Fase 6 — Aprovação provisiona a empresa

`api::approve()` **não** cria categoria, papel nem membro — chama
`\local_marketplace\api::create_company()`, que já faz tudo isso. A aprovação valida o
estado, chama, grava `planid` na empresa, marca `companyid` na candidatura, muda o status e
notifica.

Três decisões dentro dela:

1. **O `shortname` não vem do formulário público.** Ele vai para a URL e para a categoria —
   objeto global. A tela de aprovação é um `moodleform` pré-preenchido (nome, CNPJ,
   shortname *sugerido* por slug, plano escolhido) e o humano confirma. É exatamente o que
   a decisão "sem auto-atendimento" pede.
2. **Dono da empresa.** `create_company()` exige `$ownerid` e o contato pode não ter conta.
   V1: seletor de usuário pré-filtrado por `contactemail`; se não existir, o form recusa e
   linka `/admin/user.php`. Criar usuário a partir de formulário público é `RISK_SPAM` e não
   vale na v1 — melhoria anotada no docblock: convite por e-mail com token.
3. **Idempotência.** Checar `status !== pending` e lançar exceção **antes** de qualquer
   escrita; `companyid` gravado depois. Duas submissões não podem produzir duas categorias.

A empresa nova sai com `commissionpct = NULL`, para que o plano governe — preencher com o
valor do plano congelaria o número e a empresa pararia de acompanhar mudanças de plano.

---

## Armadilhas a não repetir

- **`$THEME->name` tem de bater com o diretório**, senão `layout_file()` procura no lugar errado.
- **Nunca criar `public/theme/ldg/scss/moove/`** — sombreia o pai silenciosamente.
- **Toda vez que o filho declarar um setting com nome igual a um do Moove**, perguntar "o
  callback dele vai reagir a isso?". É a origem do `loginbg` vs `loginbgimg`.
- **Purgar caches em todo teste visual.** Logo trocada sem purge continua servindo a antiga.
- **phpcs: ler o TOTAL** (`grep "A TOTAL OF"`), nunca `tail -3` — já custou um CI reprovado.
  `--max-warnings 0`: warning reprova.
- **Strings de idioma em ordem alfabética.** Inserir por âncora quebra o phpcs: reordenar o
  arquivo inteiro e conferir paridade exata entre `en`, `pt_br` e `es`.
- **`PATH` do `install.xml` é `local/partners/db`**, sem o `public/` — igual ao do marketplace.
- **Dinheiro é `TYPE="number"` com `DECIMALS`**, nunca float.
- Comentários e commits em **português sem acentos**; docs `.md` com acentos normais.

## Verificação

```bash
# phpcs — LEIA O TOTAL
docker exec -u 1000:33 courses-free-moodle-1 sh -c \
  'cd /tmp/cs && ./vendor/bin/phpcs --standard=moodle --report=summary <caminho>' \
  | grep -E "A TOTAL OF"

# phpunit
docker exec -u 1000:33 -e COMPOSER_HOME=/tmp/composer courses-free-moodle-1 \
  php /var/www/html/public/admin/tool/phpunit/cli/init.php
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  php vendor/bin/phpunit --testsuite local_marketplace_testsuite
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  php vendor/bin/phpunit --testsuite local_partners_testsuite
```

Por fase, no navegador: **F1** tema instala, é selecionável e o site fica idêntico ao Moove
(se algo quebrar, o problema é `parents` ou `rendererfactory`, não o design) · **F2** DevTools
confere navbar 64px, card r8, foco com glow, progress azul, course index com borda esquerda,
login com hero legível · **F3** upgrade limpo, `?cache=0` sem "table is not expected", 63
testes do núcleo intactos, CRUD de planos editando preço · **F4** landing renderiza deslogada,
candidatura grava e notifica, 4ª submissão bate no rate limit, home intocada · **F5** e **F6**
como descrito acima.

## Ordem e paralelismo

| Fase | Entrega | Rollback |
|---|---|---|
| 0 | `docs/private/` + `.gitignore` + assets no git | reverter o commit |
| 1 | `theme_ldg` instalável = Moove; CI ajustado | trocar o tema de volta |
| 2 | Design dark, hero no login | idem |
| 3 | Planos + `company.planid` + cadeia de comissão | upgrade é aditivo |
| 4 | `local_partners` com landing em URL própria | desinstalar o plugin |
| 5 | Landing vira a home + SEO | remover `layout/frontpage.php` do tema |
| 6 | Aprovação provisiona empresa | fila fica sem botão de aprovar |

1→2 e 3 são independentes. 4 depende de 3 (a landing exibe planos). 5 depende de 1 e 4.
6 depende de 3 e 4.

## Fora de escopo desta sessão

Formato de curso estilo Udemy, player de vídeo com iframe, integração Bunny/Cloudflare,
customização de e-mail por empresa, dashboard por perfil, cobrança de mensalidade
(bloqueada pelo CNPJ/Pagar.me), e a refação da marca quando existir nome fantasia.
