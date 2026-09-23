# Como se implementa uma feature aqui

O ciclo é sempre o mesmo, e a ordem não é estética: cada etapa existe porque a
seguinte custou caro sem ela. Teste antes do código porque teste escrito depois
descreve o que o código faz, não o que ele deveria fazer. Documentação junto do
commit porque documentação adiada não é escrita.

Este documento é o fio que liga os guias que já existem. Ele **não repete**:

| Assunto | Dono |
|---|---|
| padrão de código, fim de linha, o que o CI cobra | [`../coding-standards/README.md`](../coding-standards/README.md) |
| worktrees, portas, `.env`, VS Code | [`guia-worktrees.md`](guia-worktrees.md) e [`moodev.md`](moodev.md) |
| inicializar e rodar behat | [`behat.md`](behat.md) |
| medir o portal do aluno no Chrome | [`portal-conferencia-visual.md`](portal-conferencia-visual.md) |
| da worktree ao deploy | [`fluxo-de-contribuicao.md`](fluxo-de-contribuicao.md) |

O que está aqui é o resto: a sequência, e as armadilhas que hoje só vivem em
comentário de código ou na memória de quem já errou.

## O ciclo, em ordem

```
sincronizar upstream -> worktree -> DOCUMENTACAO -> teste vermelho -> codigo
  -> phpcs + grunt -> behat -> prova no navegador -> CODE REVIEW -> PR
```

**Code review entra antes de abrir o PR, depois que tudo já passou.** Não
substitui phpcs/behat/prova no navegador — pega o que essas etapas não pegam:
race condition, isolamento multi-tenant quebrado, duplicação de lógica entre
gateways, docblock que contradiz decisão registrada aqui. Rode `/code-review`
sobre o diff da feature e registre achados e correções em
`docs/codereview/`, no mesmo padrão usado na varredura de 2026-09-21 (ver
`docs/codereview/README.md`): um arquivo por escopo revisado, um item por
achado, com status (`pendente`/`em andamento`/`corrigido`/`descartado`) e a
correção aplicada. Isso vira o histórico consultável — sem isso, o que a
revisão encontrou e por que foi corrigido daquele jeito se perde depois do
merge.

**A documentação entra antes do código, não depois.** Não é disciplina: o que se
descobre investigando — o ponto exato do core que sustenta a solução, a armadilha
que custou uma rodada vermelha — se perde se ficar só na cabeça de quem
investigou. Escrito antes, ele guia a execução; escrito depois, ele vira resumo.

**Só abra o PR quando tudo estiver pronto.** Commit empurrado depois de o PR ser
merjeado fica órfão. Aconteceu quatro vezes neste projeto. Se precisar mostrar
progresso, mostre a branch — não o PR.

## Antes de ramificar

```bash
git -C dev fetch -q upstream MOODLE_502_STABLE
git -C dev rev-list --count origin/dev..upstream/MOODLE_502_STABLE
```

Zero, siga. Mais que zero, traga o upstream para o `dev` **antes** de criar a
worktree. Não é sugestão: este projeto acompanha o Moodle em vez de forkar, e uma
feature nascida de um `dev` atrasado encontra o merge do upstream depois, com o
código dela no meio do caminho — e aí o conflito é resolvido por quem não conhece
a mudança do core.

```bash
moodev new minha-feature --from origin/dev
moodev ls                 # confira o offset, o stack e a URL que sairam
```

**O código vem do `--from`, mas o banco vem do offset 0.** Se ele estiver numa
branch à frente da base, o banco nasce com plugin mais novo que o código e o
upgrade recusa com `cannotdowngrade`.

## Qual teste responde qual pergunta

| Pergunta | Quem responde |
|---|---|
| A regra de negócio está certa? | PHPUnit |
| A tela faz o que promete, ida e volta? | Behat sem JS |
| A tela **parece** o que foi desenhado? | Behat `@javascript` que **mede** |
| O dado real, no serviço real, está certo? | roteiro em [`../data-validation/`](../data-validation/) |

