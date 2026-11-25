> **Situação:** executado · **Início:** 2026-09-02
> **Origem:** `~/.claude/plans/shiny-dreaming-popcorn.md` — copiado literalmente, sem edição do corpo.
> **Resultado:** PR #65 — `format_ldg` entregue em `MATURITY_BETA`, e o fechamento do `theme_ldg`. A conferência no navegador que veio depois virou os planos 1 a 5 do portal.

# `format_ldg` — portal do aluno, e o fechamento do tema

## Contexto

O `theme_ldg` deixou de ser filho do `theme_moove` e passou a depender só do
`theme_boost`, que é core. A troca foi pedida para o produto não depender de um
tema de terceiro, e custou o que se esperava: as customizações que o Moove fazia
sobre o core sumiram junto. Oito delas eram **overrides visuais**, não plumbing —
sem eles o site funciona, renderizando a versão do core.

Ao mesmo tempo, existe um objetivo maior: um **formato de curso** no estilo
"portal do aluno" (referências em `docs/brand/courses-format-theme*.png`) — vídeo
ao centro, lista de aulas por módulo com conclusão e duração, progresso à vista.

As duas frentes se cruzam em poucas peças (barra de progresso, navegação entre
aulas), e é por isso que valem um plano só: construir a barra de progresso
isolada agora e refazê-la dentro do formato depois seria retrabalho.

**Decisões já tomadas com o usuário:**

| Questão | Decisão |
|---|---|
| Escopo | **Formato de curso**. Abas com vários cursos ficam fora |
| Conteúdo da aula | **Embutido por iframe**, padrão do `format_tiles` |
| Duração | **Tabela própria por `cmid`**, editada na própria lista |
| Logo | contrasta com o **fundo**, não com o modo (já implementado) |

## O que já existe e deve ser reaproveitado

Verificado no código — **não reimplementar**:

| Precisa | Onde já está |
|---|---|
| % do curso | `\core_completion\progress::get_course_progress_percentage($course, $userid)` |
| Conclusão da aula, com botão | `\core_course\output\activity_completion::export_for_template()` — devolve `ismanual`, `showmanualcompletion`, `overallcomplete`, `completiondetails` |
| Detalhe da conclusão | `\core_completion\cm_completion_details::get_instance($cminfo, $userid)` |
| Lista de aulas por seção | `get_fast_modinfo($course, $userid)` → `get_section_info_all()`, `section_info::get_sequence_cm_infos()` |
| **Bloqueio do que não foi comprado** | `availability_marketplace` já entra em `cm_info->uservisible` e `section_info->availableinfo`. O formato **não** deve consultar `entitlement` |
| Marcar concluído por AJAX | WS `core_completion_update_activity_completion_status_manually` |
| `.progress` / `.progress-bar` | já estilizados com os tokens em `theme/ldg/scss/ldg/_course.scss` |

**Cuidado registrado:** `entitlement::user_has_course_access()` faz N+1 (uma
query de `get_course_ids()` por oferta). Não chamar em laço de listagem.

## Lacunas reais

- **Progresso por seção não existe.** O mais próximo é
  `core_courseformat\output\local\content\section\cmsummary::calculate_section_stats()`,
  que é `protected`. Replicar a contagem (~40 linhas) numa classe própria.
- **Duração de vídeo não existe** em nenhum lugar do core — nem tabela, nem
  campo, nem exposição pelo player.
- **Não há player nem integração de vídeo.** `plan::max_resolution_for()` existe
  e **não tem consumidor** (ADR-0005 segue Proposta).

---

## Fase A — fechar o tema

Independente do formato, e curta. Sem isto o tema fica com buracos que já
aparecem no uso.

### A1. Absorver os três overrides que valem

Sempre a partir do **original do core**, nunca copiando o do Moove — o dele traz
classes (`moove-container-fluid`) e strings (`theme_moove`) que já causaram
quebra.

| Arquivo novo | Base | O que muda |
|---|---|---|
| `templates/block_myoverview/progress-bar.mustache` | `blocks/myoverview/templates/progress-bar.mustache` | o core mostra só texto; desenhar a barra **e manter o texto** (barra sozinha não serve a leitor de tela) |
| `templates/core_course/activity_navigation.mustache` | `course/templates/activity_navigation.mustache` | cartão com anterior/próxima; hoje é um seletor solto |
| `templates/core/user_menu.mustache` | `lib/templates/user_menu.mustache` | acrescentar o item **Acessibilidade** apontando para `/theme/ldg/accessibility.php` |

### A2. `db/install.php` — user tours

Lacuna real apontada pelo usuário. O Moove percorre `tool_usertours_tours` e
acrescenta o próprio tema ao filtro de quem já tinha `boost`. Sem isso, tour
configurado para o Boost não aparece no `theme_ldg`.

**O `install.php` só roda em instalação nova** — precisa do passo equivalente em
`db/upgrade.php`, senão produção e o ambiente local ficam de fora.

### A3. Avaliar, testando a tela antes

