# Portal do aluno — Plano 5: os quatro ajustes do layout

> **Para quem executa:** use `superpowers:executing-plans`. Os passos usam `- [ ]`.

**Meta:** fechar as quatro diferenças que a comparação com o mockup expôs, com as
decisões que o usuário tomou em 03/09/2026.

**Origem:** as capturas do [plano 4](2026-09-03-portal-plano-4-conferencia-visual.md).

## As decisões, como foram dadas

| # | O que apareceu na captura | Decisão |
|---|---|---|
| 1 | "Requisitos de conclusão", "Ir para a atividade", "Aula 1 ▶" dentro do quadro | **fica** — é informação que o aluno precisa. Aplicar o design system e encaixar no layout |
| 2 | Selo PREMIUM do mockup | **não entra** — não há de onde tirar essa informação |
| 2b | Barra de progresso solta no topo | **corrigir** — vai para o cartão da área do aluno, na coluna esquerda |
| 3 | Falta a barra anterior/próxima | **acrescentar**, no layout que o desenho pede |
| 4 | "Redefinir a demonstração nessa página" | **remover** |

## Levantado antes de escrever

- **O link do item 4 é do User Tours**: `<a id="resetpagetour">` dentro de
  `.usertour`, que entra pelo `standard_after_main_region_html`. Não é do nosso
  código.
- **Dentro do quadro**, o que precisa de estilo tem nome certo:
  `.activity-header`, `.completion-info` /
  `.automatic-completion-conditions` e `.activity-navigation`, esta já com um
  `ldg-activity-navigation` do tema.
- **`body.ldg-embedded` já existe** e esconde navbar, cabeçalho, rodapé e
  drawers. É onde o estilo do item 1 entra.

## Um ponto em aberto, registrado

Os itens 1 e 3 põem a **mesma navegação em dois lugares**: "Aula 1 ▶" dentro do
quadro e a barra anterior/próxima no miolo. Foi o que o usuário pediu, e é o que
se implementa. Se na tela ficar redundante, esconder a do quadro é uma linha em
`body.ldg-embedded`.

## Restrições globais

- Worktree `format-course-ldg`, branch `fix/format-ldg-navegador`, stack offset 0.
- Comentários em português sem acentos; documentação e strings com acentos.
- Strings em `en`, `pt_br` e `es`, em ordem alfabética — **reordenar o arquivo**.
- Estrutura no `styles.css` do formato; marca no `_format.scss` do tema.
- Propriedades lógicas (`margin-inline`, `border-inline-start`).
- **Cor nova entra medida**, nos dois modos.
- **Editou `styles.css` de plugin? Suba a versão** — `purge_caches` não basta.
- Nada de push.

---

### Tarefa 1: a barra anterior/próxima

**Arquivos:**
- Criar: `classes/output/courseformat/content/lessonnav.php`
- Criar: `templates/local/content/lessonnav.mustache`
- Modificar: `classes/output/courseformat/content.php`, `templates/local/content.mustache`
- Modificar: `styles.css`, `_format.scss`, `lang/*`
- Testar: `tests/lessonnav_test.php`

**Interfaces:**
- Consome: `catalog`, e a aula em foco do `get_selected_cm()`.
- Produz: `new lessonnav(course_format $format, catalog $catalog, ?cm_info $selected)`
  exportando `hasprev`, `prev` (`{name, url}`), `hasnext`, `next`, e `position`
  (`{index, total, module}`).