Escolher errado custa das duas formas. Uma regra de negócio testada por Behat é
lenta e frágil; um layout "testado" por PHPUnit não é testado.

**O que deliberadamente não se testa:** igualdade de pixel, fonte carregada, e
estética. Número resolve "56px de altura"; "chegou perto do desenho" é julgamento
humano, com as capturas lado a lado.

## PHPUnit: as armadilhas desta base

**`RENDERER_TARGET_GENERAL` em todo teste de renderable.**

```php
// Sem o alvo explicito, o Moodle entrega core_renderer_cli em CLI, e os testes
// de negacao passam verificando nada.
$output = $PAGE->get_renderer('local_partners', null, RENDERER_TARGET_GENERAL);
```

Custou uma rodada vermelha em `theme/ldg/tests/body_attributes_test.php`.

**`$this->resetAfterTest()` e `$this->setAdminUser()`** abrem praticamente todo
teste da base. Sem o primeiro, um teste contamina o seguinte por ordem de
execução — e o sintoma aparece em outro arquivo.

**Nome de teste descreve a regra, em português, não a mecânica.**
`test_a_faixa_final_e_descrita_pelo_teto_anterior` diz o que quebra quando fica
vermelho. `test_tiers_returns_array` não diz nada.

**`#[\PHPUnit\Framework\Attributes\CoversClass(...)]`**, atributo de PHP 8, não
annotation. `final class X_test extends \advanced_testcase`.

**Teste que só monta contexto não pega erro de template.** Se a classe é
`templatable`, feche com um teste que chama `render_from_template()` e afirma que
não lança — é o único que vê chave de mustache errada.

> **Nunca escreva uma tag de mustache dentro de um comentário de mustache.** O
> comentário `{{! … }}` termina no **primeiro** `}}`, e não no que fecha o bloco.
> Citar uma tag no docblock para explicar de onde vem o dado encerra o
> comentário ali, e todo o resto do texto — incluindo o *Example context* — sai
> como parágrafo na página. Aconteceu na landing: o contexto estava certo, o
> PHPUnit passava, e o defeito só apareceu na captura de tela. A rede é um teste
> que afirma que `@template` e `Example context` não aparecem no HTML.

## Behat: o que é específico de UI

O guia de como rodar está em [`behat.md`](behat.md). Aqui, só o que morde:

**Feature nova não é coletada sozinha.** Depois de criar ou renomear um
`.feature`, rode `php public/admin/tool/behat/cli/util.php --enable`. Sem isso o
behat responde `No scenarios` e parece que o arquivo está errado.

**`@javascript` exige duas coisas ao mesmo tempo:** `moodev up --full`, que
acrescenta o serviço `selenium` ao stack, e `--profile=chrome` na linha de
comando. Faltando qualquer uma, o behat procura Selenium em `localhost:4444` e
morre com um erro que parece problema de ambiente.

**Responsividade tem passo no core**, e não precisa de invenção
(`lib/tests/behat/behat_general.php`):

```gherkin
When I change viewport size to "mobile" without runtime scaling
When I change viewport size to "1440x900"
```

Aceita `mobile`, `tablet`, `small`, `medium`, `large` ou `<largura>x<altura>`.

> **O `without runtime scaling` não é detalhe.** Por padrão o Moodle **escala** o
> conteúdo depois de redimensionar, e os 425px do `mobile` passam a se comportar
> como uns 600. Uma asserção sobre celular medida assim afirma outra coisa — um
> cenário desta base chegou a passar dizendo o que não era.

**Com `cachejs` ligado, o Moodle serve o AMD compilado.** Módulo novo sem
`amd/build/` simplesmente não roda: botão que não faz nada, sem erro no console e
sem pista. `npx grunt amd` gera, e os arquivos vão versionados, como no
`theme_ldg`.

**O `grunt` não roda dentro do container.** O node de lá é v20 e o Moodle 5.2
exige v22. Rode no **host**, de dentro do diretório do componente — o grunt
detecta o componente pelo diretório e lint só o que interessa.