`mod_quiz` (timer, lista de tentativas, resumo), `core_enrol` (inscrição),
`full_header`, `view-cards`. Abrir cada tela com o core puro e decidir. Várias
devem estar aceitáveis.

**Não trazer:** `loginform` (155 linhas — já temos login próprio com painel de
marca) e `full_header` (reorganiza usando o container do Moove; ajustar por CSS
se precisar).

---

## Fase B — `format_ldg`

### B0. O esqueleto vem do MDLCode — passo seu, não meu

O scaffold é gerado pela extensão **MDLCode** (`lmscloud.mdlcode`, instalada em
1.6.4), que já produz a base no padrão do Moodle:

> **MDLCode Wizard** → *Create new plugin* → **Plugin type: Course format**

Confirmado que a extensão suporta o tipo `format` e que o molde dela conhece
`format.php`, `courseformat` e `section_renderer`.

**Por que este passo é seu:** o wizard é uma interface do VSCode; não dá para
acioná-lo por linha de comando. Rode com o nome `ldg` (componente
`format_ldg`).

**Assim que existir, eu assumo:** confiro o que veio, ajusto o `version.php`
(`supported = [502, 502]`, dependências), e sigo pela ordem abaixo. Tudo que o
scaffold já entregar **não será reescrito** — só adaptado.

### Estrutura alvo

O que o MDLCode gerar mais o que falta acrescentar:

```
public/course/format/ldg/
├── version.php              component format_ldg, requires 2026041000, supported [502,502]
├── lib.php                  class format_ldg extends core_courseformat\base
├── format.php               ~15 linhas, no molde do topics
├── settings.php
├── lang/{en,pt_br,es}/format_ldg.php
├── db/install.xml           format_ldg_lesson
├── db/upgrade.php
├── db/services.php          set_lesson_duration
├── classes/
│   ├── privacy/provider.php
│   ├── lesson.php           persistent: cmid, duration, sortorder
│   ├── section_progress.php a contagem que o core não expõe
│   ├── external/set_duration.php
│   └── output/
│       ├── renderer.php     extends core_courseformat\output\section_renderer
│       └── courseformat/
│           ├── content.php          + get_template_name()
│           └── content/lessonlist.php
├── templates/local/content.mustache
├── templates/local/content/lessonlist.mustache
├── amd/src/{player.js, duration.js, mutations.js}
└── backup/moodle2/restore_format_ldg_plugin.class.php
```

### Como o Moodle liga as peças (verificado)

`core_courseformat\base::get_output_classname()` procura
`format_ldg\output\courseformat\X` para cada `core_courseformat\output\local\X`.
Basta a classe existir e **estender a base**.

Mas o trait `courseformat_named_templatable` mapeia o template **sempre para o
core**. Para usar mustache próprio, sobrescrever `get_template_name()` na output
class — o jeito do `format_onetopic`
(`course/format/onetopic/classes/output/courseformat/content.php:54`).

### `lib.php`

```php
uses_sections()      => true    // seção = módulo do curso
uses_course_index()  => false   // a lista de aulas do formato substitui
supports_components()=> true    // editor reativo
get_view_url($section, $options)  // /course/view.php?id=N&lesson=CMID
course_format_options()          // aula padrão ao abrir o curso
```

`uses_course_index() => false` é deliberado: o course index é renderizado **no
cliente**, a partir do state reativo, e trocar o conteúdo dele exigiria um AMD
próprio. A lista de aulas do formato já cumpre o papel, e desligar evita duas
navegações concorrentes na mesma tela.

**Não** implementar `main_activity_interface`: ela faz a navegação secundária
virar a do módulo, e aqui o curso tem muitas atividades.

### A tela

```
┌──────────────────────────────────────────────────────────┐
│  progresso do curso  (barra + %)                         │
├───────────────────────────────┬──────────────────────────┤
│  iframe da atividade          │  LISTA DE AULAS          │
│  /mod/*/view.php?id=CMID      │   Módulo 1               │
│                               │    ✓ Aula 1      12:30   │
│  título + descrição           │    ▸ Aula 2      18:45   │
│  [ Marcar concluído ]         │   Módulo 2               │
│                               │    🔒 Aula 3     22:10   │
└───────────────────────────────┴──────────────────────────┘
```

- **iframe** aponta para a `view.php` do módulo. É carregamento real: marca
  visualizado, registra log e respeita restrição, sem o formato reimplementar
  nada. Altura ajustada por `postMessage` no `player.js`.
- **Aula bloqueada** usa `cm_info->uservisible === false`; o texto do cadeado sai
  de `section_info->availableinfo`, que já traz o link da vitrine.
- **Marcar concluído** só aparece quando `showmanualcompletion` é verdadeiro.

### Duração

```
format_ldg_lesson
  cmid      int, único
  duration  int, segundos, nullable
```

Nulo = não mostra. Com edição ligada, cada item da lista ganha campo inline que
grava por `set_lesson_duration`.

Formatar com `format_time()` do core, não à mão.

### Ordem de execução

