# mod_ldgvideo e os papéis de empresa — a fronteira do plano Free, nas duas camadas

> **Situação:** executado · **Pedido em:** 2026-09-04 · **Concluído em:** 2026-09-04
> **Nasce de:** [`2026-09-03-mod-video-youtube.md`](2026-09-03-mod-video-youtube.md), que era o recorte, não o plano.
> **O que o plano errou está no fim**, em "O que a execução mudou" — três
> afirmações daqui não sobreviveram ao código.

## Contexto

Hoje uma aula em vídeo é um `mod_page` com o iframe do YouTube colado na
descrição. Funciona, e é exatamente o problema: o vídeo não é um campo — é HTML
livre do professor, sem validação de endereço, sem proporção, sem nada que o
resto do sistema consiga ler. Colide de frente com a regra do `CLAUDE.md`
("campos, não HTML livre, na vitrine") e com o desenho do portal do aluno.

O plugin **é a implementação do plano Free** (`docs/private/planos.md`): R$ 0,
0% de comissão, **hospedagem externa por embed**, custo de infraestrutura zero.
O vídeo do plano pago (Bunny, com a trava de resolução da
[ADR-0005](../adr/0005-trava-de-resolucao-por-ticket.md)) é **outro plugin**, e
`plan::max_resolution_for()` **não é consumida aqui** — o YouTube nunca suportou
fixar resolução pela URL do embed, e a trava só faz sentido onde nós hospedamos
o arquivo.

O resultado esperado: o professor cola o que a plataforma de vídeo deu a ele —
inclusive o trecho `<iframe>` inteiro —, a nossa plataforma extrai o endereço,
joga fora o HTML, e desenha um vídeo que se comporta na grade do portal: em
desktop, em celular, e com as laterais abertas ou escondidas.