**`evaluate_script` avalia uma expressão, não um bloco.** Envolva numa IIFE:

```php
$this->getSession()->evaluateScript('(function() { var el = ...; return el.getBoundingClientRect().top; })()');
```

Sem isso o Chrome devolve `Unexpected token 'const'` longe da causa real.

**Botão de moodleform não é "visto".** `add_action_buttons` renderiza
`<input type="submit" value="…">`, e `I should see` lê nó de texto. Use
`"Salvar" "button" should exist`, ou `I press`.

**Asserção em página de exceção sempre falha.** O `behat_hooks` procura exceções
depois de cada passo, então `I should see "<mensagem>"` numa página que lançou
`moodle_exception` falha mesmo quando a exceção é o comportamento correto. Esses
casos ficam no PHPUnit.

### O que vale medir, e não só afirmar

Um cenário `@javascript` que só faz `I should see` desperdiça o navegador. Os que
pagam o próprio custo medem:

- **posição depois da rolagem** — pega `position: sticky` morto por um ancestral
  com `overflow: hidden`, que falha sem erro e sem log;
- **`left` e `top` dos irmãos** em duas larguras — é a afirmação de "uma coluna no
  celular, três no desktop", e nenhum teste de servidor a faz;
- **`scrollWidth <= innerWidth`** — rolagem horizontal no celular é o defeito mais
  comum e não aparece em nenhum outro lugar;
- **altura do alvo de toque** — 44px é requisito, não gosto.

## Provar UI no navegador

O roteiro do portal do aluno está em
[`portal-conferencia-visual.md`](portal-conferencia-visual.md). As regras que
valem para **qualquer** trabalho visual daqui:

**Meça o DOM depois de o JavaScript rodar.** Marcação correta e layout correto são
coisas diferentes.

**Número antes de opinião.** `getBoundingClientRect()` e `getComputedStyle()`
primeiro; screenshot depois, para o julgamento humano.

**Compare com o mockup renderizado no mesmo Chrome e no mesmo viewport**, não com
o PNG. PNG contra tela compara dois motores de renderização, e a diferença que
sobrar não é do nosso código.

**Contraste se calcula, não se estima.** `#6b7a90` parecia certo, dava 4,36:1 e
reprovava em AA. O número medido vai como comentário no arquivo de estilo, ao
lado da cor.

**As larguras** são 360, 390, 768, 1024 e 1440. As duas primeiras são celular
real; 768 é onde a maioria dos layouts quebra.

**A sonda vive no scratchpad da sessão, com nome longo** — nunca dentro de
`public/`. Arquivo de sonda com nome curto na raiz do Moodle já sobrescreveu
arquivo do core aqui.

**Sem sessão, a sonda mede a tela de login** e diz que está tudo bem.

**Antes de concluir que uma regra de CSS não pegou, confira o artefato que a
página carregou.** A revisão muda a cada `purge_caches`, e a aba pode reusar a
anterior — duas correções foram dadas como falhas por isso numa rodada só, e as
duas estavam corretas:

```bash
url=$(curl -sk <pagina> | grep -oE 'styles.php/[^"]*' | head -1)
curl -sk "https://localhost:8443/theme/$url" | grep -o 'SEU_SELETOR{[^}]*}'
```

**Meça as duas sessões.** Muita coisa muda entre o visitante anônimo e o usuário
autenticado — que botão aparece, para onde a raiz do domínio leva, se o menu de
idiomas fica na navbar ou dentro do menu do perfil. Uma bateria que só mede uma
das duas passa dizendo metade.

**Cromo duplicado não é erro de HTML, e nenhum teste de servidor o pega.** Quando
um plugin desenha o próprio cabeçalho e rodapé e o tema também desenha os dele, o
HTML dos dois está correto — o defeito é haver dois. A prova é contar:
`I should see exactly 1 ".ldgp-bar" elements`.

## CSS de plugin que precisa funcionar em qualquer tema