0. **Você** gera o esqueleto pelo MDLCode Wizard (ver B0)
1. Eu confiro e instalo o que veio — provar que o Moodle aceita o formato antes
   de investir em visual
2. Lista de aulas com conclusão e estado de bloqueio
3. Progresso do curso e da seção
4. iframe e a aula selecionada
5. Duração: tabela, WS e edição inline
6. SCSS no design system, dentro do tema — **mobile junto, não depois**
7. Testes e documentação de instalação

### Mobile é requisito, não acabamento

A tela da referência é de desktop, e ela **não cabe no celular**: lista ao lado
do vídeo vira lista embaixo, e o iframe precisa acompanhar a largura.

O layout é escrito **mobile primeiro**, e o `@include media-breakpoint-up(lg)`
é que traz as duas colunas — o contrário sempre deixa resíduo de largura fixa.

Pontos que costumam quebrar e precisam de teste explícito:

- iframe com altura fixa em tela estreita — a mensagem de altura do `player.js`
  vale nos dois
- lista de aulas longa: no celular ela precisa **colapsar**, senão o aluno rola
  a lista inteira antes de chegar ao vídeo
- o menu lateral do tema já vira off-canvas no celular; a lista de aulas não
  pode competir com ele pelo mesmo gesto
- campo de duração na edição inline, com teclado virtual aberto

**Testar no aparelho, não só no DevTools.** O ambiente já aceita o IP da rede
local (`config-local.php` tem `192.168.15.6:8443` no allowlist), que existe
justamente para isso.

### Testes

| Camada | O que cobre |
|---|---|
| **phpunit** | `section_progress` (a contagem que replicamos do core), `lesson` persistent, e o WS de duração |
| **behat** | trocar o formato, aula bloqueada, marcar concluído, e a volta para Topics sem quebrar |
| **phpcs** | limpo, com `--max-warnings 0` — warning reprova no CI |

O teste de `section_progress` importa mais que os outros: é código **copiado do
core** porque o original é `protected`. Se o Moodle mudar a regra de contagem, o
nosso silenciosamente diverge — o teste é o que avisa.

### Documentação de instalação

- **`README.md` do plugin**, no padrão dos outros oito: para que serve,
  dependências, o que configurar, armadilhas. Precisa dizer que o **SCSS vive no
  tema** — quem instalar o formato com outro tema recebe marcação sem estilo, e
  isso tem que estar escrito, não descoberto.
- **`docs/operacao/configuracao-inicial.md`**: acrescentar o passo de escolher o
  formato no curso, e o aviso de que `uses_course_index() => false` desliga o
  índice lateral naquele curso.
- **`docs/data-model/marketplace.md`**: a tabela `format_ldg_lesson`.
- **ADR** se a decisão do iframe for questionada depois — ela tem alternativa
  real (renderizar direto), e o porquê da escolha precisa sobreviver a mim.

**O SCSS do formato vive no tema** (`theme/ldg/scss/ldg/_format.scss`), não no
plugin. O formato entrega marcação semântica; quem pinta é o tema. Assim o
formato continua legível se alguém usar outro tema.

## Verificação

```bash
# instala e sobe versão
docker exec -u 1000:33 courses-free-moodle-1 \
  php /var/www/html/admin/cli/upgrade.php --non-interactive
docker exec -u 1000:33 courses-free-moodle-1 \
  php /var/www/html/admin/cli/purge_caches.php

# phpcs — LEIA O TOTAL
docker exec -u 1000:33 courses-free-moodle-1 sh -c \
  'cd /tmp/cs && ./vendor/bin/phpcs --standard=moodle -p /var/www/html/public/course/format/ldg'

# behat
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml --tags @format_ldg
```

No navegador, num curso com pelo menos duas seções e uma atividade restrita por
`availability_marketplace`:

| # | Teste | Esperado |
|---|---|---|
| 1 | Trocar o formato do curso para LDG | sem erro, seções preservadas |
| 2 | Aluno sem compra | aula restrita com cadeado e link da vitrine |
| 3 | Aluno com compra | mesma aula abre no iframe |
| 4 | Assistir e marcar concluído | ✓ na lista **e** progresso subindo, sem recarregar |
| 5 | Professor com edição ligada | campo de duração aparece e grava |
| 6 | Mudar o curso de volta para Topics | nada quebra; a duração fica órfã, sem erro |
| 7 | Modo claro e escuro | ambos legíveis |
| 8 | **Celular, no aparelho** (`https://192.168.15.6:8443`) | lista abaixo do vídeo, iframe na largura da tela, lista longa colapsada |
| 9 | Celular, com edição ligada | campo de duração alcançável com o teclado aberto |

O teste 6 importa: dado de formato **não** pode impedir a troca de formato.

## Fora de escopo

- Abas com vários cursos — vira bloco ou página de portal, decidido depois
- Player próprio, trava de resolução, Bunny/Cloudflare — ADR-0005, e nada disso
  existe hoje
- Duração automática vinda do provedor
- Editor de aula fora da tela padrão do Moodle