O desenho pede três coisas na barra: **aula anterior**, **onde estou** ("Aula 2 de
5 · Módulo 2") e **próxima aula**.

- [x] **Passo 1: o teste que falha**

```php
namespace format_ldg\output\courseformat\content;

use format_ldg\catalog;

#[\PHPUnit\Framework\Attributes\CoversClass(\format_ldg\output\courseformat\content\lessonnav::class)]
final class lessonnav_test extends \advanced_testcase {
    /**
     * Monta um curso com tres aulas e devolve o nav da aula do meio.
     *
     * @param int $qual Indice da aula em foco, comecando em zero.
     * @return \stdClass
     */
    private function nav_da_aula(int $qual): \stdClass {
        global $PAGE;

        $gerador = $this->getDataGenerator();
        $curso = $gerador->create_course(['format' => 'ldg', 'numsections' => 2]);

        $cms = [];
        $cms[] = $gerador->create_module('page', ['course' => $curso->id, 'section' => 1, 'name' => 'Aula um']);
        $cms[] = $gerador->create_module('page', ['course' => $curso->id, 'section' => 1, 'name' => 'Aula dois']);
        $cms[] = $gerador->create_module('page', ['course' => $curso->id, 'section' => 2, 'name' => 'Aula tres']);

        $format = course_get_format($curso);
        $modinfo = $format->get_modinfo();
        $foco = $modinfo->get_cm($cms[$qual]->cmid);

        $nav = new lessonnav($format, new catalog($format), $foco);

        return $nav->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * No meio ha anterior e proxima, e a posicao conta o curso inteiro.
     *
     * @return void
     */
    public function test_no_meio_tem_os_dois_lados(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $d = $this->nav_da_aula(1);

        $this->assertTrue($d->hasprev);
        $this->assertSame('Aula um', $d->prev->name);
        $this->assertTrue($d->hasnext);
        $this->assertSame('Aula tres', $d->next->name);
        $this->assertSame(2, $d->position->index);
        $this->assertSame(3, $d->position->total);
    }

    /**
     * A primeira nao tem anterior; a ultima nao tem proxima. Sem isto a barra
     * mostraria um botao que leva a lugar nenhum.
     *
     * @return void
     */
    public function test_pontas_nao_inventam_vizinho(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $primeira = $this->nav_da_aula(0);
        $ultima = $this->nav_da_aula(2);

        $this->assertFalse($primeira->hasprev);
        $this->assertTrue($primeira->hasnext);
        $this->assertTrue($ultima->hasprev);
        $this->assertFalse($ultima->hasnext);
    }

    /**
     * Material e forum nao entram na sequencia: a barra e de AULAS.
     *
     * @return void
     */
    public function test_so_aula_entra_na_sequencia(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();

        $gerador = $this->getDataGenerator();
        $curso = $gerador->create_course(['format' => 'ldg']);
        $a = $gerador->create_module('page', ['course' => $curso->id, 'section' => 1, 'name' => 'Aula um']);
        $gerador->create_module('resource', ['course' => $curso->id, 'section' => 1, 'name' => 'Apostila']);
        $gerador->create_module('page', ['course' => $curso->id, 'section' => 1, 'name' => 'Aula dois']);

        $format = course_get_format($curso);
        $foco = $format->get_modinfo()->get_cm($a->cmid);
        $nav = new lessonnav($format, new catalog($format), $foco);
        $d = $nav->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame('Aula dois', $d->next->name);
        $this->assertSame(2, $d->position->total);
    }
}
```

- [x] **Passo 2: rodar e ver falhar** (classe inexistente).

- [x] **Passo 3: implementar o `lessonnav`**

A sequência sai do `catalog::AULA`, que já vem na ordem do curso e já respeita
visibilidade — não se refaz a varredura. A posição é o índice dentro dessa lista;
o módulo é o nome da seção da aula em foco. As URLs saem de
`get_view_url(null, ['lesson' => $cm->id])`, para o destino continuar em Aulas.

- [x] **Passo 4: template, estrutura e marca**

`lessonnav.mustache` com três âncoras — anterior, posição (texto, não link) e
próxima. No `content.mustache` entra **entre** o quadro e o resto, só no destino
Aulas. Estrutura (`display:flex`, `justify-content:space-between`, empilhar
abaixo de `sm`) no `styles.css`; cor, raio e a pílula do "próxima" no
`_format.scss`, com `--ldg-accent-fill` e `--ldg-on-accent`.

- [x] **Passo 5: strings nos três idiomas**, reordenando os arquivos:
  `lessonprev` ("Aula anterior"), `lessonnext` ("Próxima aula"),
  `lessonposition` ("Aula {$a->index} de {$a->total}").

- [x] **Passo 6: rodar tudo, subir versão, commitar.**

---

### Tarefa 2: o cartão da área do aluno, e o progresso no lugar certo

**Arquivos:**
- Modificar: `templates/local/content.mustache`, `styles.css`, `_format.scss`

Hoje a barra de progresso fica solta no topo, atravessando a página. No desenho
ela vive num cartão na coluna esquerda, acima da navegação.

- [x] **Passo 1: mover o bloco de progresso** para dentro da região `nav`, no
  `content.mustache`, envolvido por um cartão `.ldg-portal__student`.

- [x] **Passo 2: o cartão** ganha saudação com o primeiro nome do aluno e o
  percentual. **Sem selo PREMIUM** — não há de onde tirar essa informação, e
  inventar um selo de plano numa tela de curso seria mentir para o aluno.

- [x] **Passo 3: no celular** o cartão vai para cima do miolo (a coluna `nav` não
  existe abaixo de `lg`) — uma linha a mais no `grid-template-areas`.

- [x] **Passo 4: string** `studentgreeting` ("Olá, {$a}!") nos três idiomas.

- [x] **Passo 5: medir com a sonda** — a barra não pode voltar a atravessar a
  página, e o cartão não pode empurrar a coluna além dos 280px.

---

### Tarefa 3: o design system dentro do quadro

**Arquivos:**
- Modificar: `public/theme/ldg/scss/ldg/_format.scss`, bloco `body.ldg-embedded`

O conteúdo do quadro é a `view.php` da atividade, com o CSS do próprio tema — mas
os blocos do core chegam com a cara do Boost. Três alvos, e nenhum é do nosso
HTML:

```scss
body.ldg-embedded {
    // Os requisitos de conclusao sao INFORMACAO, e nao enfeite: e por eles que o
    // aluno sabe o que falta. Ficam, com a cara do portal.
    .activity-header,
    .completion-info,
    .automatic-completion-conditions {
        background-color: var(--ldg-surface);
        border: 1px solid var(--ldg-border);
        border-radius: 12px;
    }

    .activity-navigation {
        border-top: 1px solid var(--ldg-border);
    }
}
```

- [x] **Passo 1: escrever o estilo** usando os tokens, sem hex solto.
- [x] **Passo 2: conferir com a captura**, e medir o contraste de qualquer par
  novo.
- [x] **Passo 3: subir versão e commitar.**

---

### Tarefa 4: remover o link do User Tours

**Arquivos:**
- Modificar: `public/theme/ldg/scss/ldg/_format.scss`

O `<a id="resetpagetour">` vem do `tool_usertours`, pelo
`standard_after_main_region_html` — o mesmo bloco que traz o painel de mensagens,
que **fica**. Por isso a remoção é do elemento, e não do bloco:

```scss
body.format-ldg .usertour {
    display: none;
}
```

- [x] **Passo 1: escrever, e explicar no comentário** que o alvo é o link do
  tour, não o `standard_after_main_region_html` inteiro.
- [x] **Passo 2: conferir no DOM** que `#resetpagetour` não é mais visível e que
  o painel de mensagens continua presente.

---

### Tarefa 5: as laterais escondem, e o atalho fica

**Pedido em 03/09/2026:** "menu lateral podem ser escondidos mas precisa ter um
botão como atalho rápido para a próxima e anterior atividade do curso".

No mockup isso é o `DesktopQuickLessonNav`: prev com o **título** da aula
anterior, o indicador em mono no meio e o next em azul. Ou seja, **é a barra da
tarefa 1** — o que muda é que ela passa a ser o que sobra quando as laterais
somem, e por isso fica **grudada** no topo do miolo.

**Decisão do usuário sobre o como:** JavaScript de verdade, com **build
versionado junto do plugin**. A toolchain foi instalada (`npm ci`) e o
`npx grunt amd --root=public/course/format/ldg` compila.

**Arquivos:**
- Criar: `amd/src/aside.js` e o `amd/build/aside.min.js` **gerado**
- Modificar: `lib.php` (callback de preferências), `templates/local/content.mustache`,
  `styles.css`, `_format.scss`
- Testar: `tests/behat/format_ldg.feature`

- [x] **Passo 1: declarar a preferência de usuário**

Em `lib.php`, o callback que o core exige para aceitar a gravação por AJAX — sem
ele o `core_user_update_user_preferences` **recusa** e o estado volta no próximo
carregamento, com um 400 no console e nada na tela:

```php
function format_ldg_user_preferences(): array {
    return [
        'format_ldg_aside_hidden' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => false,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}
```

- [x] **Passo 2: o módulo AMD**

`amd/src/aside.js`: alterna a classe no container, grava a preferência e move o
foco. Sem dependência nova além de `core_user/repository` (ou
`core/user_preference`), que já vem com o Moodle.

O botão é `<button aria-expanded>` e a lateral tem `id` — é o par que o leitor de
tela usa para saber o que abriu.

- [x] **Passo 3: o estado inicial vem do servidor**

A preferência é lida no `content.php` e vira uma classe no HTML. Sem isso a
lateral aparece e some depois que o JS roda — o "flash" clássico.

- [x] **Passo 4: a barra de atalho fica grudada**

`position: sticky` no topo do miolo, com o `z-index` abaixo do cabeçalho. É o que
garante o pedido: escondeu as laterais, o atalho continua na tela.

- [x] **Passo 5: sem JavaScript, nada quebra**

O botão só aparece com JS ativo (a classe é posta pelo próprio módulo); sem JS a
lateral fica visível, que é o estado útil. A barra de atalho é feita de âncoras e
funciona igual.

- [x] **Passo 6: build, e ele vai versionado**

```bash
npx grunt amd --root=public/course/format/ldg
git add public/course/format/ldg/amd/src public/course/format/ldg/amd/build
```

- [x] **Passo 7: Behat** — esconder e mostrar, e o atalho continuar visível.
  Este cenário **precisa** de `@javascript`, e por isso pode não rodar neste
  ambiente; se não rodar, fica registrado no commit em vez de silenciosamente
  pulado.

---

### Tarefa 6: conferir e documentar

- [x] **Passo 1:** sonda visual nos dois viewports e dois modos — as medidas do
  plano 4 continuam válidas, mais a barra nova.
- [x] **Passo 2:** axe-core nos quatro destinos e dois modos: zero graves.
- [x] **Passo 3:** `phpunit`, `behat`, `phpcs`.
- [x] **Passo 4:** capturas novas para o usuário comparar.
- [x] **Passo 5:** README do formato (a barra de navegação e o cartão),
  `DESIGN.md` (o selo PREMIUM que **não** existe, e por quê), e o registro.

## Executado em 03/09/2026

Commits `6101b53dba7` a `eaacf090b27`. As seis tarefas entraram, mais duas que
nasceram durante a execução.

**O que a medição achou, e nenhuma leitura acharia:**

1. **Colisão de nome invisível.** O layout do tema punha `ldg-portal` como classe
   do `<body>`, e o formato usa `.ldg-portal` na div raiz — o recuo lateral era
   aplicado **duas vezes**, 48px contra os 24 do desenho. O body virou
   `ldg-portal-page`.
2. **O grid esticava.** Sem `grid-template-rows`, o navegador distribuía a altura
   do miolo entre as duas linhas e abria um vão de centenas de pixels entre o
   cartão e o menu.
3. **A sonda mentia sobre o modo de cor.** Ela trocava `data-bs-theme` no body, o
   que muda só a página de fora: o quadro da aula é outro documento e segue a
   **preferência** do usuário. A captura saía com portal escuro e aula branca, e
   "conferido nos dois modos" era meia verdade. Agora o modo vem da preferência.
4. **Os "builds" do AMD eram o fonte copiado.** Instalar a toolchain para compilar
   o `aside.js` revelou que `player.min.js` e `duration.min.js` eram cópias do
   `src`, sem minificar — 6481 → 2108 bytes depois do grunt real.
5. **Link externo não é download.** Montar os dados de teste expôs que
   `url_get_final_display_type()` põe `text/html` na lista de download: todo
   `mod_url` para uma página web resolvia para `DISPLAY_DOWNLOAD`, e a lista
   mostrava "(baixa o arquivo)" com o atributo `download` num link de site.

**Duas tarefas que nasceram na execução**, das suas observações:

- **O portal para quem gerencia.** Como manager, a navegação do curso sumia — era
  preciso *ligar a edição* para chegar em Notas. O cabeçalho passou a carregar a
  navegação secundária do core, com trava de capacidade.
- **O tema nas páginas de administração.** Cabeçalho encolhido a 370px e a barra
  fora de posição. Ver o README do tema.

**Medido no fim:** cabeçalho 56px, menu 280, índice 360, barra do gestor de 260 a
1425, zero rolagem horizontal, axe-core sem violação, 58 testes de unidade e 14
cenários de Behat.

## Como saber que o plano 5 acabou

1. Barra anterior/próxima no miolo, **grudada no topo**, sem botão que leve a
   lugar nenhum nas pontas — e ela continua na tela com as laterais escondidas.
2. Progresso dentro do cartão, na coluna esquerda — e no celular, acima do miolo.
3. Requisitos de conclusão e navegação da atividade com a cara do portal.
4. `#resetpagetour` fora da tela; painel de mensagens intacto.
5. Medidas, axe-core e testes verdes.
6. As laterais escondem e mostram, e a escolha **sobrevive à troca de aula**.
7. `amd/build` gerado pelo grunt e versionado junto do `amd/src`.
8. Capturas entregues.
