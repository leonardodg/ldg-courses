# format_ldg

Formato de curso no estilo **portal do aluno**: uma aula por vez no centro, a
lista completa ao lado, e o progresso à vista.

## Para que serve

O formato padrão do Moodle empilha todas as atividades numa página. Aqui a
**seção é o módulo** do curso e cada atividade dentro dela é uma **aula**. A tela
mostra a aula em foco embutida, com a lista de todas as aulas agrupadas por
módulo, marcando o que já foi concluído e o que ainda está bloqueado.

O bloqueio é o motivo de o formato existir neste projeto: num marketplace, parte
do conteúdo fica atrás de uma compra, e o aluno precisa **ver que existe** antes
de decidir comprar.

## Dependências

| Dependência | Por quê |
|---|---|
| Moodle 5.2 (`2026042000`) | usa `core\output\choicelist` e o `courseformat` reativo |
| **`theme_ldg`** *(opcional)* | **o estilo e o chrome do portal vivem no tema**, não aqui |

**Leia isto antes de instalar com outro tema.** A divisão é: **estrutura no
plugin, marca no tema**. A grade das colunas, os breakpoints e as posições estão
em `styles.css`, que o Moodle carrega para qualquer tema; a cor, a fonte, o raio
e a sombra estão em `theme/ldg/scss/ldg/_format.scss`.

Com outro tema o portal fica **cinza mas usável** — colunas no lugar, navegação
funcionando, sem a marca. Com o `theme_ldg`, fica o desenho completo. Isso é
decisão de projeto: outras empresas usam este formato com o tema delas.

### O chrome do portal, e como ele se desliga sozinho

Para o aluno, a página do curso é servida por um layout **sem navbar e sem
drawers** — o chrome inteiro é do portal. Quem pede a troca é
`\format_ldg\hook_callbacks`, no hook `before_http_headers`; quem desenha é o
tema, no layout `ldgportal`.

A troca só acontece se **todas** estas forem verdade:

- a página é a do curso (`pagelayout` = `course`), num curso neste formato;
- o usuário **não** está com a edição ligada — professor editando volta para o
  chrome do Moodle, porque arrastar atividade e o menu de ações vivem nos ganchos
  que o core põe na pilha de seções;
- **o tema ativo declara o layout `ldgportal`**.

A última é o que torna a dependência opcional de verdade: com outro tema, o
formato não troca nada e o curso abre no chrome daquele tema — sem erro e sem
`debugging()` no log. Há um cenário de Behat fixando exatamente isso.

> `ldgportal` é o **contrato** entre os dois plugins. Renomear o layout no tema
> desliga o portal em silêncio.

O `availability_marketplace` **não** é dependência. O formato nunca pergunta ao
marketplace quem comprou o quê: ele lê `cm_info->uservisible`, que já chega
resolvido. Se o plugin não estiver instalado, o formato simplesmente nunca mostra
cadeado de compra.

## O que precisa configurar

Nada, no nível do site. No curso:

1. **Configurações do curso → Formato → Portal LDG**
2. **Conclusão de atividades ligada**, no curso e nas atividades. Sem isso não há
   progresso para mostrar, e as barras não aparecem — o que é diferente de
   aparecerem zeradas.
3. Opcional: **duração de cada aula**, preenchida com a edição ligada, no campo
   ao lado de cada item da lista. Em segundos. Vazio significa "não sei", e a
   lista simplesmente não mostra nada — diferente de zero.

### O índice lateral fica desligado neste curso

`uses_course_index()` devolve `false`. A lista de aulas já cumpre esse papel, na
mesma tela, e duas navegações concorrentes disputariam o mesmo espaço.

Não é só estética: o índice do Moodle é renderizado **no cliente**, a partir do
state reativo, com um template fixo do core. Trocar o conteúdo dele exigiria um
módulo AMD próprio — custo que não se paga.

## Os quatro destinos