Um plugin com `styles.css` entra na CSS compilada de **todos** os temas:
`lib/classes/output/theme_config.php` varre todo tipo de plugin atrás desse
arquivo. Não é preciso `$PAGE->requires->css()` nem cooperação do tema.

**Mas ele entra antes da CSS do tema** — a ordem é `plugins` → `parents` →
`theme`. Em empate de especificidade, o tema vence. Por isso:

**Toda regra carrega o escopo do plugin.** `.meuplugin .form-control` dá 0,2,0
contra o 0,1,0 do tema e vence independentemente da ordem. Uma única regra
desescopada funciona sob Boost e **some sob o tema do projeto, em silêncio** —
que é o pior modo de falhar.

**Tokens em custom property no wrapper do plugin, nunca em `:root`.** O `:root` é
o `<html>`, e os atributos de estado que interessam costumam ser escritos no
`<body>` ou mais abaixo. Um seletor que mira `:root` casa quando não devia.

**Redefina as `--bs-*` dentro do escopo.** É a defesa mais barata contra tema
alheio: os componentes do Bootstrap que você reaproveita leem essas variáveis, e
passam a ler as suas por herança.

**`data-bs-theme` funciona em qualquer elemento** no Bootstrap 5.3, não só na
raiz — e o Boost do 5.2 compila os blocos do modo escuro (`$enable-dark-mode:
true`). Pôr o atributo num wrapper rebaseia as variáveis daquela subárvore em
qualquer tema derivado do Boost, inclusive o Boost puro, **que não tem alternador
nenhum**. O `theme_ldg` escreve o atributo no `<body>`; o `theme_moove` usa a
classe `body.moove-darkmode`. Os dois compartilham a preferência `dark-mode-on`.

**Utilitário do Bootstrap é `!important`.** `.d-flex`, `.d-none`, `.text-center`
e companhia vêm com `!important` por desenho. Uma grade declarada por cima de um
`.d-flex` é ignorada — e o `getComputedStyle` mostra as duas coisas ao mesmo
tempo, `grid-template-columns` definido e `display: flex`, o que faz o diagnóstico
parecer impossível. Ou se trabalha **com** o flex (`flex-basis` nos filhos), ou
se usa `!important` com justificativa.

> **`purge_caches` não invalida CSS de plugin.** Em 03/09/2026 a revisão do tema
> ficou parada antes e depois do purge, e o que fez o CSS novo aparecer foi o
> **bump de `version.php`**. Editou `styles.css`? Suba a versão do plugin — e
> lembre que cada `upgrade.php` bloqueia o site inteiro com "Site is being
> upgraded" enquanto roda. Agrupe os bumps.

E mais duas do mesmo cache, que custaram diagnóstico errado em 10/09/2026:

**A primeira medição depois de um purge mede a página errada.** O primeiro acesso
recompila a CSS de todos os temas, e a sonda encontra a página a meio caminho.
Descarte a primeira leitura.

**Revisão de CSS em cache engana entre temas.** Pedir
`styles.php/boost/<revisão do ldg>/all` devolve um artefato antigo, e a conclusão
foi "a regra não chegou ao Boost" quando ela tinha chegado. Use `-1` para forçar.

## Formulário: estilizar sem perder o que o moodleform dá de graça

**Renderize o form dentro de um mustache seu**, com `$form->render()`. O
precedente é do core: `login/signup_form.php` captura o form e o
`core_renderer::render_login_signup_form()` o joga dentro de
`core/signup_form_layout.mustache`.

Isso preserva sem esforço nenhum sesskey, `is_cancelled()`, redisplay de erro do
servidor, o widget do reCAPTCHA e a tipagem do `get_data()` — porque continua
sendo o form do Moodle renderizando a si mesmo. HTML à mão custa reescrever tudo
isso para ganhar aparência.

**Grade e classes saem de `class` e `parentclass` por elemento**
(`lib/form/templatable_form_element.php`): `class` vai para o input **e** para o
wrapper; `parentclass` vai só para o `<div class="… fitem">`.

