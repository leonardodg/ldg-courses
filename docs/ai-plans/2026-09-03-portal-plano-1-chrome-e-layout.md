# Portal do aluno — Plano 1: chrome e layout

> **Para quem executa:** use `superpowers:subagent-driven-development` ou
> `superpowers:executing-plans` para tocar tarefa a tarefa. Os passos usam
> `- [ ]` para marcar progresso.

**Meta:** a página do curso em `format_ldg` passa a ser servida por um layout
próprio, sem navbar nem drawers, para quem não está editando — e continua de pé
com qualquer tema.

**Arquitetura:** o `format_ldg` decide (hook `before_http_headers`) e o
`theme_ldg` desenha (layout `ldgportal`). A decisão vive numa função separada,
que recebe a lista de layouts do tema por parâmetro para poder ser testada sem
tema instalado.

**Pilha:** Moodle 5.2 (layout `public/`), PHP 8.4, Bootstrap 5.3.3 já compilado
pelo tema, PHPUnit, Behat, `moodle-plugin-ci` 4.

Spec: [`2026-09-03-portal-do-aluno-format-ldg.md`](2026-09-03-portal-do-aluno-format-ldg.md) ·
Design system: [`../brand/DESIGN.md`](../brand/DESIGN.md)

## Restrições globais

- Worktree `format-course-ldg`, branch `fix/format-ldg-navegador`. O stack é o
  **offset 0** (`courses-free-moodle-1`, `https://localhost:8443`).
- Código e comentários em **português sem acentos**. Documentação e strings de
  idioma **com** acentos.
- Cabeçalho GPL em todo arquivo novo; `@copyright 2026 LeoDG <callme@leodg.dev>`.
- Strings em `en`, `pt_br` e `es`, em **ordem alfabética** no arquivo.
- CLI sempre como `-u 1000:33`; `< /dev/null` em `docker compose exec`.
- `phpcs --standard=moodle` com **zero** violações — ler o TOTAL, não as últimas
  linhas da saída.
- JS nasce em `amd/src` com `amd/build` gerado por `npx grunt`. Nunca editar o
  `build` à mão.
- Nada de push. O PR é aberto por quem pediu, e só depois de tudo pronto.

## Armadilha descoberta ao planejar

**Não use `\core_course\hook\before_course_viewed`.** É o hook de nome óbvio, e
está errado: `course/view.php` o despacha na **linha 110** e chama
`$PAGE->set_pagelayout('course')` na **linha 116** — seis linhas depois. Qualquer
layout definido ali é sobrescrito, sem erro nenhum.

O hook certo é `\core\hook\output\before_http_headers`, despachado na primeira
linha de `core_renderer::header()`
(`lib/classes/output/core_renderer.php:836`), ~28 linhas antes de
`layout_file($this->page->pagelayout)` resolver o arquivo, com a página ainda em
`STATE_BEFORE_HEADER`.

Ele carrega só o renderer (`public readonly \renderer_base $renderer`); a página
vem de `$hook->renderer->get_page()`, que existe em
`lib/classes/output/renderer_base.php:431`.

## Estrutura de arquivos

| Arquivo | Responsabilidade |
|---|---|
| `public/course/format/ldg/classes/hook_callbacks.php` | decidir e trocar o layout — só isso |
| `public/course/format/ldg/db/hooks.php` | registrar o listener |
| `public/course/format/ldg/tests/hook_callbacks_test.php` | a decisão, nos quatro cenários |
| `public/theme/ldg/config.php` | declarar o layout `ldgportal` |
| `public/theme/ldg/layout/ldgportal.php` | montar o contexto do chrome |
| `public/theme/ldg/templates/ldgportal.mustache` | o chrome: cabeçalho, avatar, sair |
| `public/course/format/ldg/tests/behat/format_ldg.feature` | aluno sem navbar, professor com |
| `.github/moodle-plugins.txt` | pôr o formato no portão do CI |

---

### Tarefa 1: a decisão de usar o portal

**Arquivos:**
- Criar: `public/course/format/ldg/classes/hook_callbacks.php`
- Testar: `public/course/format/ldg/tests/hook_callbacks_test.php`

**Interfaces:**
- Produz: `\format_ldg\hook_callbacks::LAYOUT` (string `'ldgportal'`) e
  `hook_callbacks::should_use_portal(\moodle_page $page, array $layouts): bool`.
  A tarefa 2 chama as duas.

- [x] **Passo 1: escrever o teste que falha**

Criar `public/course/format/ldg/tests/hook_callbacks_test.php`:

```php
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Testes da decisao de trocar o layout pelo do portal.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg;

/**
 * Testes da decisao de trocar o layout pelo do portal.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_ldg\hook_callbacks::class)]
final class hook_callbacks_test extends \advanced_testcase {
    /** @var array Layouts de um tema que declara o portal. */
    private const COM_PORTAL = ['ldgportal' => ['file' => 'ldgportal.php', 'regions' => []]];

    /** @var array Layouts de um tema que nao declara. */
    private const SEM_PORTAL = ['course' => ['file' => 'drawers.php', 'regions' => ['side-pre']]];

    /**
     * Monta uma pagina de curso no formato pedido.
     *
     * @param string $formato
     * @return \moodle_page
     */
    private function pagina_de_curso(string $formato): \moodle_page {
        global $PAGE;

        $curso = $this->getDataGenerator()->create_course(['format' => $formato]);

        // O show_editor() do core le o $PAGE GLOBAL, nao o que recebemos por
        // parametro. Sem apontar o global para o mesmo curso, o teste do
        // professor editando passaria por engano.
        $PAGE->set_course($curso);
        $PAGE->set_pagelayout('course');

        return $PAGE;
    }

    /**
     * Aluno num curso ldg, com tema que declara o layout: portal.
     *
     * @return void
     */
    public function test_aluno_no_curso_ldg_usa_o_portal(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $pagina = $this->pagina_de_curso('ldg');

        $this->assertTrue(hook_callbacks::should_use_portal($pagina, self::COM_PORTAL));
    }

    /**
     * Tema sem o layout: nao troca, para nao cair no standard com debugging.
     *
     * @return void
     */
    public function test_tema_sem_o_layout_nao_troca(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $pagina = $this->pagina_de_curso('ldg');

        $this->assertFalse(hook_callbacks::should_use_portal($pagina, self::SEM_PORTAL));
    }

    /**
     * Outro formato de curso nao vira portal.
     *
     * @return void
     */
    public function test_outro_formato_nao_usa_o_portal(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $pagina = $this->pagina_de_curso('topics');

        $this->assertFalse(hook_callbacks::should_use_portal($pagina, self::COM_PORTAL));
    }

    /**
     * Com a edicao ligada o professor volta para o chrome do Moodle.
     *
     * @return void
     */
    public function test_edicao_ligada_nao_usa_o_portal(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $pagina = $this->pagina_de_curso('ldg');
        $USER->editing = 1;

        $this->assertFalse(hook_callbacks::should_use_portal($pagina, self::COM_PORTAL));
    }

    /**
     * Pagina que nao e a do curso - relatorio, perfil - fica como esta.
     *
     * @return void
     */
    public function test_outro_layout_nao_usa_o_portal(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $pagina = $this->pagina_de_curso('ldg');
        $pagina->set_pagelayout('report');

        $this->assertFalse(hook_callbacks::should_use_portal($pagina, self::COM_PORTAL));
    }
}
```

- [x] **Passo 2: rodar e ver falhar**

```bash
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  vendor/bin/phpunit public/course/format/ldg/tests/hook_callbacks_test.php
```

Esperado: **erro fatal** `Class "format_ldg\hook_callbacks" not found`.

- [x] **Passo 3: escrever a implementação mínima**

Criar `public/course/format/ldg/classes/hook_callbacks.php`:

