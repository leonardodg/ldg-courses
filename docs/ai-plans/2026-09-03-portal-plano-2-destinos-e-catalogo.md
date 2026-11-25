# Portal do aluno — Plano 2: destinos, catálogo e a grade

> **Para quem executa:** use `superpowers:executing-plans` (ou
> `subagent-driven-development`) para tocar tarefa a tarefa. Os passos usam
> `- [ ]` para marcar progresso.

**Meta:** o portal ganha os quatro destinos do mockup — Aulas, Materiais, Fórum e
Certificado —, descobertos pelo **tipo** da atividade, navegados por URL, e
dispostos nas três colunas com estrutura que vale em qualquer tema.

**Arquitetura:** um `catalog` varre o `modinfo` uma vez e classifica; um
`portalnav` resolve o destino corrente e monta só os destinos que **têm**
conteúdo; o `content.php` pergunta aos dois e o mustache desenha as três
superfícies. A grade vai num `styles.css` do próprio formato.

**Pilha:** Moodle 5.2, PHP 8.4, Bootstrap 5.3.3 do tema, PHPUnit, Behat.

Spec: [`2026-09-03-portal-do-aluno-format-ldg.md`](2026-09-03-portal-do-aluno-format-ldg.md) ·
Design system: [`../brand/DESIGN.md`](../brand/DESIGN.md) ·
Plano anterior: [`plano 1`](2026-09-03-portal-plano-1-chrome-e-layout.md)

## Restrições globais

- Worktree `format-course-ldg`, branch `fix/format-ldg-navegador`, stack offset 0
  (`courses-free-moodle-1`, `https://localhost:8443`).
- Código e comentários em **português sem acentos**; documentação e strings de
  idioma **com** acentos.
- Cabeçalho GPL em arquivo novo; `@copyright 2026 LeoDG <callme@leodg.dev>`.
- Strings em `en`, `pt_br` e `es`, em **ordem alfabética**.
- `phpcs --standard=moodle` com zero violações — **ler o TOTAL**.
- CLI como `-u 1000:33`. Nada de push.
- Bump de `version.php` a cada tarefa que mexa em `db/`, string ou template.

## O que já existe, e vai ser reaproveitado

| Peça | Onde | O que dá |
|---|---|---|
| `lessonlist` | `classes/output/courseformat/content/lessonlist.php` | módulos, aulas, progresso, conclusão, cadeado |
| `lessonviewer` | `.../content/lessonviewer.php` | o quadro embutido com `ldgembed=1` |
| `get_selected_cm()` | `lib.php:156` | resolve `?lesson=` com validação por `modinfo` e `uservisible` |
| `get_view_url()` | `lib.php:132` | já monta URL com `lesson`; ganha `ldgview` |
| `section_progress` | `classes/section_progress.php` | percentual por módulo |

## Descobertas que já valem como decisão

**Como saber que um material baixa em vez de abrir.** `mod_resource` e `mod_url`
gravam `customdata['display']` em `get_coursemodule_info()`
(`mod/resource/lib.php:265`, `mod/url/lib.php:246`), e a constante
`RESOURCELIB_DISPLAY_DOWNLOAD` é do **core** (`lib/resourcelib.php:38`), não do
módulo. Os mesmos módulos gravam `onclick` quando o material pede janela nova ou
popup. Então a regra do miolo sai de sinais públicos, sem o formato ler tabela de
outro plugin:

| Sinal no `cm_info` | O que o portal faz |
|---|---|
| `empty($cm->url)` | não é clicável (rótulo); só aparece a descrição |
| `!empty($cm->onclick)` | o módulo pediu janela própria → link para fora do quadro |
| `customdata['display'] == RESOURCELIB_DISPLAY_DOWNLOAD` | link de download |
| resto | abre no quadro embutido |

**A lista de aulas vai encolher.** Hoje `lessonlist` e `get_selected_cm()` tratam
**toda** atividade exibível como aula — inclusive o PDF e o fórum. Com o catálogo,
material, fórum e certificado saem da lista. Isso muda o comportamento atual e
**os testes existentes de `lessonlist` precisam ser lidos antes**, não depois.

## Estrutura de arquivos

| Arquivo | Responsabilidade |
|---|---|
| `classes/catalog.php` | classificar o `modinfo` em quatro baldes |
| `classes/portalnav.php` | destino corrente, destinos existentes, URLs |
| `classes/output/courseformat/content/materiallist.php` | exportar a lista de materiais |
| `classes/output/courseformat/content/lessonlist.php` | passa a receber só as aulas |
| `classes/output/courseformat/content.php` | pergunta ao catálogo e ao nav |
| `lib.php` | `get_view_url()` ganha `ldgview`; `get_selected_cm()` só entre aulas |
| `templates/local/content.mustache` | as três superfícies |
| `templates/local/content/portalnav.mustache` | menu, abas e barra de baixo |
| `templates/local/content/materiallist.mustache` | a lista de materiais |
| `styles.css` | a grade, em qualquer tema |

---

### Tarefa 1: o catálogo

