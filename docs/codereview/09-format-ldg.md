# course/format/ldg

[Voltar ao índice](README.md)

## 1. Destino Forum/Certificado embutido sem checagem de bloqueio
- **Status:** corrigido
- **Arquivo:** `classes/output/courseformat/content.php:122`
- **Achado:** o destino Forum/Certificado pega o primeiro cm do bucket
  não-filtrado do catálogo e embute direto, sem checagem `uservisible`/lock e
  sem mensagem de bloqueio, ao contrário do caminho AULA que trata
  explicitamente itens bloqueados.
- **Cenário de falha:** um aluno que não comprou o entitlement que libera o
  certificado do curso clica na aba Certificado. Em vez da mensagem de
  cadeado/bloqueio usada em todo o resto do portal, recebe qualquer página
  crua de "acesso negado" que o módulo embutido renderizar dentro do
  iframe — UX inconsistente, sem defesa em profundidade.
- **Correção:** `lessonviewer::export_for_template()` checa `!$cm->uservisible`
  e exporta `locked` + `lockinfo` (via `info::format_info()`) sem `hasframe`.
  O mustache renderiza o bloco `.ldg-lesson__*` (CSS estrutural em
  `format/ldg/styles.css`, pintura em `theme/ldg/scss/ldg/_format.scss`).
  Testes: `lessonviewer_test::test_bloqueada_mostra_cadeado_e_nao_embuti`
  e `test_liberada_embuti_o_quadro`.

## 2. Aba Forum/Certificado mostrada mesmo sem item visível
- **Status:** corrigido
- **Arquivo:** `classes/portalnav.php:116`
- **Achado:** `destinations()` decide se mostra a aba Forum/Certificado
  usando `catalog->has($chave)`, que só checa que o bucket não está vazio,
  não que o usuário atual pode ver algum item nele.
- **Cenário de falha:** num curso onde o único certificado está bloqueado por
  um entitlement não cumprido, todo aluno ainda vê a aba "Certificado" nas
  três superfícies de navegação. Clicar leva ao embed quebrado do achado
  acima, em vez de a aba simplesmente não aparecer.
- **Correção:** novo `catalog::has_visible($type)` (itera o bucket e exige
  `uservisible`). `portalnav::destinations()` usa `has_visible` para FORUM e
  CERTIFICADO; AULA e MATERIAL continuam em `has()` porque a lista própria
  mostra cadeado por item. Testes: `catalog_test` (3 casos) e
  `portalnav_test::test_forum_bloqueado_nao_vira_aba` /
  `test_abas_de_lista_nao_filtram_por_item`.

## 3. `get_selected_cm()` teleporta silenciosamente para primeira lição visível
- **Status:** corrigido
- **Arquivo:** `lib.php:197`
- **Achado:** cai silenciosamente para a primeira lição `uservisible` de todo
  o curso quando a lição pedida/próxima não é `uservisible`, sem aviso de
  bloqueio.
- **Cenário de falha:** um aluno clica em "próxima lição" esperando a lição
  4, mas ela está bloqueada. O aluno é teleportado silenciosamente para uma
  lição disponível não relacionada, sem explicação, enquanto o `lessonnav`
  ainda mostrava o nome da lição bloqueada como se fosse alcançável.
- **Correção:** o cmid pedido na URL (`optional_param('lesson')`) é devolvido
  mesmo quando `!uservisible` — o `lessonviewer` mostra o cadeado. Fallback
  para a primeira visível só quando não há pedido ou o pedido não existe no
  curso. Testes: `lessonlist_selection_test::test_aula_bloqueada_pedida_e_devolvida`
  e `test_sem_pedido_cai_na_primeira_disponivel`.

## 4. `durations_for()` chamado por seção, não por página
- **Status:** corrigido
- **Arquivo:** `classes/output/courseformat/content/lessonlist.php:177`
- **Achado:** `lesson::durations_for()` é chamado uma vez por seção dentro de
  `export_lessons()`, não uma vez por página como o próprio docblock do
  método em `lesson.php` afirma.
- **Cenário de falha:** um curso com N seções dispara N consultas de duração
  separadas em vez de 1, reintroduzindo em nível de seção o exato problema
  N+1 que o docblock da classe afirma ter sido corrigido, sem detecção porque
  contagem de consultas de duração não é verificada por nenhum teste.
- **Correção:** `export_for_template()` coleta todos os cmids de aula do
  curso e chama `lesson::durations_for()` uma vez; o array passa para
  `export_lessons()`. Contagem de leituras com `$DB->perf_get_reads()` em
  `lessonlist_durations_test::test_duracoes_vem_de_uma_consulta_por_pagina`
  (teto de 1 na janela da exportação).

## 5. `get_selected_cm()` duplica varredura de classificação do `catalog`
- **Status:** corrigido
- **Arquivo:** `format.php:174`
- **Achado:** revarre toda seção/cm e chama `catalog::classify()` por cm,
  duplicando exatamente a varredura de classificação que o construtor de
  `catalog.php` já faz momentos depois — duplicação que o próprio docblock de
  `catalog.php` afirma ter sido eliminada.
- **Cenário de falha:** toda renderização de página do portal faz uma
  varredura completa de classificação do curso pelo menos duas vezes, mais
  uma terceira varredura parcial em `lessonlist.php::export_lessons()` —
  triplicando o trabalho de classificação por requisição e criando uma
  armadilha de manutenção em três frentes para qualquer mudança futura na
  regra de classificação.
- **Correção:** `get_selected_cm(?catalog $catalog = null)` aceita o
  catálogo já construído; `content::export_for_template()` cria um `catalog`
  e o repassa para seleção, `portalnav` e listas — uma varredura por página.
  Prova: `lessonlist_selection_test::test_get_selected_cm_usa_o_catalogo_recebido`
  (catálogo construído antes da aula nova não a enxerga).

## 6. `section_progress` reimplementa regra do core em silêncio
- **Status:** corrigido
- **Arquivo:** `classes/section_progress.php:36`
- **Achado:** reimplementa manualmente a regra de percentual de conclusão por
  seção do Moodle porque o método real do core é `protected` — o próprio
  docblock do arquivo admite: "Se o Moodle mudar essa regra, a nossa diverge
  em silêncio."
- **Cenário de falha:** um upgrade futuro do core muda o que conta para
  conclusão de seção; as barras de progresso do `format_ldg` discordam
  silenciosamente do resto do relatório de conclusão do próprio Moodle, com
  só um teste unitário do mesmo plugin capaz de pegar a divergência.
- **Correção:** `section_progress_core_parity_test` compara
  `section_progress::for_section()` com `cmsummary::calculate_section_stats()`
  via `ReflectionMethod` (método protected do core) em cinco cenários:
  seção mista, aula bloqueada, sem conclusão, visitante e seções vazias —
  qualquer mudança de regra no core quebram os testes de paridade.