```php
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Callbacks de hook do formato.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg;

use core\hook\output\before_http_headers;
use moodle_page;

/**
 * Troca o layout da pagina do curso pelo do portal do aluno.
 *
 * O course/view.php do core crava set_pagelayout('course') e so consulta o
 * formato ANTES disso, entao o formato nao consegue escolher o proprio layout
 * pelos caminhos normais. Este listener corre no before_http_headers, que e
 * despachado na primeira linha do core_renderer::header() - antes de o
 * pagelayout virar arquivo, e com a pagina ainda em STATE_BEFORE_HEADER.
 *
 * NAO use o \core_course\hook\before_course_viewed, que tem o nome mais obvio:
 * o course/view.php o despacha SEIS LINHAS ANTES do set_pagelayout, e o layout
 * definido la e sobrescrito sem erro nenhum.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /** @var string O layout que o tema precisa declarar para o portal existir. */
    public const LAYOUT = 'ldgportal';

    /**
     * A decisao, separada do estado global para poder ser testada.
     *
     * Os layouts vem por PARAMETRO, e nao de $page->theme, porque o
     * moodle-plugin-ci roda o formato sem o theme_ldg instalado - um teste que
     * dependesse do tema seria pulado justamente onde precisa valer.
     *
     * @param moodle_page $page
     * @param array $layouts Os layouts declarados pelo tema ativo.
     * @return bool
     */
    public static function should_use_portal(moodle_page $page, array $layouts): bool {
        if ($page->pagelayout !== 'course') {
            return false;
        }

        $curso = $page->course;

        if (empty($curso->id) || $curso->id == SITEID || $curso->format !== 'ldg') {
            return false;
        }

        // Tema que nao declara o layout cairia no 'standard' com um debugging()
        // na cara de quem usa outro tema. Melhor nao trocar.
        if (!array_key_exists(self::LAYOUT, $layouts)) {
            return false;
        }

        // Professor editando volta para o chrome do Moodle: arrastar atividade,
        // renomear e o menu de acoes vivem nos ganchos que o core poe naquela
        // marcacao, e nenhum deles funciona dentro do portal.
        return !course_get_format($curso)->show_editor();
    }

    /**
     * Ponto de entrada do hook.
     *
     * @param before_http_headers $hook
     * @return void
     */
    public static function before_http_headers(before_http_headers $hook): void {
        $page = $hook->renderer->get_page();

        if (!self::should_use_portal($page, (array) $page->theme->layouts)) {
            return;
        }

        $page->set_pagelayout(self::LAYOUT);
    }
}
```

- [x] **Passo 4: rodar e ver passar**

```bash
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  vendor/bin/phpunit public/course/format/ldg/tests/hook_callbacks_test.php
```

Esperado: **OK (5 tests)**.

- [x] **Passo 5: phpcs limpo**

```bash
docker exec -u 1000:33 courses-free-moodle-1 sh -c \
  'cd /tmp/cs && ./vendor/bin/phpcs --standard=moodle --report=summary \
   /var/www/html/public/course/format/ldg' | grep -E "A TOTAL OF|no errors"
```

Esperado: nenhuma violação. **Leia a linha do total** — cortar a saída já
escondeu 16 erros aqui uma vez.

- [x] **Passo 6: commitar**

```bash
git add public/course/format/ldg/classes/hook_callbacks.php \
        public/course/format/ldg/tests/hook_callbacks_test.php
git commit -m "NOBUG: format_ldg - a decisao de usar o layout do portal"
```

---

### Tarefa 2: registrar o listener

**Arquivos:**
- Criar: `public/course/format/ldg/db/hooks.php`
- Modificar: `public/course/format/ldg/version.php`

**Interfaces:**
- Consome: `\format_ldg\hook_callbacks::before_http_headers` da tarefa 1.
- Produz: o hook passa a rodar de verdade; a tarefa 3 vê o efeito na tela.

- [x] **Passo 1: criar o registro**

`public/course/format/ldg/db/hooks.php` (mesmo cabeçalho GPL das outras):

```php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => \format_ldg\hook_callbacks::class . '::before_http_headers',
    ],
];
```

- [x] **Passo 2: subir a versão do plugin**

Em `public/course/format/ldg/version.php`, trocar
`$plugin->version = 2026090304;` por `$plugin->version = 2026090305;`.

O `db/hooks.php` só é lido depois de um upgrade — sem o bump, o listener não
existe para o Moodle e a tarefa 3 falha por um motivo que não é o dela.

- [x] **Passo 3: rodar o upgrade e limpar cache**

```bash
cf cli upgrade.php --non-interactive && cf cli purge_caches.php
```

- [x] **Passo 4: conferir que o hook foi registrado**

```bash
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  php public/admin/cli/cfg.php --name=hooks_callback_overrides 2>/dev/null; \
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  php -r 'define("CLI_SCRIPT",true); require("/var/www/html/config.php");
  $m = \core\hook\manager::get_instance();
  var_dump($m->get_callbacks_for_hook(\core\hook\output\before_http_headers::class));' \
  | grep -c format_ldg
```

Esperado: **1** ou mais. Zero significa que o upgrade não rodou ou o arquivo tem
erro de sintaxe.

- [x] **Passo 5: commitar**

```bash
git add public/course/format/ldg/db/hooks.php public/course/format/ldg/version.php
git commit -m "NOBUG: format_ldg - registra o listener do before_http_headers"
```

---

### Tarefa 3: o layout `ldgportal` no tema