**Um plugin `local` não pode sobrescrever `core_form/element-*.mustache`.** A
resolução de template é por componente; só um tema sobrescreve o `core_form`. Se
a aparência depender disso, ela volta a depender do tema.

**Card de rádio sai de `element-radio.mustache`, que imprime `{{{text}}}` cru**
dentro do `<label>`. O card inteiro cabe ali como HTML montado no PHP, e o estado
selecionado vem de seletor irmão `input:checked ~ .card`. Use `opacity: 0` no
input, **nunca `display: none`** — o segundo tira o rádio da ordem de foco e mata
a navegação por setas, que é como um leitor de tela escolhe entre rádios.

> **`addGroup(..., $appendName = true)` renomeia o campo em silêncio.** Com
> `true`, `planid` vira `plangroup[planid]`, o salvamento grava `null` para
> sempre, e nenhum teste que não escolha aquele campo vai perceber. Passe
> `false`, e escreva o teste que prova que o valor chega à linha.

**Validação de servidor não é opcional.** O `required` do HTML some com um
`curl`. Todo campo obrigatório precisa de checagem em `validation()`.

## Strings, migração e privacidade

**Ordem alfabética nas strings é obrigatória.** Inserir por âncora quebra o
`phpcs`; reordene o arquivo inteiro depois de acrescentar. Os idiomas são `en`,
`pt_br` e `es`, e os três precisam do **mesmo conjunto de chaves**.

**`db/install.xml` e `db/upgrade.php` precisam concordar**, inclusive na ordem
das colunas — o último argumento de `xmldb_field` é o campo anterior. Prove com
`php admin/cli/check_database_schema.php`, não no olho.

> **Os scripts de CLI estão em dois lugares, e a distinção não é arbitrária.** O
> CLI do **core** fica na raiz, fora do webroot: `admin/cli/upgrade.php`,
> `admin/cli/check_database_schema.php`, `admin/cli/purge_caches.php`,
> `admin/cli/cfg.php`. O CLI de **plugin** fica sob `public/`, junto do plugin:
> `public/admin/tool/behat/cli/init.php`,
> `public/admin/tool/phpunit/cli/init.php`. Errar o caminho dá
> "Could not open input file", que parece ambiente quebrado e não é.

**Não use `'choices'` em propriedade anulável do persistent.** A checagem da lista
roda antes da validação customizada e reprova o próprio `null`, porque
`in_array(null, ['a', 'b'])` é falso. Lista fechada em campo anulável se valida
num método `validate_<campo>()`, com saída antecipada no vazio.

**`db/install.php` só roda em instalação nova.** Sem passo no `db/upgrade.php`,
nada muda no que já está no ar. O mesmo vale para `assign_capability()`, que só
acrescenta: papel que já existe guarda as capabilities do desenho antigo, e
reconciliar significa apagar o que saiu da lista.

**Não retroaja consentimento.** Coluna de aceite acrescentada depois nasce
anulável: preencher as linhas antigas com `1` inventa um consentimento que
ninguém deu. E grave o **momento**, não um booleano — um `1` em toda linha é
redundante com a existência da linha.

**Escreva no docblock o que sobrevive ao esquecimento, e por quê.** No
`privacy\provider`, a decisão de anonimizar em vez de apagar, ou de preservar um
carimbo de aceite, é exatamente o tipo de coisa que alguém desfaz por engano seis
meses depois.

## Antes de dizer que acabou

```bash
# PHPUnit da suite inteira do plugin
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  php vendor/bin/phpunit --testsuite <componente>_testsuite

# phpcs - LEIA O TOTAL. O CI roda com moodle-extra + --max-warnings 0:
# aviso tambem reprova. Na raiz, .phpcs.xml ja soma o extra aos excludes.
docker exec -u 1000:33 ldg-courses-moodle-1 \
  phpcs --standard=moodle-extra -p --report=summary public/<caminho>

# mustache-lint, eslint e stylelint
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 npx grunt

# behat: primeiro sem JS, depois com
… --tags "@<componente>"
… --profile=chrome --tags "@<componente>&&@javascript"
```