**Arquivos:**
- Criar: `public/course/format/ldg/classes/catalog.php`
- Testar: `public/course/format/ldg/tests/catalog_test.php`

**Interfaces:**
- Produz: `\format_ldg\catalog::classify(\cm_info $cm): string` devolvendo uma de
  `catalog::AULA`, `catalog::MATERIAL`, `catalog::FORUM`, `catalog::CERTIFICADO`,
  `catalog::NENHUM`; e `new catalog(course_format $format)` com
  `get(string $tipo): array` (de `cm_info`, na ordem do curso) e
  `has(string $tipo): bool`. As tarefas 2, 3 e 4 usam esses três.

- [x] **Passo 1: escrever o teste que falha**

Criar `public/course/format/ldg/tests/catalog_test.php` (cabeçalho GPL e docblock
como nos outros testes do plugin):

```php
namespace format_ldg;

#[\PHPUnit\Framework\Attributes\CoversClass(\format_ldg\catalog::class)]
final class catalog_test extends \advanced_testcase {
    /**
     * Monta um curso com uma atividade de cada tipo que importa.
     *
     * @return array [curso, modinfo]
     */
    private function curso_completo(): array {
        $gerador = $this->getDataGenerator();
        $curso = $gerador->create_course(['format' => 'ldg', 'numsections' => 2]);

        $gerador->create_module('page', ['course' => $curso->id, 'section' => 1, 'name' => 'Aula um']);
        $gerador->create_module('quiz', ['course' => $curso->id, 'section' => 1, 'name' => 'Prova']);
        $gerador->create_module('resource', ['course' => $curso->id, 'section' => 1, 'name' => 'Apostila']);
        $gerador->create_module('folder', ['course' => $curso->id, 'section' => 2, 'name' => 'Anexos']);
        $gerador->create_module('url', ['course' => $curso->id, 'section' => 2, 'name' => 'Link']);
        $gerador->create_module('forum', ['course' => $curso->id, 'section' => 2, 'name' => 'Duvidas']);
        $gerador->create_module('label', ['course' => $curso->id, 'section' => 1]);

        return [$curso, get_fast_modinfo($curso)];
    }

    /**
     * Cada tipo cai no balde certo.
     *
     * @return void
     */
    public function test_classifica_por_tipo(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$curso, $modinfo] = $this->curso_completo();
        $porNome = [];

        foreach ($modinfo->get_cms() as $cm) {
            $porNome[$cm->name] = catalog::classify($cm);
        }

        $this->assertSame(catalog::AULA, $porNome['Aula um']);
        $this->assertSame(catalog::AULA, $porNome['Prova']);
        $this->assertSame(catalog::MATERIAL, $porNome['Apostila']);
        $this->assertSame(catalog::MATERIAL, $porNome['Anexos']);
        $this->assertSame(catalog::MATERIAL, $porNome['Link']);
        $this->assertSame(catalog::FORUM, $porNome['Duvidas']);
    }

    /**
     * Rotulo nao entra em balde nenhum: nao tem pagina para abrir.
     *
     * @return void
     */
    public function test_rotulo_fica_de_fora(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$curso, $modinfo] = $this->curso_completo();

        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->modname === 'label') {
                $this->assertSame(catalog::NENHUM, catalog::classify($cm));
                return;
            }
        }

        $this->fail('O rotulo nao foi criado.');
    }

    /**
     * O catalogo separa o curso inteiro, na ordem em que ele aparece.
     *
     * @return void
     */
    public function test_separa_o_curso(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$curso, ] = $this->curso_completo();
        $catalogo = new catalog(course_get_format($curso));

        $this->assertCount(2, $catalogo->get(catalog::AULA));
        $this->assertCount(3, $catalogo->get(catalog::MATERIAL));
        $this->assertCount(1, $catalogo->get(catalog::FORUM));
        $this->assertTrue($catalogo->has(catalog::MATERIAL));
        $this->assertFalse($catalogo->has(catalog::CERTIFICADO));
    }

    /**
     * Curso vazio nao tem destino nenhum, e isso nao pode ser um erro.
     *
     * @return void
     */
    public function test_curso_vazio(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $curso = $this->getDataGenerator()->create_course(['format' => 'ldg']);
        $catalogo = new catalog(course_get_format($curso));

        $this->assertSame([], $catalogo->get(catalog::AULA));
        $this->assertFalse($catalogo->has(catalog::AULA));
    }

    /**
     * Atividade escondida nao aparece para o aluno.
     *
     * @return void
     */
    public function test_atividade_escondida_fica_de_fora(): void {
        $this->resetAfterTest();

        $gerador = $this->getDataGenerator();
        $curso = $gerador->create_course(['format' => 'ldg']);
        $aluno = $gerador->create_user();
        $gerador->enrol_user($aluno->id, $curso->id, 'student');

        $gerador->create_module('page', ['course' => $curso->id, 'section' => 1, 'name' => 'Visivel']);
        $gerador->create_module('page', [
            'course' => $curso->id,
            'section' => 1,
            'name' => 'Escondida',
            'visible' => 0,
        ]);

        $this->setUser($aluno);
        $catalogo = new catalog(course_get_format($curso));
        $aulas = $catalogo->get(catalog::AULA);

        $this->assertCount(1, $aulas);
        $this->assertSame('Visivel', reset($aulas)->name);
    }
}
```