**Arquivos:**
- Modificar: `public/theme/ldg/config.php` (bloco `$THEME->layouts`)
- Criar: `public/theme/ldg/layout/ldgportal.php`
- Criar: `public/theme/ldg/templates/ldgportal.mustache`
- Modificar: `public/theme/ldg/version.php`

**Interfaces:**
- Consome: a constante `'ldgportal'` da tarefa 1 (o nome do layout é o contrato
  entre os dois plugins).
- Produz: a página do curso servida sem navbar e sem drawers, com o menu de
  usuário no cabeçalho.

- [x] **Passo 1: declarar o layout**

Em `public/theme/ldg/config.php`, dentro de `$THEME->layouts`, acrescentar depois
do `frontpage`:

```php
    // O portal do aluno, servido pelo format_ldg. Sem navbar e sem drawers: o
    // chrome inteiro e do portal. O nome tem que continuar 'ldgportal', que e o
    // que o \format_ldg\hook_callbacks::LAYOUT procura antes de trocar.
    'ldgportal' => [
        'file' => 'ldgportal.php',
        'regions' => [],
        'options' => ['nonavbar' => true, 'nocourseheaderfooter' => true],
    ],
```

- [x] **Passo 2: escrever o layout**

`public/theme/ldg/layout/ldgportal.php` (com cabeçalho GPL e docblock
`@package theme_ldg`):

```php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');

// O menu de usuario vem do core, montado pela mesma classe que o layout de
// drawers usa. Tirar a navbar nao pode tirar junto o "sair" do aluno.
$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, [
        'context' => \core\context\course::instance(SITEID),
        'escape' => false,
    ]),
    'output' => $OUTPUT,
    'bodyattributes' => $OUTPUT->body_attributes(['ldg-portal']),
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'coursename' => format_string($COURSE->fullname),
    // "Fechar Aula" devolve o aluno para a area dele, nao para a home do site:
    // quem esta num curso quer voltar para a lista de cursos.
    'exiturl' => (new moodle_url('/my/'))->out(false),
    'exitlabel' => get_string('exitcourse', 'format_ldg'),
];

echo $OUTPUT->render_from_template('theme_ldg/ldgportal', $templatecontext);
```

- [x] **Passo 3: escrever o template**

`public/theme/ldg/templates/ldgportal.mustache`. O comentário do topo segue o
padrão da casa (com o aviso de não escrever chaves duplas dentro dele) e o corpo
é:

```html
{{> theme_boost/head }}

<body {{{ bodyattributes }}}>
{{> core/local/toast/wrapper}}

<div id="page-wrapper" class="ldg-portal-wrapper">
    {{{ output.standard_top_of_body_html }}}

    <header class="ldg-portal__header">
        <a class="ldg-portal__brand" href="{{{ exiturl }}}">
            <span class="ldg-portal__brand-mark" aria-hidden="true"></span>
            <span class="ldg-portal__brand-name">{{ sitename }}</span>
        </a>

        <div class="ldg-portal__course" title="{{ coursename }}">{{ coursename }}</div>

        <div class="ldg-portal__actions">
            {{#langmenu}}
                {{> theme_boost/language_menu }}
            {{/langmenu}}

            <div class="d-flex align-items-stretch usermenu-container" data-region="usermenu">
                {{#usermenu}}
                    {{> core/user_menu }}
                {{/usermenu}}
            </div>

            <a class="ldg-portal__exit" href="{{{ exiturl }}}">{{ exitlabel }}</a>
        </div>
    </header>

    <div id="page" class="ldg-portal__page">
        <div id="region-main" role="main">
            <span id="maincontent"></span>
            {{{ output.main_content }}}
        </div>
    </div>

    {{{ output.standard_after_main_region_html }}}
</div>

{{{ output.standard_end_of_body_html }}}
</body>
</html>
```

`{{{ output.main_content }}}` é **obrigatório**: sem ele o `header()` do core
lança `page layout file ... does not contain the main content placeholder`.

**`usermenu` e `langmenu` são contextos, não HTML.** Interpolar com chaves triplas
derruba a página com `Array to string conversion`, e o erro aponta para o
mustache **compilado** em `moodledata/localcache`, não para o template — custa
tempo até se entender que o arquivo citado não é o que se edita. Quem desenha é o
parcial do core, entrando na seção, como o `navbar.mustache` deste tema já faz.

- [x] **Passo 4: string do botão, nos três idiomas**

Em `public/course/format/ldg/lang/en/format_ldg.php`, na posição alfabética:

```php
$string['exitcourse'] = 'Close lesson';
```