O portal tem quatro telas, e **o professor não configura nenhuma**: o papel sai do
**tipo** da atividade, em `classes/catalog.php`.

| Destino | O que cai nele |
|---|---|
| Aulas | tudo que não for os outros três — `page`, `quiz`, `assign`, `lesson`… |
| Material de apoio | `resource`, `folder`, `url` |
| Fórum de alunos | `forum` |
| Certificado | `customcert` |

**Destino sem conteúdo não aparece.** É o que desarma o risco de classificar por
tipo: curso sem fórum não mostra aba de fórum vazia.

O destino corrente viaja na URL — `?ldgview=lessons|materials|forum|certificate`,
ao lado do `?lesson=<cmid>` — então botão voltar, favorito e Ctrl+clique
funcionam sem JavaScript. Valor desconhecido, ou de destino que o curso não tem,
cai em Aulas sem erro.

Rótulo, material, fórum e certificado **não aparecem na lista de aulas**: cada um
tem destino próprio. O corte que exclui o rótulo é a **ausência de URL** — não dá
para usar `is_of_type_that_can_display()`, que é
`plugin_supports(FEATURE_CAN_DISPLAY, true)` com default **true**, e o `mod_label`
nunca declara a flag.

### Onde cada material abre

| Sinal no `cm_info` | O que o portal faz |
|---|---|
| `mod_resource` com `display` = `RESOURCELIB_DISPLAY_DOWNLOAD` | link de download |
| `mod_url` | abre em aba nova |
| `onclick` preenchido, ou `display` `NEW`/`POPUP` | abre em aba nova |
| resto | abre no quadro embutido |

Duas armadilhas moram aqui. A primeira: **arquivo com download forçado não pode
abrir no quadro** — dentro de um iframe o download dispara e o quadro fica em
branco. A segunda é do core: `url_get_final_display_type()` põe `text/html` na
lista de download (`mod/url/locallib.php:355`), então **qualquer link para uma
página web resolve para `DISPLAY_DOWNLOAD`** — e ali aquilo significa "manda o
navegador para a URL", não "salva um arquivo". Por isso a regra separa
`mod_resource` de `mod_url` em vez de olhar só o número.

## Armadilhas

**Com a edição ligada, a tela muda.** A coluna principal volta a ser a pilha de
seções do core. Não é bug: arrastar atividade, renomear e o menu de ações vivem
nos ganchos que o core põe naquela marcação, e nenhum funciona dentro de um
quadro embutido. Para ver o curso como o aluno vê, **desligue a edição**.

**A atividade embutida vem sem o cabeçalho do site — só com o `theme_ldg`.** O
Moodle não tem um modo embutido genérico: apenas o `mod_page` aceita `inpopup`, e
só num caso. Quem esconde o cabeçalho é o tema, detectando o cabeçalho HTTP
`Sec-Fetch-Dest: iframe`. Com outro tema, o quadro mostra o site inteiro dentro
dele.

**O progresso por seção é código copiado do core.** `\format_ldg\section_progress`
replica `cmsummary::calculate_section_stats()`, que é `protected`. Se o Moodle
mudar a regra de contagem, a nossa **diverge em silêncio**. O teste
`section_progress_test` existe para avisar: quando ele falhar depois de um
upgrade, o provável é que a regra mudou lá, não que o teste está ruim.

**Aula bloqueada não entra no denominador do progresso.** Se entrasse, quem não
comprou veria a barra travar abaixo de 100% sem nenhuma explicação na tela.

**Trocar de formato não apaga as durações.** Sair para o formato de seções e
voltar preserva o que foi preenchido. As linhas ficam órfãs no meio-tempo, e isso
é de propósito — a tabela não tem chave estrangeira para `course_modules`
justamente para que dado de formato nunca impeça a troca de formato. A limpeza só
acontece quando o **curso** é apagado.

**`availableinfo` nem sempre é `string`.** Com mais de uma condição de acesso ele
vem como `core_availability_multiple_messages`, e passá-lo para `format_string()`
estoura `TypeError`. Use `\core_availability\info::format_info()`.