- [x] **Passo 2: rodar e ver falhar**

```bash
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  vendor/bin/phpunit public/course/format/ldg/tests/catalog_test.php
```

Esperado: `Class "format_ldg\catalog" not found`, cinco vezes.

- [x] **Passo 3: escrever a implementação**

Criar `public/course/format/ldg/classes/catalog.php`:

```php
namespace format_ldg;

use cm_info;
use core_courseformat\base as course_format;

/**
 * O curso separado por PAPEL da atividade, e nao por secao.
 *
 * O portal tem quatro destinos e o professor nao configura nenhum: o papel sai
 * do TIPO da atividade. E decisao de projeto - tela de aluno que depende de
 * configuracao certa e tela que um dia aparece vazia.
 *
 * O que esta classe NAO faz: nao pergunta ao local_marketplace quem comprou o
 * que. O bloqueio chega pronto em cm_info->uservisible.
 */
class catalog {
    /** @var string Aula: e o que sobra, e o padrao do portal. */
    public const AULA = 'lessons';

    /** @var string Material de apoio. */
    public const MATERIAL = 'materials';

    /** @var string Forum de alunos. */
    public const FORUM = 'forum';

    /** @var string Certificado. */
    public const CERTIFICADO = 'certificate';

    /** @var string Nao entra em destino nenhum. */
    public const NENHUM = 'none';

    /** @var string[] Modulos que sao material de apoio. */
    public const MODS_MATERIAL = ['resource', 'folder', 'url'];

    /** @var array<string, cm_info[]> Preenchido uma vez, no construtor. */
    protected array $baldes;

    /**
     * Varre o curso UMA vez.
     *
     * @param course_format $format
     */
    public function __construct(course_format $format) {
        $this->baldes = [
            self::AULA => [],
            self::MATERIAL => [],
            self::FORUM => [],
            self::CERTIFICADO => [],
        ];

        $modinfo = $format->get_modinfo();

        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$format->is_section_visible($section)) {
                continue;
            }

            foreach ($modinfo->sections[$section->sectionnum] ?? [] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                $tipo = self::classify($cm);

                if ($tipo === self::NENHUM) {
                    continue;
                }

                $this->baldes[$tipo][$cm->id] = $cm;
            }
        }
    }

    /**
     * O papel de uma atividade.
     *
     * O par is_visible_on_course_page() + is_of_type_that_can_display() e o
     * mesmo que o core usa e que o lessonlist ja usava: e o segundo que mantem o
     * banco de questoes fora, porque o mod_qbank declara FEATURE_CAN_DISPLAY
     * como false.
     *
     * @param cm_info $cm
     * @return string
     */
    public static function classify(cm_info $cm): string {
        // A ausencia de URL e o primeiro corte, e o unico que pega o ROTULO.
        // NAO da para usar is_of_type_that_can_display() para isso: ele e
        // plugin_supports(FEATURE_CAN_DISPLAY, true), com default TRUE, e o
        // mod_label nunca declara a flag - entao rotulo passaria por aula.
        if (empty($cm->url)) {
            return self::NENHUM;
        }

        if (!$cm->is_visible_on_course_page() || !$cm->is_of_type_that_can_display()) {
            return self::NENHUM;
        }

        if ($cm->modname === 'forum') {
            return self::FORUM;
        }

        if ($cm->modname === 'customcert') {
            return self::CERTIFICADO;
        }

        if (in_array($cm->modname, self::MODS_MATERIAL, true)) {
            return self::MATERIAL;
        }

        return self::AULA;
    }

    /**
     * As atividades de um destino, na ordem do curso.
     *
     * @param string $tipo
     * @return cm_info[]
     */
    public function get(string $tipo): array {
        return $this->baldes[$tipo] ?? [];
    }

    /**
     * Se o destino tem conteudo.
     *
     * E o que decide se ele aparece no menu: destino vazio nao vira aba vazia.
     *
     * @param string $tipo
     * @return bool
     */
    public function has(string $tipo): bool {
        return !empty($this->baldes[$tipo]);
    }
}
```

- [x] **Passo 4: rodar e ver passar**

```bash
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  vendor/bin/phpunit public/course/format/ldg/tests/catalog_test.php
```

Esperado: `OK (5 tests)`.

- [x] **Passo 5: phpcs e commit**

```bash
docker exec -u 1000:33 courses-free-moodle-1 sh -c \
  'cd /tmp/cs && ./vendor/bin/phpcs --standard=moodle -p /var/www/html/public/course/format/ldg; echo "EXIT=$?"'
git add public/course/format/ldg/classes/catalog.php \
        public/course/format/ldg/tests/catalog_test.php
git commit -m "NOBUG: format_ldg - o catalogo do curso por papel da atividade"
```