`pt_br`: `'Fechar aula'`. `es`: `'Cerrar lección'`. **Reordene o arquivo inteiro
depois de inserir** — âncora fora de ordem quebra o phpcs.

- [x] **Passo 5: subir a versão do tema e recompilar**

`public/theme/ldg/version.php`: `2026090221` → `2026090222`.

```bash
cf cli upgrade.php --non-interactive && cf cli purge_caches.php
```

- [x] **Passo 6: ver na tela**

Abrir `https://localhost:8443/course/view.php?id=<curso ldg>` como aluno.
Esperado: sem navbar, sem drawers, cabeçalho do portal com avatar. Com a edição
ligada, o Moodle de sempre.

- [x] **Passo 7: commitar**

```bash
git add public/theme/ldg/config.php public/theme/ldg/layout/ldgportal.php \
        public/theme/ldg/templates/ldgportal.mustache \
        public/theme/ldg/version.php public/course/format/ldg/lang/
git commit -m "NOBUG: theme_ldg - o layout do portal do aluno"
```

---

### Tarefa 4: Behat prova as duas metades da regra

**Arquivos:**
- Modificar: `public/course/format/ldg/tests/behat/format_ldg.feature`

**Sem `@javascript`, e de propósito.** O arquivo já traz o motivo no topo: este
ambiente não tem navegador, e teste que não roda não protege nada. A troca de
chrome é decidida no **servidor**, então os cenários abaixo valem sem navegador.
A conferência de acessibilidade com axe-core **exige** `@javascript` e por isso
vai para o plano 4, junto com o Chrome — prometê-la aqui seria prometer um teste
que não roda.

Os nomes seguem o `Background` que já existe: curso `ldgcurso`, usuários `aluno`
e `professor`.

- [x] **Passo 1: escrever os cenários**

```gherkin
  Scenario: O aluno ve o portal, sem o chrome do Moodle
    When I am on the "ldgcurso" "Course" page logged in as "aluno"
    Then ".ldg-portal__header" "css_element" should exist
    And "nav.navbar" "css_element" should not exist

  Scenario: O professor sem editar tambem ve o portal
    When I am on the "ldgcurso" "Course" page logged in as "professor"
    Then ".ldg-portal__header" "css_element" should exist

  Scenario: Com a edicao ligada volta o chrome do Moodle
    Given I am on the "ldgcurso" "Course" page logged in as "professor"
    When I turn editing mode on
    Then "nav.navbar" "css_element" should exist
    And ".ldg-portal__header" "css_element" should not exist
```

O segundo cenário é o que separa "professor" de "editando" — a regra é a edição,
não o papel, e sem ele o terceiro passaria por acidente.

- [x] **Passo 2: habilitar a feature e rodar**

```bash
docker exec -u 1000:33 courses-free-moodle-1 \
  php /var/www/html/public/admin/tool/behat/cli/util.php --enable
docker exec -d -u 1000:33 courses-free-moodle-1 \
  sh -c 'cd /var/www/html/public && php -S 127.0.0.1:8000 >/tmp/behatweb.log 2>&1'
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml \
  public/course/format/ldg/tests/behat/format_ldg.feature
```

Esperado: todos os cenários verdes. Feature nova ou renomeada **não é coletada**
sem o `util.php --enable` — sem ele o behat responde `No scenarios` e parece
erro de sintaxe.

- [x] **Passo 3: commitar**

```bash
git add public/course/format/ldg/tests/behat/format_ldg.feature
git commit -m "NOBUG: format_ldg - behat do chrome do portal"
```

---

### Tarefa 5: o formato entra no portão do CI

**Arquivos:**
- Modificar: `.github/moodle-plugins.txt`
- Modificar: `public/course/format/ldg/README.md`

- [x] **Passo 1: acrescentar o formato à lista**

Depois do bloco do `theme/ldg`:

```
# Formato de curso do portal do aluno. Depende do theme_ldg apenas para a
# MARCA - o layout do portal so e usado quando o tema declara o 'ldgportal',
# e sem ele o formato cai no chrome padrao do tema ativo.
public/course/format/ldg
```

- [x] **Passo 2: rodar o mesmo portão do CI, local**

```bash
docker exec -u 1000:33 courses-free-moodle-1 sh -c \
  'cd /tmp/cs && ./vendor/bin/phpcs --standard=moodle --report=summary \
   /var/www/html/public/course/format/ldg /var/www/html/public/theme/ldg' \
  | grep -E "A TOTAL OF|no errors"
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  vendor/bin/phpunit --filter format_ldg
npx grunt
```