**São duas camadas, e uma sem a outra não fecha.** O campo recusar a URL do
próprio site é validação de formulário — protege contra o engano, não contra a
intenção. Quem tranca de verdade é o **papel**: se o vendedor não consegue pôr
arquivo no `moodledata`, não existe arquivo local para ele apontar. Esse papel já
existe e está incompleto, e a
[segunda metade deste plano](#a-segunda-camada-os-papéis-de-empresa) o fecha.

### As decisões desta sessão

| Decisão | Por quê |
|---|---|
| Componente **`mod_ldgvideo`** | `mod_video` está livre no diretório hoje (conferido na API: `mod_videotime` responde, `mod_video` volta vazio), mas é genérico e um terceiro pode publicá-lo. Os dois não coexistiriam, e renomear depois é migração de tabela. **O professor nunca vê o componente** — o rótulo é string de idioma, e continua "Vídeo" |
| **Não é só YouTube** | O plano Free já dizia "YouTube/Vimeo por embed". O plugin aceita toda plataforma que o site souber embutir |
| **Quem reconhece o endereço é o core** | Ver [a seção seguinte](#o-core-já-faz-metade-disso) |
| Fronteira: **menos o próprio site** | Recusa URL do nosso `wwwroot`. É a guarda de margem: o plano existe para custar zero de banda |
| **Nenhum botão de expandir no vídeo** | O portal já tem os botões de esconder as laterais no cartão do aluno (`format_ldg/amd/src/aside.js`). Com largura fluida, esconder a lateral já alarga o vídeo sozinho |
| Conclusão manual **semeada na instalação** | Não é `settings.php` — ver [A conclusão](#a-conclusão-não-é-settingsphp) |
| `format_ldg` e `theme_ldg` **não mudam** | Ver [a pergunta que o documento de origem deixou aberta](#a-pergunta-que-o-documento-de-origem-deixou-aberta) |

## O core já faz metade disso

`public/media/classes/manager.php` é o subsistema de mídia do Moodle, e o site
já traz cinco players: `youtube`, `vimeo`, `videojs`, `html5video`,
`html5audio`. Dois métodos resolvem o que eu ia escrever à mão:

| Método | O que entrega |
|---|---|
| `can_embed_url(moodle_url $url)` | *este endereço é vídeo que sabemos embutir?* — cada player traz o próprio regex de reconhecimento |
| `embed_url(moodle_url $url, $name, $w, $h)` | o HTML do player, pronto |

O `media_youtube` já cobre `watch?v=`, `youtu.be`, `embed/`, `shorts/`,
playlist e tempo de início, e tem **configuração `nocookie` própria**, no admin
do core. Reescrever isso seria construir a segunda fonte de verdade do mesmo
regex.

**Ganho que só aparece depois:** instalar um player novo no site — Dailymotion,
Loom — passa a valer para o `mod_ldgvideo` sem uma linha de código nossa. E quem
liga e desliga plataforma é a tela de admin que já existe (*Plugins → Players de
mídia*), não uma configuração que eu inventaria.

**O que o core erra, e é justamente o pedido:**
`media/player/youtube/templates/embed.mustache` emite
`width="{{width}}" height="{{height}}"` **em pixels fixos**, dentro de um
`<span class="mediaplugin mediaplugin_youtube">`. É o `560x315` do problema, com
outro nome. Consertar isso é CSS nosso, e é a parte que este plugin de fato
constrói.

## O esqueleto: cópia do `mod_page`, com poda

`public/mod/page/` → `public/mod/ldgvideo/`, e daí:

**Renomear em tudo:** `page` → `ldgvideo`, `mod_page` → `mod_ldgvideo`,
`page_supports()` → `ldgvideo_supports()`, `lang/en/page.php` →
`lang/en/ldgvideo.php`. Autoria **LeoDG `<callme@leodg.dev>`, 2026** em todo
cabeçalho — o GPL do core fica, a linha de `@copyright`/`@author` é que muda.

**Podar, porque não faz sentido em vídeo:**

- `backup/moodle1/` — Moodle 1.9 não tem o que restaurar aqui
- `classes/analytics/indicator/` — os indicadores do `page` medem conteúdo próprio
- os campos `content`, `contentformat`, `legacyfiles`, `legacyfileslast`, `revision` — sem conteúdo HTML não há área de arquivo, revisão de cache nem migração de legado
- `RESOURCELIB_DISPLAY_POPUP`, `popupwidth`, `popupheight` — popup de tamanho fixo é o oposto do pedido

**Manter, com nome trocado:** `lib.php`, `view.php`, `index.php`, `mod_form.php`,
`db/access.php`, `db/upgrade.php`, `db/services.php` + `classes/external.php`
(a chamada `_view_` que marca a visualização), `classes/privacy/provider.php`
(`null_provider`, como o do `page` — o módulo não guarda dado pessoal),
`classes/search/activity.php` (indexando `intro`), os dois eventos,
`backup/moodle2/`, `tests/generator/lib.php`.

`ldgvideo_supports()` fica igual ao do `page`: `MOD_ARCHETYPE_RESOURCE`,
`MOD_PURPOSE_CONTENT`, `FEATURE_MOD_INTRO`, `FEATURE_COMPLETION_TRACKS_VIEWS`,
`FEATURE_SHOW_DESCRIPTION`, `FEATURE_BACKUP_MOODLE2`, sem nota e sem grupos.

### A tabela

`db/install.xml`, tabela `ldgvideo` — o `page` menos o conteúdo, mais o vídeo:

| Campo | Tipo | Papel |
|---|---|---|
| `id`, `course`, `name`, `intro`, `introformat`, `timemodified` | como no `page` | |
| `videourl` | char(1333), notnull | **a fonte da verdade.** A URL canônica, normalizada |
| `aspectratio` | char(8), notnull, default `16:9` | `16:9`, `9:16` ou `4:3` |
| `displayoptions` | text | serializado, como no `page` — guarda o `printintro` |

Índice em `course`, como no original.

> **Não há campo `provider` nem `videoid`, e é de propósito.** Quem resolve o
> endereço é o core, no momento de desenhar. Guardar o player escolhido
> congelaria a decisão de hoje: um vídeo salvo antes de o site ganhar um player
> novo continuaria preso ao antigo.

## O campo de vídeo: extrair, não avisar

O professor vai colar o que o botão *Compartilhar → Incorporar* deu a ele, e
isso é o trecho inteiro:

```html
<iframe width="560" height="315" src="https://www.youtube.com/embed/d2bq9QW7fZg?si=_yAd63h_wkBsRARU" ...></iframe>
```

**O campo aceita isso e resolve.** Avisar "não cole assim" transfere ao
professor um trabalho que a máquina faz melhor — e, se ele colar mesmo assim, o
aviso não impediu nada.

`classes/url.php`, sem estado e sem dependência de plataforma:

```php
public static function normalize(string $entrada): ?array
```

Devolve `['url' => moodle_url, 'ratio' => '16:9'|'9:16'|'4:3'|null]`, ou `null`:

1. Se o texto contém `<iframe`, extrai o `src` — e **lê `width`/`height` só para
   deduzir a proporção** (`560×315` ≈ 16:9), descartando os pixels
2. Senão, tenta o texto como URL
3. Tira os parâmetros de rastreio (`si`, `pp`, `feature`) — não são guardados

Não há regex de YouTube aqui. Reconhecer *qual* plataforma é do core.

`mod_form::validation()`, dois portões, nesta ordem:

```php
$dados = \mod_ldgvideo\url::normalize($data['videourl']);
if ($dados === null || !\core_media_manager::instance()->can_embed_url($dados['url'])) {
    $errors['videourl'] = get_string('erroraddressnotvideo', 'ldgvideo');
} else if (str_starts_with($dados['url']->out(false), $CFG->wwwroot)) {
    $errors['videourl'] = get_string('errorselfhosted', 'ldgvideo');
}
```

O segundo portão é regra de negócio, não paranoia: o professor pode subir um
`.mp4` num rótulo do curso, copiar o link do `pluginfile.php` e colar aqui —
vídeo servido pela **nossa** banda, no plano que existe para custar zero.

E o portão que fecha o XSS entre inquilinos é o primeiro: **o HTML colado nunca
é armazenado nem ecoado**, só a URL sobrevive.

> **Deduzir a proporção do trecho colado é conveniência, não regra.** O campo
> `aspectratio` continua editável e vence. Um Short colado como
> `<iframe width="315" height="560">` já chega com 9:16 selecionado.

## O comportamento: proporção manda, pixel não existe

Este é o pedido central, e a resposta é CSS em vez de detecção de dispositivo.

`view.php` chama `core_media_manager::instance()->embed_url()` e envolve a saída
numa caixa de proporção. A proporção da instância vira **classe**
(`ldgvideo--16-9`, `--9-16`, `--4-3`), nunca `style` inline:

```css
.ldgvideo__frame { width: 100%; aspect-ratio: 16 / 9; }

/* O core emite a midia dentro de um <span>, que e inline: sem isto a caixa
   nao tem altura para dar. E os width/height em pixel vem como ATRIBUTO do
   iframe, entao o CSS ganha deles sem !important. */
.ldgvideo__frame .mediaplugin { display: block; width: 100%; height: 100%; }
.ldgvideo__frame iframe,
.ldgvideo__frame video,
.ldgvideo__frame .video-js { width: 100%; height: 100%; border: 0; }
```

O `video`/`.video-js` está aí porque o `media_videojs` desenha um `<video>`, e
não um iframe — a regra tem que cobrir os dois, senão a plataforma que cair no
VideoJS ignora a caixa.

Isso resolve os três casos do pedido **sem uma linha de JavaScript**:

| Situação | O que acontece |
|---|---|
| Desktop, laterais abertas | a coluna do miolo é `minmax(0,1fr)` entre laterais de 280px e 360px → o vídeo ocupa o que sobra |
| Desktop, laterais escondidas | `.ldg-portal--hide-nav.ldg-portal--hide-index` colapsa a grade para uma coluna (`format/ldg/styles.css:118`) → o vídeo alarga sozinho |
| Celular | grade de uma coluna a partir de `format/ldg/styles.css:47` → largura total, altura proporcional |

### Duas armadilhas do encaixe no portal

**Nada de `vh` dentro da página da atividade.** O `player.js` do `format_ldg`
**encolhe o quadro para zero antes de medir** a altura (`amd/src/player.js`,
`resize()`) — foi assim que ele saiu de um ciclo de realimentação que levou o
quadro de 420px a 12084px em 02/09/2026. Com o quadro em zero, qualquer `100vh`
lá dentro vira zero e o vídeo some. `aspect-ratio` deriva da **largura**, e por
isso é imune.

**Tela cheia depende de dois iframes.** O do YouTube fica dentro do nosso, que
fica dentro do quadro do portal. O do portal já tem `allowfullscreen`
(`format/ldg/templates/local/content/lessonviewer.mustache`), e o do core traz
`allow="fullscreen"` — **confirmar no navegador**, porque a política de permissão
não é herdada por padrão e o sintoma é um botão que não faz nada.

## A pergunta que o documento de origem deixou aberta

> *"Decidir no plano como o formato de curso descobre o tipo de vídeo."*

**Ele não descobre, e não precisa.** `catalog::classify()`
(`public/course/format/ldg/classes/catalog.php:114`) manda para Aulas tudo que
não é `forum`, `customcert`, `resource`, `folder` ou `url` — então
`mod_ldgvideo` cai em Aulas sem uma linha de mudança, e o plugin do Bunny
também cairá, no dia em que existir.

E o 16:9 **não vai para o `format_ldg`**. O `styles.css:250` deixou o recado —
*"Os 16/9 do desenho chegam com o mod_video"* — mas ele chega **dentro da página
da atividade**, não no quadro do portal: o quadro continua medindo, porque
também carrega quiz e tarefa, que não têm cara de vídeo.

**`format_ldg` e `theme_ldg` não recebem alteração neste plano.**

## A conclusão não é `settings.php`

O pedido é que a atividade nasça com *"Alunos devem marcar manualmente como
feito"*. Isso **não é configuração de plugin**:
`\core_completion\manager::get_default_completion()`
(`public/completion/classes/manager.php:550`) lê da tabela
`course_completion_defaults` e, sem linha lá, devolve `COMPLETION_TRACKING_NONE`.
Quem aplica é `public/course/modlib.php:975`, ao montar o formulário.

Então `db/install.php` semeia a linha para o curso do site:

```php
function xmldb_ldgvideo_install() {
    global $DB;
    $moduleid = $DB->get_field('modules', 'id', ['name' => 'ldgvideo'], MUST_EXIST);
    $DB->insert_record('course_completion_defaults', (object) [
        'course'     => SITEID,
        'module'     => $moduleid,
        'completion' => COMPLETION_TRACKING_MANUAL,
    ]);
}
```

Seguro na ordem: `upgrade_plugins_modules()` insere o registro em `modules`
**antes** de chamar o hook — `public/lib/upgradelib.php:96`, com o comentário do
core dizendo literalmente *"may be needed in install.php already"*.

Continua sendo **default**, e não trava: o admin muda em Padrões de conclusão, o
professor muda na atividade.

## As configurações do plugin

`settings.php`, enxuto — o do `page` menos o popup:

| Configuração | Padrão | Nota |
|---|---|---|
| `ldgvideo/printintro` | **1 (marcado)** | é o "Display page description" pedido. No `mod_page` o padrão é `0` |
| `ldgvideo/aspectratio` | `16:9` | padrão do site para atividades novas |

**Nada de configuração de plataforma.** Quem liga o YouTube ou o Vimeo é
*Plugins → Players de mídia*, do core. O README aponta para lá, e recomenda ligar
o `nocookie` do `media_youtube` — o domínio normal grava rastreio antes de o
aluno apertar play, e a troca não custa nada.

## O ícone

`pix/monologo.svg` substituído por um play. **Monocromático de verdade**:
`viewBox="0 0 24 24"`, `fill="currentColor"`, sem cor literal — o Moodle tinge o
monologo pela cor do `MOD_PURPOSE_CONTENT`, e um SVG com cor fixa ignora o tema e
destoa dos vizinhos no seletor de atividades.

## A segunda camada: os papéis de empresa

### O que já existe, e o que está aberto

`local_marketplace/db/install.php:46` cria o papel **`marketplaceseller`**, em
`CONTEXT_COURSECAT`, e o comentário dele já enunciou a regra melhor do que eu
enunciaria: *"a regra de negócio 'vídeo tem que ficar fora da plataforma' NÃO é
uma capability própria: é a AUSÊNCIA das capabilities que colocam arquivo no
moodledata"*. Usa `CAP_PROHIBIT` e não `CAP_PREVENT` porque o papel de usuário
autenticado permite o upload, e no Moodle o `ALLOW` vence o `PREVENT` — só o
`PROHIBIT` não é sobreponível por papel nenhum.

Está certo. Está incompleto:

| Buraco | Consequência |
|---|---|
| **A lista tranca 2 de 17 repositórios** | `dropbox`, `googledocs`, `onedrive`, `nextcloud`, `webdav`, `s3`, `filesystem` e `user` (arquivos privados) continuam colocando arquivo no `moodledata` |
| **`moodle/restore:uploadfile` não é proibida** | um `.mbz` restaurado leva vídeo dentro, e passa por fora do seletor de arquivos |
| **`db/upgrade.php` nunca tocou no papel** | o `install.php` só roda em instalação nova. Mexer na lista sem passo de upgrade **não muda nada no que está no ar** |
| **Nenhum teste** | é a regra que sustenta a margem do plano Free, e nada verifica que ela continua valendo depois de um upgrade do Moodle |
| **`owner` e `seller` são o mesmo papel** | quem só monta curso também mexe na credencial financeira da empresa |

### Dois papéis, com a mesma proibição

`marketplaceseller` passa a ser o **editor**, e nasce o **`marketplacemanager`**
para o `owner`. A divisão é por risco: o editor monta curso, o gerente mexe em
dinheiro e em gente.

| Capability | `marketplacemanager` | `marketplaceseller` |
|---|---|---|
| `moodle/course:create`, `:update`, `:manageactivities`, `:visibility`, `:viewhiddencourses` | ✅ | ✅ |
| `moodle/category:viewcourselist`, `local/marketplace:publishcourse` | ✅ | ✅ |
| `local/marketplace:managecompany` | ✅ | — |
| `local/marketplace:managepayment` | ✅ | — |
| `local/marketplace:viewreport` | ✅ | — |
| `moodle/role:assign` | ✅ | — |
| `enrol/fee:config`, `enrol/manual:enrol`, `moodle/course:enrolreview` | ✅ | — |

O gerente é o editor **mais** a coluna comercial — nenhuma capability sai do
conjunto que o `marketplaceseller` tem hoje, ela só muda de dono.

> **`moodle/role:assign` fica só com o gerente, e isso importa.** Quem pode
> atribuir papel pode tentar se dar um que permita upload. Não funciona — o
> `PROHIBIT` não é sobreponível —, mas dar a capability a quem não precisa dela
> é convite para descobrir isso na tentativa.

### A lista de proibição, fechada de verdade

Aplicada **aos dois papéis**, por uma constante única em
`classes/roles.php` — duas listas que precisam ficar iguais viram duas listas
que divergem:

```php
public const PROHIBIT = [
    // Todo caminho conhecido para colocar arquivo no moodledata.
    'repository/upload:view',
    'repository/url:view',
    'repository/user:view',
    'repository/dropbox:view',
    'repository/googledocs:view',
    'repository/onedrive:view',
    'repository/nextcloud:view',
    'repository/webdav:view',
    'repository/s3:view',
    'repository/filesystem:view',
    'repository/coursefiles:view',
    'repository/recent:view',
    'moodle/user:manageownfiles',
    'moodle/restore:uploadfile',
    'moodle/course:ignorefilesizelimits',
];
```

`repository/youtube:view` **fica de fora de propósito**: aquele repositório
devolve link externo, que é exatamente o que se quer.

**Efeito colateral que se aceita, e que o README precisa dizer:** o vendedor
também não envia imagem de capa pelo seletor de arquivos — usa URL externa. E no
portal do aluno, o destino **Material de apoio** fica valendo só para `mod_url`:
`mod_resource` e `mod_folder` continuam podendo ser criados, e ficam vazios. Isso
já era verdade antes deste plano; passa a estar escrito.

### O que a lista **não** cobre, e por que não se persegue

Um administrador pode habilitar um repositório novo amanhã, e ele nasce fora da
lista. A resposta **não** é código que lê a configuração e proíbe dinamicamente:
segurança que depende de a configuração estar certa não é segurança.

A resposta é a lista estática ser o guarda, e o **`cli/status.php` virar o
relator**. Ele já reporta o papel e a contagem de capabilities
(`cli/status.php:70`); ganha uma linha que compara os repositórios **habilitados**
com a lista de proibição e grita o que sobrou:

```
  papel de gerente ..................... sim (24 capabilities)
  papel de editor ...................... sim (17 capabilities)
  repositorios sem proibicao ........... NENHUM
```

Quando alguém habilitar o Dropbox, a linha passa a dizer `dropbox  <-- caminho
aberto para o moodledata`. Não conserta sozinho, e é assim de propósito.

### As mudanças de código

| Arquivo | O quê |
|---|---|
| `classes/roles.php` | **nasce.** As duas listas de `ALLOW`, a lista de `PROHIBIT`, os dois `shortname`, e `ensure()` — cria/atualiza os dois papéis, idempotente. `install.php` e `upgrade.php` chamam o mesmo método |
| `db/install.php` | a criação inline sai e vira uma chamada a `roles::ensure()` |
| `db/upgrade.php` | **passo novo:** `roles::ensure()`, e depois a migração de quem já é `owner` — `role_unassign` do `seller` e `role_assign` do `manager`, no contexto da categoria de cada empresa |
| `classes/api.php:613` | `assign_seller_role()` vira `assign_member_role(company, userid, memberrole)`, escolhendo o papel pelo `memberrole` |
| `classes/api.php:715` | `remove_member()` hoje desatribui só o `seller`; passa a desatribuir **os dois** — senão trocar de papel deixa o antigo grudado |
| **todo ponto que grava `memberrole`** | trocar de `owner` para `seller` (e o contrário) tem que **trocar o papel junto**. Localizar antes de mexer: o vínculo são duas coisas inseparáveis, como o `guia-desenvolvedor.md:122` já avisa |
| `cli/status.php` | as duas linhas de papel e a de repositórios abertos |
| `db/access.php` | sem mudança — as capabilities já existem |
| `lang/{en,pt_br,es}` | `managerrole`, `managerroledesc`, e o texto do `sellerrole` revisado para "editor" |

> **A migração roda uma vez e tem que ser idempotente.** Se o upgrade morrer no
> meio, rodar de novo não pode duplicar atribuição nem derrubar quem já foi
> migrado. `role_assign()` é seguro em repetição; a desatribuição precisa ser
> condicionada a o vínculo ser `owner`.

### Os testes do papel

`tests/roles_test.php` — o arquivo que hoje não existe, e é o mais importante
dos dois plugins:

| Caso | O que prova |
|---|---|
| `ensure()` cria os dois papéis, em `CONTEXT_COURSECAT` | a fundação |
| `ensure()` roda duas vezes sem duplicar | idempotência, que o upgrade depende |
| Toda capability da `PROHIBIT` está em `CAP_PROHIBIT` **nos dois papéis** | é o teste que a regra de negócio nunca teve |
| Um usuário com o papel de editor **não** tem `repository/upload:view` no contexto da empresa, **mesmo estando** no papel de usuário autenticado | prova o `PROHIBIT` contra o `ALLOW`, que é a razão de a escolha ser `PROHIBIT` |
| O editor **não** tem `local/marketplace:managepayment`; o gerente tem | a separação |
| Migrar um `owner` troca o papel, e rodar a migração de novo não muda nada | o passo de upgrade |
| `remove_member()` tira os dois papéis | o vínculo desfeito por inteiro |

E um caso de Behat, `@local_marketplace`: um vendedor logado abre o formulário
de uma atividade e **o seletor de arquivos não oferece "Enviar um arquivo"** — a
prova na tela, que nenhuma asserção de capability substitui.

## Código que muda fora do plugin

Um arquivo só: **`.github/moodle-plugins.txt`** ganha a linha
`public/mod/ldgvideo`. Sem ela o plugin não passa pelo portão que bloqueia o
deploy — foi o que aconteceu com o `format_ldg` até 03/09/2026.

## Documentação

Plugin novo não termina no `version.php`. Nesta base a documentação é parte da
entrega, e a lista abaixo é o que fica **desatualizado** no dia em que o plugin
existir.

### Nasce

**`public/mod/ldgvideo/README.md`**, no molde do
[`format_ldg`](../../public/course/format/ldg/README.md): para que serve, do que
depende, o que precisa ser configurado, e **as armadilhas** — o `vh` que o
`player.js` zera, os dois `allowfullscreen`, e o fato de que quem liga uma
plataforma nova é a tela de *Players de mídia* do core, não uma configuração
deste plugin.

**`docs/adr/0008-embed-multiplataforma-pelo-core.md`**, a partir do
`0000-template.md`, situação **Aceita**. A decisão tem alternativa real e
consequência que sobrevive à sessão, que é o critério de ADR aqui:

- *Contexto:* o plano Free é embed externo, e o core já traz cinco players com o regex de reconhecimento de cada plataforma
- *Decisão:* delegar reconhecimento e embed ao `core_media_manager`; guardar URL, não `videoid`/`provider`; a fronteira é **qualquer player habilitado, menos o próprio `wwwroot`**
- *Alternativas:* regex próprio de YouTube (segunda fonte de verdade do mesmo padrão); lista fechada YouTube+Vimeo (cada plataforma nova vira código e deploy); sem guarda de `wwwroot` (o professor cola um `pluginfile.php` e serve vídeo pela nossa banda no plano de 0%)
- *Consequência:* o conjunto de plataformas aceitas passa a ser **configuração de site**, não código. Desligar um player de mídia quebra as atividades que dependiam dele, **em silêncio** — é o preço, e é o que a seção "Como saber que erramos" registra
- *Relação:* a [ADR-0005](../adr/0005-trava-de-resolucao-por-ticket.md) **não se aplica** aqui, e a ADR nova diz isso explicitamente para a próxima sessão não tentar ligar as duas

**`docs/adr/0009-papeis-de-empresa-sem-upload.md`**, situação **Aceita**. É
decisão separada da anterior porque tem outra consequência e outro modo de
falhar: por que a fronteira é `CAP_PROHIBIT` numa lista estática, e não uma
capability própria nem leitura dinâmica dos repositórios habilitados; por que
`owner` e `seller` deixam de compartilhar papel; e o sinal de erro — um vendedor
conseguir subir arquivo, ou o `cli/status.php` reportar repositório sem
proibição. Boa parte do texto já está escrita no docblock de
`db/install.php:29`, que hoje é o único lugar onde essa decisão vive.

### Muda

| Arquivo | O que fica errado sem mexer |
|---|---|
| **`CLAUDE.md`** | diz "**Seis plugins**" e lista seis. Passa a sete, com uma linha para o `mod_ldgvideo`. Em "Estado atual", a contagem de testes. E "Armadilhas do Moodle nesta base" ganha a linha do `vh` dentro do quadro do portal |
| **`docs/README.md`** | a tabela "Os READMEs dos plugins" ganha a linha do `mod_ldgvideo` — **e a do `format_ldg`, que tem README desde 03/09 e nunca foi listada** |
| **`docs/dev/guia-desenvolvedor.md`** | a seção "Plugins" e "Tabelas, campo a campo" ganham o `ldgvideo`. E a linha 120 diz *"`owner` ou `seller`. **As capabilities são as mesmas**"* — deixa de ser verdade. A linha 122, sobre o vínculo ser duas coisas inseparáveis, ganha **qual** papel |
| **`public/local/marketplace/README.md`** | os dois papéis, a lista de proibição e o efeito colateral: sem seletor de arquivos, a capa vem de URL externa e o "Material de apoio" do portal fica valendo só para `mod_url` |
| **`docs/data-model/`** | a tabela nova, com os quatro campos e por que não há `provider` nem `videoid` |
| **`docs/architecture/estado-e-proximas-fases.md`** | o plano Free deixa de ser promessa e passa a ter peça no ar |
| **`docs/legal/mapa-de-dados-pessoais.md`** | ver abaixo — **é o item que não pode ser esquecido** |
| **`docs/ai-plans/2026-09-03-mod-video-youtube.md`** | está marcado **pendente**. Passa a resolvido, apontando para este plano e registrando as duas mudanças de recorte: o nome virou `mod_ldgvideo`, e não é mais só YouTube |
| **`docs/ai-plans/README.md`** | o índice, com este plano já renomeado para `2026-09-04-mod-ldgvideo.md` |

### O item legal, que é consequência de verdade

O embed manda o **IP do aluno** para o YouTube ou o Vimeo assim que a página
carrega — **antes** de ele apertar play. Isso é dado pessoal saindo da
plataforma para terceiro, e o
`docs/legal/mapa-de-dados-pessoais.md` já tem a seção *"Dados que saem da
plataforma"* onde isso entra, com a base legal.

É também o argumento concreto para ligar o `nocookie` do `media_youtube`: ele não
elimina o IP, mas corta o cookie de rastreio antes do play. O README do plugin
recomenda; o mapa registra o que continua saindo mesmo com ele ligado.

E na `politica-de-privacidade.md`, a mesma frase em linguagem de titular.

### `docs/private/planos.md`

A linha do quadro *"o que já está no código"* diz hoje: *"Plano `Free`: não
existe como plano; o `mod_video` é a peça dele, e ainda é plano futuro."* Passa
a existir, com o nome novo.

> **Cuidado ao mexer nele:** `docs/private/` é gitignored e **não sobrevive à
> remoção da worktree** — foi assim que o arquivo original de 19 KB se perdeu.
> Editar e manter cópia fora de qualquer worktree, antes de o `moodev rm` rodar.

## Ordem de execução

Worktree própria: `moodev new mod-ldgvideo`. Antes, conferir no `moodev ls` qual
branch o offset 0 serve e passar no `--from` — o código vem do `--from`, o banco
vem do offset 0, e a divergência para o upgrade com `cannotdowngrade`.

**Primeiro os papéis, e não o plugin.** Eles consertam um buraco que existe hoje,
não dependem do `mod_ldgvideo` para nada, e são a parte que toca produção — se
algo tiver que ser cortado no meio do caminho, é o vídeo que espera.

1. `local_marketplace/classes/roles.php` + `tests/roles_test.php`, **o teste primeiro**
2. `db/install.php` chamando `roles::ensure()`; passo novo no `db/upgrade.php` com a migração dos `owner`
3. `classes/api.php`: `assign_member_role()`, `remove_member()`, e os pontos que gravam `memberrole`
4. `cli/status.php`: as duas linhas de papel e a de repositórios abertos
5. Idiomas do `local_marketplace`, e a feature de Behat do seletor de arquivos
6. Copiar `mod/page` → `mod/ldgvideo`, renomear e podar
7. `version.php`, `db/install.xml`, `db/install.php`, `lib.php`
8. `classes/url.php` **com o teste escrito antes** — é código puro e é onde mora o risco
9. `mod_form.php`: campo, `validation()`, `data_preprocessing()`
10. `view.php`, template e `styles.css`
11. `pix/monologo.svg`
12. Idiomas `en`/`pt_br`/`es` do plugin novo, com paridade de chaves
13. O resto dos testes do `mod_ldgvideo`
14. `.github/moodle-plugins.txt`
15. `README.md` do plugin e a `ADR-0008`
16. A documentação que muda — a tabela da seção de documentação, o mapa de dados pessoais incluído
17. Registrar este plano no índice, renomeado para `2026-09-04-mod-ldgvideo.md`, e marcar o documento de origem como resolvido

## Qualidade de código

O que o CI cobra, e o que ele não cobra e mesmo assim vale.

**`phpcs --standard=moodle`, zero violações.** E **ler o total** — `tail -3` já
produziu um "zero violações" com 16 erros presentes, e o CI reprovou:

```bash
docker exec -u 1000:33 courses-free-moodle-1 sh -c \
  'cd /tmp/cs && ./vendor/bin/phpcs --standard=moodle --report=summary \
   /var/www/html/public/mod/ldgvideo' | grep -E "A TOTAL OF"
```

**Idiomas `en`, `pt_br` e `es`, com paridade de chaves e ordem alfabética
obrigatória.** Inserir por âncora quebra o `phpcs`; depois de acrescentar,
reordena-se o arquivo inteiro. Espanhol genérico, não `es_ar`.

**Comentários e mensagens de commit em português, sem acentos.** Prosa de
documentação leva acentuação normal. E **nada de regex cego em comentário** para
fazer a renomeação em massa do passo 2: um padrão que capitaliza `// texto`
também pega a segunda linha de comentário multi-linha, e já corrompeu o cabeçalho
GPL de 74 arquivos. A renomeação é por arquivo, conferindo o diff.

**Fim de linha LF.** Os arquivos nascem de uma cópia do `mod_page`, que é
upstream — conferir `git ls-files --eol` depois do passo 2, e **não** usar
`dos2unix`.

**PHPDoc completo** em toda classe, método e propriedade, com `@package
mod_ldgvideo` — é o que o `phpcs` do Moodle cobra e o que o resto dos plugins
daqui segue.

**Comentário explica o porquê, não o quê.** É o padrão visível no `format_ldg`:
o `player.js` documenta o ciclo de realimentação que quase não se vê, e não o
que a linha faz. As duas armadilhas desta página — o `vh` e os dois
`allowfullscreen` — vão comentadas no código, não só aqui.

## Testes

**Um job de CI por plugin**, e o `mod_ldgvideo` entra na lista do passo 9.

### PHPUnit

| Arquivo | O que prova |
|---|---|
| `tests/url_test.php` | **o mais importante.** Tabela de casos, um por formato de entrada |
| `tests/lib_test.php` | `ldgvideo_supports()`, `_add_instance`, `_update_instance`, `_delete_instance`, e o `_view` que dispara o evento e marca a conclusão |
| `tests/mod_form_test.php` | os dois portões da `validation()` |
| `tests/generator_test.php` | o gerador cria instância válida — é o que os outros testes usam |
| `tests/externallib_test.php` | a chamada `_view_`, herdada do `page` |
| `tests/backup_restore_test.php` | duplicar a atividade preserva `videourl` e `aspectratio` |

A tabela do `url_test` cobre, no mínimo: o trecho `<iframe>` inteiro do
enunciado; `youtu.be/ID?si=…`; `watch?v=ID&list=…`; `embed/ID`; `shorts/ID` (que
deduz **9:16**); uma URL do **Vimeo** — provando que não há regex de YouTube
nosso; e **as recusas**: `<script>alert(1)</script>`, string vazia, uma URL que
não é vídeo, e uma URL do próprio `wwwroot`.

```bash
docker exec -u 1000:33 -e COMPOSER_HOME=/tmp/composer courses-free-moodle-1 \
  php /var/www/html/public/admin/tool/phpunit/cli/init.php
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  php vendor/bin/phpunit --testsuite mod_ldgvideo_testsuite

# O do papel, que mexe em plugin que ja esta em producao: a suite INTEIRA,
# e nao so o arquivo novo. A troca de assinatura de assign_seller_role()
# alcanca quem chamava.
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  php vendor/bin/phpunit --testsuite local_marketplace_testsuite
```

> **Teste que toca renderer precisa de `RENDERER_TARGET_GENERAL`.** Sem isso o
> PHPUnit entrega o renderer de CLI e o teste passa **verificando nada** — já
> aconteceu no `theme_ldg`.

### Behat

`tests/behat/`, com a tag `@mod_ldgvideo`, no molde das três features do
`mod_page`:

- **`ldgvideo_form.feature`** — colar o trecho `<iframe>` salva; colar lixo mostra o erro; colar uma URL do próprio site mostra o erro do plano Free
- **`ldgvideo_appearance.feature`** — com `printintro` ligado a descrição aparece, desligado não aparece
- **`ldgvideo_completion.feature`** — a atividade nova abre com "marcar manualmente" já selecionado, e o aluno consegue marcar

**Sem `@javascript`.** É a mesma regra do `format_ldg`: tudo o que essas features
verificam é desenhado no servidor, e o ambiente não tem navegador — teste que não
roda não protege nada.

```bash
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml \
  --tags "@mod_ldgvideo"
```

### No navegador, porque isto é layout

PHPUnit e Behat **não veem layout**. A prova é no Chrome, medindo contra o curso
de demonstração do portal
(`format/ldg/cli/make_testdata.php --run --reset`):

| O que provar | Como |
|---|---|
| O trecho colado vira vídeo | colar o `<iframe>` do enunciado e conferir na base que ficou só a URL — nada de HTML |
| Proporção em desktop | laterais abertas, `1440px`: medir largura e altura e conferir a razão 16:9 |
| Laterais escondidas | apertar os dois botões do cartão do aluno: o vídeo **alarga**, mantendo a razão |
| Celular | `390px`: largura cheia, sem rolagem horizontal na página |
| Short | uma atividade em 9:16 fica **alta e estreita**, sem tarja e sem estourar a coluna |
| Vimeo | um vídeo do Vimeo desenha igual — é a prova de que o multi-plataforma é real |
| Tela cheia | o botão do player abre em tela cheia **de dentro do portal** |
| Sem realimentação | deixar aberto trinta segundos e conferir que a altura do quadro não cresce sozinha |

E um teste que só o servidor pega, mas que fecha o desenho: um `mod_ldgvideo`
num curso do portal aparece em **Aulas**, e não em Material — provando que
`catalog::classify()` não precisou mudar.

### O upgrade, que é o que pode quebrar produção

A parte dos papéis roda contra um banco que já tem empresas e vínculos, e o
`install.php` **não** é executado ali. A prova é sobre um banco com dado, não
sobre um banco novo:

1. Antes de subir, no ambiente da worktree: conferir quantos `owner` existem — `SELECT memberrole, COUNT(*) FROM {local_marketplace_member} GROUP BY memberrole`
2. Rodar o upgrade e conferir que **cada** `owner` tem `marketplacemanager` no contexto da categoria da empresa dele, e **não tem mais** `marketplaceseller`
3. **Rodar o upgrade de novo** — o passo é idempotente, e a segunda passada não pode mexer em nada
4. `php local/marketplace/cli/status.php < /dev/null` (o `< /dev/null` é obrigatório) e ler as três linhas novas
5. Entrar como um vendedor de teste e abrir o formulário de uma atividade: **não há "Enviar um arquivo"** no seletor

O passo 3 é o que costuma faltar. Um upgrade que morre no meio é rodado de novo,
e é aí que uma migração não idempotente duplica atribuição ou derruba quem já
foi migrado.


---

# O que a execução mudou

O plano acima está preservado como foi aprovado. Três coisas nele se mostraram
falsas ao encostar no código, e uma quarta era um defeito que só apareceu
rodando. Registrar isso é o ponto deste diretório.

## "Não há regex de plataforma no plugin" — não se sustentou

O plano afirmava que reconhecer plataforma era inteiramente do core. É verdade
para o **reconhecimento**, e falso para o **endereço de incorporação**: os regex
dos players do core cobrem o link da barra de endereços, e não o do `src`. O
`media_youtube` não casa `/embed/` nem `/shorts/`; o `media_vimeo` não casa
`player.vimeo.com/video/123`.

Ou seja, o caso principal do plugin — colar o trecho que o botão *Incorporar*
entrega — não funcionaria. Entrou uma tabela de **quatro canonicalizações** em
`url::canonicalizar()`, que reescreve o caminho sem decidir se aquilo é vídeo. O
docblock da classe diz exatamente isso, e a [ADR-0008](../adr/0008-embed-multiplataforma-pelo-core.md)
registra a decisão.

## O ícone: `currentColor` seria errado

O plano pedia `fill="currentColor"`. A convenção real da base é `fill="#212529"`
literal — conferido no `mod_url`, `mod_quiz`, `mod_forum` e `mod_resource`.
Seguiu-se a base.

## O `phpcs` não vive mais em `/tmp/cs`

O comando do `CLAUDE.md` falha: a ferramenta veio para a imagem, em
`/opt/devtools`, com symlink no `PATH`. O `CLAUDE.md` foi corrigido, junto com o
fato de o CI rodar `--max-warnings 0` — **aviso também reprova**, e isso não
estava escrito em lugar nenhum.

## O defeito que só o Behat achou

`url::montar()` **perdia a porta**. `parse_url` devolve host e porta separados, e
a primeira versão só guardava o host: `http://moodle:8000/…` virava
`http://moodle/…`. Além de corromper qualquer endereço com porta, isso derrotava
o portão do vídeo hospedado aqui — num site em porta não padrão, o endereço
remontado deixava de começar pelo `wwwroot` e o vídeo local **passaria**.

Nenhum teste unitário pegaria: eles usavam endereços sem porta. Apareceu porque
o Behat roda em `:8000`. Há regressão fixando porta e fragmento.

## E o defeito que o banco de teste escondia

`roles::ensure()` só chamava `assign_capability()`, que **nunca remove**. Numa
base que já tinha o papel de vendedor — a produção — ele seguia com
`managepayment`, `managecompany`, `viewreport` e `role:assign` do desenho
antigo. A separação de papéis funcionava no banco de teste, que nasce limpo, e
**não fazia absolutamente nada em produção**.

Encontrado rodando o upgrade contra um banco com dado, que era o passo de
verificação do próprio plano. `ensure()` passou a reconciliar, e há teste que
reproduz o estado da produção antes de rodar.

## Escopo da proibição: mais estreito do que o plano dava a entender

O plano falava em "o vendedor não coloca arquivo no site". O papel vive no
contexto da **categoria**, então a proibição vale onde o curso é montado e **não**
no perfil pessoal de quem monta — o vendedor ainda sobe arquivo nos próprios
*Arquivos privados*. Isso não abre a margem (aquele arquivo não chega ao aluno),
mas a frase larga teria virado dívida. O escopo está fixado em teste, e a
[ADR-0009](../adr/0009-papeis-de-empresa-sem-upload.md) explica.

## Fechado em 06/09/2026

Duas das pendências abaixo eram a mesma coisa vista de dois lados, e foram
resolvidas juntas.

- **Os cinco players de mídia foram habilitados no site.** O Vimeo passou a funcionar; a recusa que ele dava era configuração, e não código.
- **A mensagem de erro passou a distinguir os dois casos.** Se um player *instalado* reconheceria o endereço mas está desligado, o formulário diz que a ação é do administrador, em vez de mandar o professor conferir um link que já está certo. Antes as duas situações davam "isto não parece um endereço de vídeo". Há teste unitário e cenário de Behat.

## Em aberto

- **O plano Free continua sem existir como plano vendável** em `local_marketplace_plan`. Este trabalho entregou a peça técnica, não a comercial.
- **Desligar um player quebra as atividades já salvas** que dependiam dele, em silêncio. A mensagem nova só cobre o momento de *criar* a atividade. Registrado na [ADR-0008](../adr/0008-embed-multiplataforma-pelo-core.md) como sinal de erro.
- **Dois vínculos órfãos** em `local_marketplace_member` apontam para empresa que não existe mais, no banco de desenvolvimento. É sujeira anterior a este trabalho; a migração dos donos não foi afetada, porque ela percorre empresas e não vínculos.

## Verificação, com número

| O quê | Resultado |
|---|---|
| PHPUnit `local_marketplace` | 114 testes, 348 asserções |
| PHPUnit `mod_ldgvideo` | 38 testes, 102 asserções |
| Behat `@mod_ldgvideo` (perfil chrome) | 10 cenários, 76 passos |
| Behat `local_marketplace/roles.feature` | 1 cenário |
| phpcs, os dois plugins | 96 arquivos, **0 erros e 0 avisos** |
| Upgrade contra banco com dado | 3 donos migrados; gerente 38 capabilities, vendedor 31, 24 proibidas em cada |
| Medição no Chrome, 1440x900 | quadro de **1022px**, e não os 560 do atributo do core |