---

### Tarefa 2: a navegação por URL

**Arquivos:**
- Criar: `public/course/format/ldg/classes/portalnav.php`
- Modificar: `public/course/format/ldg/lib.php:132` (`get_view_url`)
- Testar: `public/course/format/ldg/tests/portalnav_test.php`

**Interfaces:**
- Consome: `catalog::AULA|MATERIAL|FORUM|CERTIFICADO` e `catalog::has()`.
- Produz:
  `new portalnav(course_format $format, catalog $catalog, string $pedido, ?cm_info $selected = null)`
  com `current(): string` e `destinations(): array` — cada item com
  `key`, `label`, `url`, `active`.

**A aula selecionada viaja junto.** Trocar para Materiais e voltar para Aulas tem
que devolver o aluno à **mesma** aula, não à primeira do curso. Por isso o
`portalnav` recebe a `cm_info` em foco e a repete no `lesson` de cada URL.

- [x] **Passo 1: `get_view_url` passa a carregar o destino**

Em `lib.php`, dentro de `get_view_url()`, depois do bloco do `lesson`:

```php
        // O destino do portal viaja na URL, como a aula. Assim o botao voltar,
        // o favorito e o Ctrl+clique funcionam sem uma linha de JavaScript.
        if (!empty($options['ldgview'])) {
            $url->param('ldgview', (string) $options['ldgview']);
        }
```

- [x] **Passo 2: escrever o teste que falha**

`public/course/format/ldg/tests/portalnav_test.php`:

```php
namespace format_ldg;

#[\PHPUnit\Framework\Attributes\CoversClass(\format_ldg\portalnav::class)]
final class portalnav_test extends \advanced_testcase {
    /**
     * Curso com aula, material e forum - sem certificado.
     *
     * @return \stdClass
     */
    private function curso(): \stdClass {
        $gerador = $this->getDataGenerator();
        $curso = $gerador->create_course(['format' => 'ldg']);

        $gerador->create_module('page', ['course' => $curso->id, 'section' => 1, 'name' => 'Aula um']);
        $gerador->create_module('resource', ['course' => $curso->id, 'section' => 1, 'name' => 'Apostila']);
        $gerador->create_module('forum', ['course' => $curso->id, 'section' => 1, 'name' => 'Duvidas']);

        return $curso;
    }

    /**
     * Monta o nav para um pedido de destino.
     *
     * @param \stdClass $curso
     * @param string $pedido
     * @return portalnav
     */
    private function nav(\stdClass $curso, string $pedido): portalnav {
        $format = course_get_format($curso);

        return new portalnav($format, new catalog($format), $pedido, $format->get_selected_cm());
    }

    /**
     * Sem pedido, o destino e a aula.
     *
     * @return void
     */
    public function test_padrao_e_aulas(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(catalog::AULA, $this->nav($this->curso(), '')->current());
    }

    /**
     * Pedido desconhecido cai em aulas, sem erro.
     *
     * @return void
     */
    public function test_pedido_desconhecido_cai_em_aulas(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(catalog::AULA, $this->nav($this->curso(), 'inventado')->current());
    }

    /**
     * Pedido de destino que o curso nao tem tambem cai em aulas.
     *
     * @return void
     */
    public function test_destino_sem_conteudo_cai_em_aulas(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(catalog::AULA, $this->nav($this->curso(), catalog::CERTIFICADO)->current());
    }

    /**
     * Destino existente e respeitado.
     *
     * @return void
     */
    public function test_destino_existente_vale(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(catalog::MATERIAL, $this->nav($this->curso(), catalog::MATERIAL)->current());
    }

    /**
     * O menu so lista o que existe, e marca o corrente.
     *
     * @return void
     */
    public function test_menu_so_tem_o_que_existe(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $destinos = $this->nav($this->curso(), catalog::MATERIAL)->destinations();
        $chaves = array_column($destinos, 'key');

        $this->assertSame([catalog::AULA, catalog::MATERIAL, catalog::FORUM], $chaves);
        $this->assertNotContains(catalog::CERTIFICADO, $chaves);

        foreach ($destinos as $destino) {
            $this->assertSame($destino['key'] === catalog::MATERIAL, $destino['active']);
            $this->assertStringContainsString('ldgview=' . $destino['key'], $destino['url']);
        }
    }

    /**
     * Trocar de destino nao pode perder a aula em foco.
     *
     * Sem isto, ir em Materiais e voltar para Aulas jogaria o aluno na primeira
     * aula do curso - e ele estava na decima.
     *
     * @return void
     */
    public function test_a_aula_em_foco_viaja_junto(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $curso = $this->curso();
        $format = course_get_format($curso);
        $aula = $format->get_selected_cm();

        $this->assertNotNull($aula);

        $destinos = (new portalnav($format, new catalog($format), catalog::MATERIAL, $aula))->destinations();

        foreach ($destinos as $destino) {
            $this->assertStringContainsString('lesson=' . $aula->id, $destino['url']);
        }
    }
}
```