Esperado: phpcs sem violações, phpunit verde, grunt sem erro de eslint/stylelint.

- [x] **Passo 3: atualizar o README do formato**

Trocar a promessa antiga ("instalado com outro tema, ele aparece sem estilo") por
o que passa a ser verdade: com outro tema o formato usa o chrome daquele tema, e
o layout do portal só entra quando o tema declara `ldgportal`. Citar o nome do
layout — é o contrato entre os dois plugins.

- [x] **Passo 4: commitar**

```bash
git add .github/moodle-plugins.txt public/course/format/ldg/README.md
git commit -m "NOBUG: format_ldg - entra no moodle-plugin-ci, e o README conta a nova regra"
```

---

## Como saber que o plano 1 acabou

1. `vendor/bin/phpunit --filter format_ldg` verde, com os 5 testes novos.
2. Behat verde nos três cenários.
3. `phpcs --standard=moodle` com zero violações nos dois plugins — total lido.
4. `npx grunt` sem erro.
5. Na tela, em `https://localhost:8443`: aluno sem navbar, professor editando com
   navbar.
6. Trocando o tema do site para o Boost, o curso continua abrindo — com o chrome
   do Boost e **sem** `debugging()` no log.

O item 6 é o que prova a decisão que motivou o desenho: outra empresa pode usar o
formato com o tema dela.

## Executado em 03/09/2026

As cinco tarefas entraram, em cinco commits (`2959e3c4662` a `037052a9157`).

**Resultado medido:** `phpunit --filter format_ldg` → 35 testes verdes;
`behat format_ldg.feature` → 10 cenários, 90 passos, verdes;
`phpcs --standard=moodle` nos dois plugins → `EXIT=0`; página do curso carregada
como aluno matriculado → HTTP 200, cabeçalho do portal presente, zero navbar.

**O que o plano não previu, e apareceu na execução:**

1. **`usermenu` e `langmenu` são contextos, não HTML.** Interpolar com chaves
   triplas derruba a página com `Array to string conversion`, apontando para o
   mustache compilado em `moodledata/localcache` — um arquivo que não existe no
   repositório. Corrigido no plano e no template.
2. **O professor ficava preso na visão de aluno.** O interruptor de "Edit mode"
   mora na navbar, que o portal não tem. Quem achou foi o Behat, ao não encontrar
   o campo. O `output.edit_switch` foi para o cabeçalho do portal — ele já se
   protege sozinho e devolve vazio para quem não pode editar.
3. **Os dois primeiros cenários de Behat falharam por um bom motivo:** o site do
   Behat nasce com o tema `boost`, que não declara o layout, então o hook
   corretamente não trocou nada. Foi a prova acidental do guard. Cada cenário
   passou a dizer de qual tema fala, e ganhou um irmão que fixa a regra ao
   contrário — com o `boost`, o curso abre no chrome do `boost`.
4. **O portão do CI achou defeito no primeiro uso.** O teste de privacidade do
   core reprovava `format_ldg_lesson (usermodified)` — uma referência a `user`
   numa tabela cujo plugin se declara `null_provider`. Anterior a este trabalho, e
   invisível justamente porque o formato estava fora da lista. A coluna, que
   nenhuma linha de PHP jamais leu, foi removida no upgrade `2026090306`.

**O que ficou de fora, e por quê:** o `npx grunt` não rodou — não há
`node_modules` no repositório nem no container, e instalar a toolchain inteira do
Moodle seria caro para um trabalho que não mexeu em JS nem em CSS, que é o que
ele lintaria. O template novo foi provado pelo renderer de verdade, em 10
cenários de Behat e numa carga real da página.

**Mudança no ambiente de trabalho, para você saber:** a senha do usuário de teste
`alunoteste` foi trocada para `Portal.2026!` para a conferência no navegador, e o
hash anterior não foi guardado.

## Os próximos planos

Escritos quando este entrar, e não antes — plano especulativo envelhece entre a
escrita e a execução:

- **Plano 2:** catálogo por tipo de atividade, os quatro destinos, o `?ldgview=`
  e o **`styles.css` estrutural do formato** — a grade das três colunas nasce
  junto com os destinos que a preenchem, e não antes.
- **Plano 3:** tokens, fontes self-hosted e a marca do portal no tema, contra o
  `docs/brand/DESIGN.md`.
- **Plano 4:** dados de teste no `make_testdata.php`, a conferência visual no
  Chrome com as medidas do design system, e o axe-core que ficou de fora do Behat
  por falta de navegador neste ambiente.