**Não escreva tag mustache dentro de comentário mustache.** O comentário termina
no primeiro `}}`, então uma tag citada em prosa vira seção de verdade sem
fechamento. O erro sai como `Missing closing tag` apontando para uma linha que não
tem nada a ver.

## Estrutura

```
classes/
  catalog.php                   separa o curso por papel da atividade
  portalnav.php                 os quatro destinos e o corrente
  hook_callbacks.php            decide e troca o layout da página
  lesson.php                    duração por cmid (persistent)
  section_progress.php          a contagem que o core não expõe
  external/set_duration.php     grava a duração, via AJAX
  output/courseformat/
    content.php                 estende o content do core
    content/lessonlist.php      a lista de aulas
    content/lessonnav.php       o atalho anterior/próxima
    content/lessonviewer.php    a aula embutida
    content/materiallist.php    o material de apoio
templates/local/               content, lessonlist, lessonnav, lessonviewer,
                               materiallist, portalnav
styles.css                     a ESTRUTURA do portal, para qualquer tema
amd/src/                       player (altura do quadro), duration (edição),
                               aside (esconder as laterais)
db/hooks.php                   registra o listener do before_http_headers
backup/moodle2/                leva e traz a duração
cli/make_testdata.php          monta um curso de demonstração
```

**`styles.css` é estrutura; o tema é marca.** Grade, colunas, breakpoints e
posições moram aqui, porque o Moodle carrega o `styles.css` do plugin para
qualquer tema — é o que faz o portal ficar cinza mas usável fora do `theme_ldg`.
Cor, fonte, raio e sombra ficam no tema.

Cuidado ao mexer: o CSS do tema entra **depois** do `styles.css` do plugin no
arquivo servido, então grade escrita no tema vence a daqui **em silêncio**.

## O atalho entre aulas

A barra `anterior · onde estou · próxima` fica **grudada no topo do miolo**, e
isso é requisito: as laterais podem ser escondidas, e quando são, ela é a única
navegação que resta.

Duas regras, ambas em teste: na ponta o botão **não aparece** — botão que não leva
a lugar nenhum é pior que a ausência dele —, e a sequência é só de **aula**, senão
o curso com material mostraria "aula 3 de 9" tendo cinco aulas.

## Esconder as laterais

Navegação e índice somem de forma independente, e a escolha **sobrevive à troca de
aula**: fica em `format_ldg_aside_hidden`, declarada em
`format_ldg_user_preferences()`. Sem essa declaração o core **recusa** a gravação
vinda do navegador — a lateral fecha na tela e reabre na carga seguinte, com um
400 no console e nada visível.

O estado inicial vem do **servidor**, já como classe no HTML; se fosse o JS a
aplicar, a lateral apareceria e sumiria depois. E os botões vivem no cartão do
aluno, não dentro das laterais: uma lateral escondida não pode guardar o próprio
botão de voltar.

Sem JavaScript não há botão — a classe que os revela é posta pelo próprio módulo —
e as laterais ficam visíveis, que é o estado útil.

## Testes

```bash
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  php vendor/bin/phpunit --testsuite format_ldg_testsuite

docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml \
  --tags "@format_ldg"
```

As features **não** usam `@javascript`: tudo o que elas verificam é desenhado no
servidor. O que depende de navegador — a altura do quadro e a gravação da duração
— fica fora, porque o ambiente não tem um, e teste que não roda não protege nada.

## Dados de demonstração

```bash
docker exec -u 1000:33 ldg-courses-moodle-1 \
  php /var/www/html/public/course/format/ldg/cli/make_testdata.php --run --reset
```

Monta um curso com quatro módulos, aulas de conclusão manual e automática, um
quiz com pergunta, uma url e um **certificado trancado** por duas condições em E:
concluir todas as aulas **e** comprar uma oferta. O script se recusa a rodar fora
de um endereço local.