- [x] **Passo 3: rodar e ver falhar**

```bash
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  vendor/bin/phpunit public/course/format/ldg/tests/portalnav_test.php
```

Esperado: `Class "format_ldg\portalnav" not found`.

- [x] **Passo 4: escrever a implementação**

`public/course/format/ldg/classes/portalnav.php`:

```php
namespace format_ldg;

use cm_info;
use core_courseformat\base as course_format;

/**
 * A navegacao do portal: quatro destinos, um corrente.
 *
 * Sao tres superficies para a MESMA navegacao - menu a esquerda no desktop,
 * abas no meio e barra embaixo no celular. Por isso a lista de destinos e
 * montada uma vez e desenhada tres.
 *
 * O estado viaja na URL, e nao no cliente: botao voltar, favorito e Ctrl+clique
 * saem de graca, e a pagina continua funcionando sem JavaScript.
 */
class portalnav {
    /** @var string[] A ordem em que os destinos aparecem, sempre. */
    public const ORDEM = [catalog::AULA, catalog::MATERIAL, catalog::CERTIFICADO, catalog::FORUM];

    /** @var course_format */
    protected course_format $format;

    /** @var catalog */
    protected catalog $catalog;

    /** @var string */
    protected string $current;

    /** @var cm_info|null A aula em foco, que viaja junto nos links. */
    protected ?cm_info $selected;

    /**
     * Construtor.
     *
     * @param course_format $format
     * @param catalog $catalog
     * @param string $pedido O que veio na URL, cru.
     * @param cm_info|null $selected A aula em foco, para nao se perder na troca.
     */
    public function __construct(
        course_format $format,
        catalog $catalog,
        string $pedido,
        ?cm_info $selected = null
    ) {
        $this->format = $format;
        $this->catalog = $catalog;
        $this->selected = $selected;

        // Pedido invalido, desconhecido ou de destino vazio cai em aulas. Nao e
        // erro: URL velha, link colado e curso que perdeu o forum sao normais, e
        // nenhum deles justifica uma tela de erro para o aluno.
        $valido = in_array($pedido, self::ORDEM, true) && $catalog->has($pedido);

        $this->current = $valido ? $pedido : catalog::AULA;
    }

    /**
     * O destino corrente.
     *
     * @return string
     */
    public function current(): string {
        return $this->current;
    }

    /**
     * Os destinos que existem neste curso.
     *
     * Destino sem conteudo nao aparece - e o que desarma o risco de classificar
     * por tipo sem o professor configurar nada.
     *
     * @return array
     */
    public function destinations(): array {
        $destinos = [];

        // A aula em foco viaja em TODOS os links: ir em Materiais e voltar tem
        // que devolver a mesma aula, e nao a primeira do curso.
        $opcoes = [];

        if ($this->selected !== null) {
            $opcoes['lesson'] = $this->selected->id;
        }

        foreach (self::ORDEM as $chave) {
            if (!$this->catalog->has($chave)) {
                continue;
            }

            $destinos[] = [
                'key' => $chave,
                'label' => get_string('view' . $chave, 'format_ldg'),
                'url' => $this->format->get_view_url(null, $opcoes + ['ldgview' => $chave])->out(false),
                'active' => $chave === $this->current,
            ];
        }

        return $destinos;
    }
}
```

- [x] **Passo 5: as strings dos destinos, nos três idiomas**

Em ordem alfabética, nos três arquivos. `en`:

```php
$string['viewcertificate'] = 'Certificate';
$string['viewforum'] = 'Student forum';
$string['viewlessons'] = 'Watch lessons';
$string['viewmaterials'] = 'Support material';
```

`pt_br`: `'Certificado'`, `'Fórum de alunos'`, `'Assistir aulas'`,
`'Material de apoio'`. `es`: `'Certificado'`, `'Foro de estudiantes'`,
`'Ver clases'`, `'Material de apoyo'`.

Suba `version.php` (string nova exige bump para o cache de idioma), rode
`cf cli upgrade.php --non-interactive` e `cf cli purge_caches.php`.

- [x] **Passo 6: rodar, phpcs e commit**

```bash
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  vendor/bin/phpunit public/course/format/ldg/tests/portalnav_test.php
docker exec -u 1000:33 courses-free-moodle-1 sh -c \
  'cd /tmp/cs && ./vendor/bin/phpcs --standard=moodle -p /var/www/html/public/course/format/ldg; echo "EXIT=$?"'
git add public/course/format/ldg/classes/portalnav.php \
        public/course/format/ldg/tests/portalnav_test.php \
        public/course/format/ldg/lib.php public/course/format/ldg/lang \
        public/course/format/ldg/version.php
git commit -m "NOBUG: format_ldg - os quatro destinos do portal, navegados por URL"
```

---

### Tarefa 3: a aula deixa de ser tudo

**Arquivos:**
- Modificar: `public/course/format/ldg/lib.php` (`get_selected_cm`)
- Modificar: `public/course/format/ldg/classes/output/courseformat/content/lessonlist.php`
- Testar: `public/course/format/ldg/tests/lessonlist_selection_test.php` (novo)