**Mexeu no `db/`? Rode o conferidor de esquema do core.**

```bash
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  php admin/cli/check_database_schema.php
```

Ele compara o banco VIVO com o `install.xml` e e o unico instrumento que enxerga
migracao que "rodou" sem fazer nada. Saida esperada: `Database structure is ok.`

Isso nao e zelo — em 11/09/2026 ele achou, em um segundo, um defeito que estava
em producao havia dez dias: um passo de upgrade acrescentava uma coluna a uma
tabela cujo nome veio da CLASSE (`course_policy`) e nao da constante `TABLE` dela
(`local_marketplace_course`). A guarda `table_exists()` do proprio passo devolveu
falso, nada foi criado, e o upgrade terminou com sucesso.

**O que torna essa familia de defeito perigosa e o silencio dos dois lados.** O
`insert_record` do Moodle DESCARTA campo que a tabela nao tem, em vez de recusar.
Entao o valor sumia na gravacao, a leitura devolvia nulo, e nulo tinha um
significado legitimo — "herda a base do site". Comissao negociada sobre o liquido
saia cobrada sobre o bruto, e nenhuma tela dizia nada.

**PHPUnit nao pega isso, e vale saber por que:** o ambiente de teste instala do
ZERO, e a instalacao nova le o `install.xml`, onde a coluna sempre esteve. O
esquema de teste sempre bate. Quem diverge e o site ATUALIZADO — e ele nao existe
no CI. Por isso o teste que pega e o que le o TEXTO da migracao
(`local_marketplace/tests/db_schema_test.php`), e nao o que consulta o banco.

### A regra que saiu dali

**Uma instrucao de migracao nao pode rodar sem fazer nada e ainda assim passar.**

Quando a tabela e do proprio plugin — declarada no `install.xml` —, ela existe.
Perguntar se existe antes de acrescentar campo nao protege de nada, e transforma
nome errado em silencio:

```php
// NAO. O table_exists() engole o nome errado, e o upgrade termina com sucesso.
if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
    $dbman->add_field($table, $field);
}

// SIM. Nome errado estoura na hora, com o nome da tabela na mensagem.
if (!$dbman->field_exists($table, $field)) {
    $dbman->add_field($table, $field);
}
```

Tres guardas continuam CERTAS, e nao casam com o padrao proibido:

| Forma | Por que e legitima |
|---|---|
| `if (table_exists(...))` antes de `drop_table` | so se apaga o que existe, e a tabela pode ja ter sido apagada |
| `if (!table_exists(...))` antes de criar | criacao idempotente; a negacao e outra forma |
| `table_exists('<plugin de terceiro>')` | ele pode nao estar instalado, e ai a guarda **e** a regra de negocio |

`db_schema_test` cobra isto nos **dez** plugins do projeto, e nao so no
marketplace: o defeito nao era do plugin, era da forma, e a forma cabe em
qualquer `db/upgrade.php` escrito amanha. Plugin ausente da arvore e pulado, e
nao reprovado.

**Passo que ja rodou nao se conserta.** Um passo de upgrade roda uma vez por
site: onde ele ja passou, mudar o texto nao tem efeito nenhum, e onde ainda nao
passou o passo corretivo resolve do mesmo jeito. Escreva o passo novo, deixe o
antigo como esta com um comentario explicando, e registre o engano em
`ERRADAS_JA_CORRIGIDAS` ou `GUARDAS_JA_CORRIGIDAS` — listas que exigem, elas
proprias, que o passo corretivo exista.

**`tail -3` no phpcs esconde o relatório.** Já se reportou "zero violações" com 16
erros presentes, e o CI reprovou. Leia o total.

E a verificação que nenhum comando faz: **releia este documento depois de segui-lo**.
Padrão escrito antes da execução e nunca revisto vira ficção — o valor dele está
em ser corrigido pelo que deu errado na primeira vez que foi usado.