**Interfaces:**
- Consome: `catalog::classify()`.
- Produz: `get_selected_cm()` só devolve `cm_info` classificado como `AULA`; a
  lista de aulas idem.

**Por que esta tarefa existe:** hoje toda atividade exibível é aula. Sem esta
mudança, a apostila aparece na lista de aulas **e** em Materiais, e abrir o curso
pode cair num PDF.

- [x] **Passo 1: o teste que fixa a regra**

```php
namespace format_ldg;

#[\PHPUnit\Framework\Attributes\CoversClass(\format_ldg\catalog::class)]
final class lessonlist_selection_test extends \advanced_testcase {
    /**
     * Material nao pode ser escolhido como aula em foco.
     *
     * @return void
     */
    public function test_material_nao_vira_aula_em_foco(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $gerador = $this->getDataGenerator();
        $curso = $gerador->create_course(['format' => 'ldg']);
        $material = $gerador->create_module('resource', [
            'course' => $curso->id, 'section' => 1, 'name' => 'Apostila',
        ]);
        $gerador->create_module('page', [
            'course' => $curso->id, 'section' => 1, 'name' => 'Aula um',
        ]);

        // Pede o material pela URL, como faria um link velho.
        $_GET['lesson'] = $material->cmid;
        $selecionada = course_get_format($curso)->get_selected_cm();
        unset($_GET['lesson']);

        $this->assertNotNull($selecionada);
        $this->assertSame('Aula um', $selecionada->name);
    }
}
```

- [x] **Passo 2: rodar e ver falhar**

Esperado: falha com `Apostila`, provando que hoje o material é aceito como aula.

- [x] **Passo 3: filtrar nos dois lugares**

Em `lib.php`, dentro do laço de `get_selected_cm()`, logo depois de pegar `$cm`:

```php
                // Material, forum e certificado tem destino proprio no portal.
                // Sem esta linha, um link velho para a apostila abriria o PDF no
                // lugar da aula, e a apostila apareceria nas duas listas.
                if (catalog::classify($cm) !== catalog::AULA) {
                    continue;
                }
```

E o mesmo filtro em `lessonlist::export_lessons()`, trocando o par de testes de
visibilidade que já está lá pela chamada única a `catalog::classify()` — a regra
passa a existir num lugar só.

- [x] **Passo 4: rodar a suíte inteira do formato**

```bash
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  vendor/bin/phpunit --filter format_ldg
```

Esperado: verde. **Se algum teste antigo de `lessonlist` reprovar**, leia-o antes
de mexer: ele pode estar fixando o comportamento antigo de propósito, e aí a
conversa é sobre o teste, não sobre o código.

- [x] **Passo 5: phpcs e commit**

```bash
git add public/course/format/ldg/lib.php \
        public/course/format/ldg/classes/output/courseformat/content/lessonlist.php \
        public/course/format/ldg/tests/lessonlist_selection_test.php
git commit -m "NOBUG: format_ldg - material e forum saem da lista de aulas"
```

---

### Tarefa 4: o miolo por destino

**Arquivos:**
- Criar: `classes/output/courseformat/content/materiallist.php`
- Criar: `templates/local/content/materiallist.mustache`
- Criar: `templates/local/content/portalnav.mustache`
- Modificar: `classes/output/courseformat/content.php`
- Modificar: `templates/local/content.mustache`
- Testar: `tests/materiallist_test.php`

**Interfaces:**
- Consome: `catalog`, `portalnav`, `lessonviewer`.
- Produz: contexto do template com `portalnav`, `lessonlist`, `materiallist`,
  `lessonviewer` e a chave `view` (o destino corrente).

- [x] **Passo 1: o teste da regra do material**

```php
namespace format_ldg\output\courseformat\content;

#[\PHPUnit\Framework\Attributes\CoversClass(\format_ldg\output\courseformat\content\materiallist::class)]
final class materiallist_test extends \advanced_testcase {
    /**
     * Arquivo com download forcado vira link, e nao quadro embutido.
     *
     * Abrir download dentro de iframe dispara o download e deixa a tela em
     * branco - o aluno acha que quebrou.
     *
     * @return void
     */
    public function test_download_forcado_nao_abre_no_quadro(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();

        $gerador = $this->getDataGenerator();
        $curso = $gerador->create_course(['format' => 'ldg']);
        $gerador->create_module('resource', [
            'course' => $curso->id,
            'section' => 1,
            'name' => 'Apostila',
            'display' => RESOURCELIB_DISPLAY_DOWNLOAD,
        ]);

        $format = course_get_format($curso);
        $lista = new materiallist($format, new \format_ldg\catalog($format));
        $dados = $lista->export_for_template($PAGE->get_renderer('core'));

        $material = reset($dados->materials);

        $this->assertTrue($material->isdownload);
        $this->assertFalse($material->inframe);
    }

    /**
     * Pasta abre no quadro, porque tem pagina propria.
     *
     * @return void
     */
    public function test_pasta_abre_no_quadro(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();

        $gerador = $this->getDataGenerator();
        $curso = $gerador->create_course(['format' => 'ldg']);
        $gerador->create_module('folder', [
            'course' => $curso->id, 'section' => 1, 'name' => 'Anexos',
        ]);

        $format = course_get_format($curso);
        $lista = new materiallist($format, new \format_ldg\catalog($format));
        $dados = $lista->export_for_template($PAGE->get_renderer('core'));

        $material = reset($dados->materials);

        $this->assertFalse($material->isdownload);
        $this->assertTrue($material->inframe);
    }
}
```

- [x] **Passo 2: rodar e ver falhar**, depois escrever o `materiallist`

O coração da classe é a regra da tabela de descobertas:

```php
        $abrefora = !empty($cm->onclick);
        $display = $cm->customdata['display'] ?? null;
        $baixa = ((int) $display === RESOURCELIB_DISPLAY_DOWNLOAD);

        $item = (object) [
            'cmid' => $cm->id,
            'name' => $cm->get_formatted_name(),
            'modname' => $cm->modname,
            'iconurl' => $cm->get_icon_url()->out(false),
            'url' => $cm->url ? $cm->url->out(false) : '',
            'isdownload' => $baixa,
            // So entra no quadro o que tem pagina propria e nao pediu janela
            // nova. O resto e link, e o navegador resolve.
            'inframe' => !$baixa && !$abrefora && !empty($cm->url),
            'section' => $format->get_section_name($secao),
        ];
```

`require_once($CFG->libdir . '/resourcelib.php')` no topo, porque a constante
vive lá e o formato não a herda de lugar nenhum.

- [x] **Passo 3: o `content.php` passa a perguntar**

```php
        $catalogo = new catalog($this->format);
        $pedido = optional_param('ldgview', '', PARAM_ALPHA);
        // A aula em foco ja e resolvida aqui em cima, no $selecionada que o
        // lessonviewer usa - e a mesma que vai nos links do menu.
        $nav = new portalnav($this->format, $catalogo, $pedido, $selecionada);

        $data->portalnav = ['destinations' => $nav->destinations()];
        $data->view = $nav->current();
        $data->islessons = $nav->current() === catalog::AULA;
        $data->ismaterials = $nav->current() === catalog::MATERIAL;
```

Para Fórum e Certificado o miolo é o mesmo `lessonviewer`, recebendo o primeiro
`cm_info` do balde — o quadro embutido já sabe desenhar qualquer atividade, e é
por isso que não há classe nova para eles.

- [x] **Passo 4: os templates**

`portalnav.mustache` desenha a **mesma** lista três vezes, e quem some é o CSS.
Item é `<a>` com `href`, nunca `<button>`: é navegação de verdade, e o
Ctrl+clique tem que abrir noutra aba.

```html
{{!
    A navegacao do portal, nas tres superficies do mockup: menu a esquerda no
    desktop, abas no meio e barra embaixo no celular. E a MESMA lista - o
    styles.css e que esconde duas delas em cada largura.

    ARMADILHA: nao escreva chaves duplas de mustache dentro deste comentario.

    @template format_ldg/local/content/portalnav

    Example context (json):
    {
        "destinations": [
            {"key": "lessons", "label": "Assistir aulas", "url": "#", "active": true},
            {"key": "materials", "label": "Material de apoio", "url": "#", "active": false}
        ]
    }
}}
<nav class="ldg-portal__nav" aria-label="{{#str}}portalnav, format_ldg{{/str}}">
    {{#destinations}}
        <a class="ldg-portal__navitem ldg-portal__navitem--{{key}} {{#active}}is-active{{/active}}"
           href="{{{url}}}" {{#active}}aria-current="page"{{/active}}>{{label}}</a>
    {{/destinations}}
</nav>

<nav class="ldg-portal__tabs" aria-label="{{#str}}portalnav, format_ldg{{/str}}">
    {{#destinations}}
        <a class="ldg-portal__tab {{#active}}is-active{{/active}}"
           href="{{{url}}}" {{#active}}aria-current="page"{{/active}}>{{label}}</a>
    {{/destinations}}
</nav>

<nav class="ldg-portal__bottomnav" aria-label="{{#str}}portalnav, format_ldg{{/str}}">
    {{#destinations}}
        <a class="ldg-portal__bottomitem ldg-portal__bottomitem--{{key}} {{#active}}is-active{{/active}}"
           href="{{{url}}}" {{#active}}aria-current="page"{{/active}}>{{label}}</a>
    {{/destinations}}
</nav>
```

Isso pede mais uma string, `portalnav` — "Navegação do curso" / "Course
navigation" / "Navegación del curso" —, porque três `nav` sem rótulo na mesma
página é exatamente o que o axe-core reprova no plano 4.

Em `content.mustache`, o corpo passa a ter três regiões nomeadas:
`.ldg-portal__aside--nav`, `.ldg-portal__main`, `.ldg-portal__aside--index`, e o
miolo escolhe entre `lessonviewer` e `materiallist` pelas chaves `islessons` e
`ismaterials`.

- [x] **Passo 5: rodar, ver na tela e commitar**

```bash
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  vendor/bin/phpunit --filter format_ldg
cf cli purge_caches.php
```

Abrir `https://localhost:8443/course/view.php?id=13` e conferir os quatro
destinos, inclusive `?ldgview=materials`.

---

### Tarefa 5: a grade que vale em qualquer tema

**Arquivos:**
- Criar: `public/course/format/ldg/styles.css`
- Modificar: `public/theme/ldg/scss/ldg/_format.scss`
- Modificar: `tests/behat/format_ldg.feature`

**Interfaces:**
- Consome: as classes de região da tarefa 4.
- Produz: o layout de três colunas sem depender do tema.

- [x] **Passo 1: a estrutura, no formato**

`styles.css` — só o que é **estrutura**, nunca marca. Sem cor, sem fonte, sem
sombra. As medidas saem do `docs/brand/DESIGN.md`:

```css
/* Mobile primeiro: coluna unica. As colunas so entram nos pontos de virada. */
.ldg-portal__body { display: flex; flex-direction: column; gap: 1rem; }
.ldg-portal__aside--nav { display: none; }
.ldg-portal__aside--index { order: 2; }
.ldg-portal__main { order: 1; min-width: 0; }
.ldg-portal__bottomnav { position: fixed; inset: auto 0 0 0; height: 60px; display: flex; }
.ldg-portal__tabs { display: flex; }

@media (min-width: 992px) {
    .ldg-portal__body { flex-direction: row; align-items: flex-start; }
    .ldg-portal__aside--nav { display: block; flex: 0 0 280px; position: sticky; top: 56px; }
    .ldg-portal__bottomnav, .ldg-portal__tabs { display: none; }
}

@media (min-width: 1200px) {
    .ldg-portal__aside--index { flex: 0 0 360px; position: sticky; top: 56px; }
}
```

O `styles.css` do plugin é carregado pelo Moodle para **qualquer** tema — é isso
que faz o portal ficar cinza mas usável fora do `theme_ldg`.

- [x] **Passo 2: o Behat da degradação**

Acrescentar ao cenário do tema `boost`, que já existe:

```gherkin
    And ".ldg-portal__body" "css_element" should exist
```

Prova que a estrutura sobrevive sem o tema — que é a razão de ela morar aqui.

- [x] **Passo 3: rodar tudo e commitar**

```bash
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  vendor/bin/phpunit --filter format_ldg
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml \
  public/course/format/ldg/tests/behat/format_ldg.feature
```

---

## Como saber que o plano 2 acabou

1. `phpunit --filter format_ldg` verde, com `catalog`, `portalnav`,
   `lessonlist_selection` e `materiallist` novos.
2. Behat verde, incluindo a estrutura sobrevivendo no tema `boost`.
3. `phpcs` com zero violações — total lido.
4. Na tela: os quatro destinos aparecem só quando têm conteúdo; `?ldgview=`
   inválido cai em Aulas sem erro; a apostila **não** aparece na lista de aulas.
5. Em 1280px, três colunas; abaixo de 992px, coluna única com barra embaixo.

## Executado em 03/09/2026

As cinco tarefas entraram, em cinco commits (`d72eacb7938` a `e0853a8c4f7`).

**Resultado medido:** `phpunit --filter format_ldg` → 52 testes;
`behat format_ldg.feature` → 12 cenários, 113 passos; `phpcs` nos dois plugins →
`EXIT=0`; na página, os quatro destinos respondem e `?ldgview=inventado` cai em
Aulas sem erro.

**O que o plano errou, e foi corrigido na execução:**

1. **O `classify()` do plano deixaria rótulo virar aula.**
   `is_of_type_that_can_display()` é `plugin_supports(FEATURE_CAN_DISPLAY, true)`
   — com default **true** — e o `mod_label` nunca declara a flag. O corte certo é
   a ausência de URL: o que não abre não pode ser destino.
2. **Flex não dava conta.** Com as três superfícies de navegação como irmãs do
   miolo, só `grid-template-areas` põe cada uma no lugar sem mexer no HTML.
3. **Dois passos de Behat errados:** um inventado (`should not contain`) e um
   dependente de idioma — o site do Behat roda em inglês, e os cenários usavam
   rótulos em português. Passaram a mirar as classes dos destinos.
4. **Strings appendadas quebram a ordem alfabética**, que o phpcs reprova. Os
   três arquivos foram reordenados por inteiro, como manda o `CLAUDE.md`.

**Mudança de comportamento a registrar:** rótulo, material, fórum e certificado
**saíram da lista de aulas**. Antes, toda atividade exibível era aula.

## O que fica para o plano 3

Cor, tipografia, fontes self-hosted e a marca — tudo contra o
`docs/brand/DESIGN.md`. Até lá o portal fica **estruturalmente certo e
visualmente cru**, e isso é de propósito: misturar grade e marca no mesmo passo
esconde qual das duas quebrou.
